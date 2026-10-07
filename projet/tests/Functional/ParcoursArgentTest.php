<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\Evenement;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parcours d'argent de bout en bout, en HTTP reel.
 *
 * panier -> commande -> preuve de paiement -> validation -> billet -> scan
 *
 * Chaque etape passe par le controleur, le pare-feu et PostgreSQL : ce sont ces
 * couches, non couvertes jusqu'ici, qui portaient tous les defauts de l'audit.
 */
final class ParcoursArgentTest extends CasFonctionnel
{
    public function testParcoursCompletDuPanierAuScanDuBillet(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($organisateur, places: 50, prix: 5000.0);
        $urlEvenement = '/evenements/' . $evenement->getSlug() . '-' . $evenement->getId();

        // --- Etape 1 : ajout au panier, jeton lu dans la page de l'evenement ---
        $actionPanier = '/panier/ajouter/' . $evenement->getId();
        $jetonPanier = $this->jetonDuFormulaire($urlEvenement, $actionPanier);

        $this->client->request('POST', $actionPanier, [
            '_token' => $jetonPanier,
            'quantite' => 2,
            'type' => 'SIMPLE',
            'redirect' => 'panier',
        ]);
        self::assertResponseRedirects('/panier');

        $this->client->request('GET', '/panier');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Concert de test');

        // --- Etape 2 : creation de la commande (paiement invite, CSRF en en-tete) ---
        $jetonPaiement = $this->jetonDuScript('/achat', 'csrfToken');

        $this->client->request(
            'POST',
            '/api/payments/create',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $jetonPaiement,
            ],
            content: json_encode([
                'email' => 'invite@talchif.td',
                'methode_paiement' => 'momo',
                'telephone' => '+23599000001',
                'country' => 'TD',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $charge = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($charge['ok']);

        $reference = $charge['reference'];
        $commande = $this->em->getRepository(Commande::class)->findOneBy(['reference' => $reference]);
        self::assertInstanceOf(Commande::class, $commande);
        self::assertTrue($commande->isPending());
        self::assertSame(10000.0, (float) $commande->getMontantTotal(), '2 places a 5000');
        self::assertNull($commande->getClient(), 'le checkout invite ne rattache aucun client');

        $urlInstructions = '/achat/instructions/' . $reference . '?token=' . $commande->getAccessToken();

        // --- Etape 3 : preuve de paiement, jeton lu dans la page d'instructions ---
        $actionPreuve = '/achat/notifier-paiement/' . $reference . '?token=' . $commande->getAccessToken();
        $jetonPreuve = $this->jetonDuFormulaire($urlInstructions, $actionPreuve);

        $this->client->request('POST', $actionPreuve, [
            '_token' => $jetonPreuve,
            'transaction_reference' => 'MP260101.1200.A12345',
            'operateur' => 'momo',
        ]);
        self::assertResponseRedirects();

        $this->em->clear();
        $commande = $this->em->getRepository(Commande::class)->findOneBy(['reference' => $reference]);
        self::assertSame('MP260101.1200.A12345', $commande->getReferenceTransactionClient());
        self::assertTrue($commande->isProcessing(), 'la soumission de preuve passe la commande en traitement');

        // --- Etape 4 : validation par l'organisateur de l'evenement ---
        $this->client->loginUser($organisateur);
        $actionValidation = '/organisateur/references-a-verifier/valider/' . $reference;
        $jetonValidation = $this->jetonDuFormulaire('/organisateur/references-a-verifier', $actionValidation);

        $this->client->request('POST', $actionValidation, ['_token' => $jetonValidation]);
        self::assertResponseRedirects('/organisateur/references-a-verifier');

        $this->em->clear();
        $commande = $this->em->getRepository(Commande::class)->findOneBy(['reference' => $reference]);
        self::assertTrue($commande->isPaid(), 'la commande doit etre payee apres validation');

        // Le compte client a ete cree depuis l'email de checkout.
        $client = $this->em->getRepository(User::class)->findOneBy(['email' => 'invite@talchif.td']);
        self::assertInstanceOf(User::class, $client);
        self::assertTrue($client->isCheckoutAccount());

        // Deux billets emis, stock decremente.
        $billets = $this->em->getRepository(Billet::class)->findBy(['transactionId' => $reference]);
        self::assertCount(2, $billets);

        $evenementRelu = $this->em->getRepository(Evenement::class)->findOneBy(['slug' => 'concert-de-test']);
        self::assertSame(2, $evenementRelu->getPlacesVendues());
        self::assertSame(48, $evenementRelu->getPlacesRestantes());

        // --- Etape 5 : le client accede a l'image QR de son billet ---
        $billet = $billets[0];
        $this->client->loginUser($client);

        // Un compte de checkout a un profil incomplet : la page billet redirige.
        $this->client->request('GET', '/achat/billet/' . $billet->getId());
        self::assertResponseRedirects('/profil');

        $this->client->request('GET', '/billet/' . $billet->getId() . '/qrcode.png');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');

        // --- Etape 6 : le scan hors fenetre de validation est refuse ---
        // L'evenement est dans 30 jours : la fenetre (app.validation.debut_offset)
        // n'est pas ouverte, le billet ne doit pas pouvoir etre consomme.
        $this->client->loginUser($organisateur);
        $jetonScan = $this->jetonDuScript('/validation', 'scanCsrfToken');

        $this->client->request('POST', '/api/validation/scan', [
            '_token' => $jetonScan,
            'qrCode' => $billet->getQrCode(),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $scan = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($scan['success']);
        self::assertSame('TOO_EARLY', $scan['type']);

        $this->em->clear();
        self::assertFalse(
            $this->em->getRepository(Billet::class)->find($billet->getId())->isUtilise(),
            'un scan refuse ne doit pas consommer le billet'
        );
    }

    public function testScanAccepteLeBilletDansLaFenetreDeValidationUneSeuleFois(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $client = $this->creerUtilisateur('porteur@talchif.td');
        // Evenement en cours : la fenetre de validation est ouverte.
        $evenement = $this->creerEvenement($organisateur, date: 'now');
        $billet = $this->creerBillet($evenement, $client, 'BILLET-FENETRE-OUVERTE');

        $this->client->loginUser($organisateur);
        $jetonScan = $this->jetonDuScript('/validation', 'scanCsrfToken');

        $this->client->request('POST', '/api/validation/scan', [
            '_token' => $jetonScan,
            'qrCode' => $billet->getQrCode(),
        ]);
        self::assertResponseIsSuccessful();
        $premier = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($premier['success'], 'premier scan accepte');

        $this->em->clear();
        self::assertTrue(
            $this->em->getRepository(Billet::class)->find($billet->getId())->isUtilise(),
            'le billet doit etre marque utilise'
        );

        // Antifraude : un billet deja consomme ne doit jamais etre revalide.
        $this->client->request('POST', '/api/validation/scan', [
            '_token' => $jetonScan,
            'qrCode' => $billet->getQrCode(),
        ]);
        $second = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($second['success'], 'un billet deja utilise ne doit pas etre revalide');
    }

    public function testUneSecondeValidationNEmetAucunBilletSupplementaire(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($organisateur);
        $commande = $this->creerCommandeEnAttente($evenement, quantite: 2);
        $reference = $commande->getReference();
        $action = '/organisateur/references-a-verifier/valider/' . $reference;

        $this->client->loginUser($organisateur);

        $jeton = $this->jetonDuFormulaire('/organisateur/references-a-verifier', $action);
        $this->client->request('POST', $action, ['_token' => $jeton]);
        self::assertResponseRedirects();

        // Rejeu de la meme soumission, jeton inclus.
        $this->client->request('POST', $action, ['_token' => $jeton]);
        self::assertResponseRedirects();

        $this->em->clear();

        self::assertCount(
            2,
            $this->em->getRepository(Billet::class)->findBy(['transactionId' => $reference]),
            'pas de billets en double apres double validation'
        );

        $evenementRelu = $this->em->getRepository(Evenement::class)->findOneBy(['slug' => 'concert-de-test']);
        self::assertSame(2, $evenementRelu->getPlacesVendues(), 'le stock ne doit etre decremente qu une fois');
    }

    public function testLeStockRefuseUneCommandeSuperieureAuxPlacesRestantes(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($organisateur, places: 1);
        $commande = $this->creerCommandeEnAttente($evenement, quantite: 5);
        $action = '/organisateur/references-a-verifier/valider/' . $commande->getReference();

        $this->client->loginUser($organisateur);
        $jeton = $this->jetonDuFormulaire('/organisateur/references-a-verifier', $action);

        $this->client->request('POST', $action, ['_token' => $jeton]);
        self::assertResponseRedirects();

        $this->em->clear();

        self::assertCount(
            0,
            $this->em->getRepository(Billet::class)->findAll(),
            'aucun billet emis quand le stock est insuffisant'
        );

        $evenementRelu = $this->em->getRepository(Evenement::class)->findOneBy(['slug' => 'concert-de-test']);
        self::assertSame(0, $evenementRelu->getPlacesVendues());
        self::assertFalse(
            $this->em->getRepository(Commande::class)->findOneBy(['reference' => $commande->getReference()])->isPaid()
        );
    }
}
