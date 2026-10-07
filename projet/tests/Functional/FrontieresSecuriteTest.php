<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\Evenement;
use Symfony\Component\HttpFoundation\Response;

/**
 * Frontieres de securite des parcours d'argent.
 *
 * Verrouille les regles qui n'etaient garanties par aucun test : jeton CSRF
 * exige sur chaque ecriture, cloisonnement entre organisateurs, jeton d'acces
 * des commandes invitees, et fail-closed du webhook.
 */
final class FrontieresSecuriteTest extends CasFonctionnel
{
    public function testUnAjoutAuPanierSansJetonCsrfEstRefuse(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($organisateur);

        $this->client->request('POST', '/panier/ajouter/' . $evenement->getId(), [
            'quantite' => 2,
            'type' => 'SIMPLE',
        ]);
        self::assertResponseRedirects('/panier');

        $this->client->request('GET', '/panier');
        self::assertSelectorTextNotContains('body', 'Concert de test', 'le panier doit rester vide');
    }

    public function testUneCreationDeCommandeSansJetonCsrfEstRefusee(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($organisateur);

        // Panier rempli au prealable, avec jeton valide.
        $action = '/panier/ajouter/' . $evenement->getId();
        $jeton = $this->jetonDuFormulaire('/evenements/' . $evenement->getSlug() . '-' . $evenement->getId(), $action);
        $this->client->request('POST', $action, ['_token' => $jeton, 'quantite' => 1, 'type' => 'SIMPLE']);

        // Sans en-tete X-CSRF-TOKEN : refus.
        $this->client->request(
            'POST',
            '/api/payments/create',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'invite@talchif.td',
                'methode_paiement' => 'momo',
                'telephone' => '+23599000001',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertCount(0, $this->em->getRepository(Commande::class)->findAll());
    }

    public function testUnScanSansJetonCsrfEstRefuse(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $client = $this->creerUtilisateur('porteur@talchif.td');
        $evenement = $this->creerEvenement($organisateur, date: 'now');
        $billet = $this->creerBillet($evenement, $client, 'BILLET-SANS-JETON');

        $this->client->loginUser($organisateur);
        $this->client->request('POST', '/api/validation/scan', ['qrCode' => $billet->getQrCode()]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->em->clear();
        self::assertFalse($this->em->getRepository(Billet::class)->find($billet->getId())->isUtilise());
    }

    public function testUnOrganisateurNePeutPasValiderLaCommandeDUnAutre(): void
    {
        $proprietaire = $this->creerUtilisateur('proprietaire@talchif.td', 'ORGANISATEUR');
        $intrus = $this->creerUtilisateur('intrus@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($proprietaire);
        $commande = $this->creerCommandeEnAttente($evenement, quantite: 2);
        $reference = $commande->getReference();

        // Le proprietaire voit la commande et obtient un jeton valide.
        $this->client->loginUser($proprietaire);
        $action = '/organisateur/references-a-verifier/valider/' . $reference;
        $jeton = $this->jetonDuFormulaire('/organisateur/references-a-verifier', $action);

        // L'intrus rejoue la soumission avec ce jeton.
        $this->client->loginUser($intrus);
        $this->client->request('POST', $action, ['_token' => $jeton]);
        self::assertResponseRedirects();

        $this->em->clear();
        self::assertFalse(
            $this->em->getRepository(Commande::class)->findOneBy(['reference' => $reference])->isPaid(),
            'un organisateur tiers ne doit pas pouvoir encaisser une commande'
        );
        self::assertCount(0, $this->em->getRepository(Billet::class)->findAll());
    }

    public function testUnOrganisateurNeVoitPasLesCommandesDUnAutre(): void
    {
        $proprietaire = $this->creerUtilisateur('proprietaire@talchif.td', 'ORGANISATEUR');
        $intrus = $this->creerUtilisateur('intrus@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($proprietaire);
        $commande = $this->creerCommandeEnAttente($evenement);

        $this->client->loginUser($intrus);
        $this->client->request('GET', '/organisateur/references-a-verifier');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', $commande->getReference());
    }

    public function testUnOrganisateurNAccedePasAuxStatistiquesDUnAutreEvenement(): void
    {
        $proprietaire = $this->creerUtilisateur('proprietaire@talchif.td', 'ORGANISATEUR');
        $intrus = $this->creerUtilisateur('intrus@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($proprietaire);

        $this->client->catchExceptions(true);
        $this->client->loginUser($intrus);
        $this->client->request('GET', '/organisateur/evenement/' . $evenement->getId() . '/stats');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testLOrganisateurProprietaireAccedeAuxStatistiques(): void
    {
        $proprietaire = $this->creerUtilisateur('proprietaire@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($proprietaire);

        $this->client->loginUser($proprietaire);
        $this->client->request('GET', '/organisateur/evenement/' . $evenement->getId() . '/stats');

        self::assertResponseIsSuccessful('le voter doit autoriser le proprietaire');
    }

    public function testLAdministrateurAccedeAuxStatistiquesDeTousLesEvenements(): void
    {
        $proprietaire = $this->creerUtilisateur('proprietaire@talchif.td', 'ORGANISATEUR');
        $admin = $this->creerUtilisateur('admin@talchif.td', 'ADMIN');
        $evenement = $this->creerEvenement($proprietaire);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/organisateur/evenement/' . $evenement->getId() . '/stats');

        self::assertResponseIsSuccessful('la hierarchie de roles doit ouvrir l espace organisateur a l admin');
    }

    public function testLesInstructionsDePaiementExigentLeJetonDAcces(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($organisateur);
        $commande = $this->creerCommandeEnAttente($evenement);

        $this->client->catchExceptions(true);

        $this->client->request('GET', '/achat/instructions/' . $commande->getReference());
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'sans jeton : refus');

        $this->client->request('GET', '/achat/instructions/' . $commande->getReference() . '?token=mauvais');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'jeton errone : refus');

        $this->client->request(
            'GET',
            '/achat/instructions/' . $commande->getReference() . '?token=' . $commande->getAccessToken()
        );
        self::assertResponseIsSuccessful('jeton valide : acces');
    }

    public function testUnClientNAccedePasAuQrCodeDUnAutreClient(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $proprietaire = $this->creerUtilisateur('proprietaire@talchif.td');
        $intrus = $this->creerUtilisateur('intrus@talchif.td');
        $evenement = $this->creerEvenement($organisateur);
        $billet = $this->creerBillet($evenement, $proprietaire, 'BILLET-PRIVE');

        $this->client->catchExceptions(true);

        $this->client->loginUser($intrus);
        $this->client->request('GET', '/billet/' . $billet->getId() . '/qrcode.png');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->loginUser($proprietaire);
        $this->client->request('GET', '/billet/' . $billet->getId() . '/qrcode.png');
        self::assertResponseIsSuccessful();

        // L'organisateur de l'evenement y a acces pour le controle a l'entree.
        $this->client->loginUser($organisateur);
        $this->client->request('GET', '/billet/' . $billet->getId() . '/qrcode.png');
        self::assertResponseIsSuccessful();
    }

    public function testLeWebhookPawaPayRefuseToutSansSecretConfigure(): void
    {
        // PAWAPAY_WEBHOOK_SECRET est vide dans l'environnement de test :
        // le webhook doit refuser plutot que d'accepter sans verifier (fail-closed).
        $this->client->request(
            'POST',
            '/webhook/pawapay',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['depositId' => 'faux-depot', 'status' => 'COMPLETED'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
    }

    public function testLesPagesPubliquesEtLaPage404Repondent(): void
    {
        $this->client->catchExceptions(true);

        foreach (['/', '/evenements', '/panier', '/connexion', '/a-propos'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful("page publique {$url}");
        }

        $this->client->request('GET', '/url-qui-nexiste-pas');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUnVisiteurAnonymeEstRedirigeDepuisLEspaceOrganisateur(): void
    {
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/organisateur');

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/connexion',
            (string) $this->client->getResponse()->headers->get('Location')
        );
    }

    public function testUnClientNAccedePasALEspaceOrganisateur(): void
    {
        $client = $this->creerUtilisateur('client@talchif.td');

        $this->client->catchExceptions(true);
        $this->client->loginUser($client);
        $this->client->request('GET', '/organisateur');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUnOrganisateurNAccedePasALAdministration(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');

        $this->client->catchExceptions(true);
        $this->client->loginUser($organisateur);
        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAucuneReponsePubliqueNEmetDEnTeteCorsSansOrigineAutorisee(): void
    {
        $this->client->request('GET', '/', server: ['HTTP_ORIGIN' => 'https://attaquant.example']);

        self::assertResponseIsSuccessful();
        self::assertFalse(
            $this->client->getResponse()->headers->has('Access-Control-Allow-Origin'),
            'une origine non autorisee ne doit recevoir aucun en-tete CORS'
        );
    }
}
