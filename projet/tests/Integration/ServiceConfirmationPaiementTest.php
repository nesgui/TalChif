<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Evenement;
use App\Entity\Organisation;
use App\Entity\MembreOrganisation;
use App\Entity\User;
use App\Service\Paiement\ContexteConfirmation;
use App\Service\Paiement\ServiceConfirmationPaiement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verrouille l'invariant « paiement confirme » contre PostgreSQL.
 *
 * Ces cas couvrent les deux defauts qui avaient casse les trois anciens chemins
 * de confirmation : le client absent sur une commande payee en invite, et
 * l'absence d'idempotence sur une confirmation rejouee.
 */
final class ServiceConfirmationPaiementTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ServiceConfirmationPaiement $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $conteneur = static::getContainer();

        $this->em = $conteneur->get(EntityManagerInterface::class);
        $this->service = $conteneur->get(ServiceConfirmationPaiement::class);

        $this->em->getConnection()->executeStatement(
            'TRUNCATE billet, commande_ligne, commande, log_securite, evenement, membre_organisation, organisation, utilisateur RESTART IDENTITY CASCADE'
        );
    }

    public function testConfirmeUneCommandePayeeEnInviteSansClientRattache(): void
    {
        $commande = $this->creerCommandeInvite(placesDisponibles: 10, quantite: 3, prixUnitaire: 5000.0);

        $resultat = $this->service->confirmer(
            $commande,
            new ContexteConfirmation('TEST_CONFIRMATION', 'Jeu de test')
        );

        self::assertFalse($resultat->dejaConfirmee);
        self::assertSame(3, $resultat->nombreBillets, '3 billets attendus pour une ligne de quantite 3');

        $this->em->clear();

        // Un compte client a ete cree a partir de l'email de checkout.
        $client = $this->em->getRepository(User::class)->findOneBy(['email' => 'invite@talchif.td']);
        self::assertInstanceOf(User::class, $client, 'le client doit etre cree depuis checkoutEmail');
        self::assertTrue($client->isCheckoutAccount());

        // Les billets existent, sont rattaches au client et marques payes.
        $billets = $this->em->getRepository(\App\Entity\Billet::class)->findAll();
        self::assertCount(3, $billets);
        foreach ($billets as $billet) {
            self::assertSame($client->getId(), $billet->getClient()?->getId());
            self::assertSame('PAYE', $billet->getStatutPaiement());
        }

        // Le stock a ete decremente.
        $evenement = $this->em->getRepository(Evenement::class)->findOneBy(['slug' => 'evenement-de-test']);
        self::assertSame(3, $evenement->getPlacesVendues());
        self::assertSame(7, $evenement->getPlacesRestantes());

        // La commande est payee et le cashback credite (1 % de 15 000 = 150).
        $commandeRelue = $this->em->getRepository(Commande::class)->findOneBy(['reference' => 'EVT-TEST-0001']);
        self::assertTrue($commandeRelue->isPaid());
        self::assertSame(150, $client->getBalance());
    }

    public function testUneSecondeConfirmationNEmetAucunBilletSupplementaire(): void
    {
        $commande = $this->creerCommandeInvite(placesDisponibles: 10, quantite: 2, prixUnitaire: 5000.0);

        $premier = $this->service->confirmer(
            $commande,
            new ContexteConfirmation('TEST_CONFIRMATION', 'Premier appel')
        );
        self::assertSame(2, $premier->nombreBillets);

        $second = $this->service->confirmer(
            $commande,
            new ContexteConfirmation('TEST_CONFIRMATION', 'Rejeu')
        );

        self::assertTrue($second->dejaConfirmee, 'un rejeu doit etre inoffensif');
        self::assertSame(0, $second->nombreBillets);

        $this->em->clear();

        self::assertCount(2, $this->em->getRepository(\App\Entity\Billet::class)->findAll(), 'pas de billets en double');

        $evenement = $this->em->getRepository(Evenement::class)->findOneBy(['slug' => 'evenement-de-test']);
        self::assertSame(2, $evenement->getPlacesVendues(), 'le stock ne doit etre decremente qu une fois');

        $client = $this->em->getRepository(User::class)->findOneBy(['email' => 'invite@talchif.td']);
        self::assertSame(100, $client->getBalance(), 'le cashback ne doit pas etre credite deux fois');
    }

    public function testRefuseUneCommandeExpiree(): void
    {
        $commande = $this->creerCommandeInvite(placesDisponibles: 10, quantite: 1, prixUnitaire: 5000.0);
        $commande->marquerExpiree();
        $this->em->flush();

        $this->expectException(\RuntimeException::class);

        $this->service->confirmer(
            $commande,
            new ContexteConfirmation('TEST_CONFIRMATION', 'Commande expiree')
        );
    }

    private function creerCommandeInvite(int $placesDisponibles, int $quantite, float $prixUnitaire): Commande
    {
        $organisateur = new User();
        $organisateur->setEmail('organisateur@talchif.td');
        $organisateur->setNom('Organisateur Test');
        $organisateur->setRole('ORGANISATEUR');
        $organisateur->setActif(true);
        $organisateur->setIsVerified(true);
        $organisateur->setPassword('hachage-factice');
        $this->em->persist($organisateur);

        $evenement = new Evenement();
        $evenement->setNom('Evenement de test');
        $evenement->setSlug('evenement-de-test');
        $evenement->setDescription('Jeu de test');
        $evenement->setDateEvenement(new \DateTimeImmutable('+30 days'));
        $evenement->setLieu('Salle de test');
        $evenement->setAdresse('1 rue du test');
        $evenement->setVille('Ndjamena');
        $evenement->setPlacesDisponibles($placesDisponibles);
        $evenement->setPlacesVendues(0);
        $evenement->setPrixSimple($prixUnitaire);
        $evenement->setIsActive(true);
        $evenement->setIsValide(true);
        $evenement->setOrganisateur($organisateur);
        $evenement->setOrganisation($this->organisationDe($organisateur));
        $this->em->persist($evenement);

        $commande = new Commande();
        $commande->setReference('EVT-TEST-0001');
        $commande->setMontantTotal($prixUnitaire * $quantite);
        $commande->setNumeroClient('+23563519678');
        $commande->setMethodePaiement('momo');
        $commande->setStatut(Commande::STATUT_PENDING);
        $commande->setCommissionPlateforme(0.0);
        $commande->setMontantNetOrganisateur($prixUnitaire * $quantite);
        $commande->setClient(null);
        $commande->setCheckoutEmail('invite@talchif.td');
        $commande->setAccessToken(bin2hex(random_bytes(8)));
        $commande->setDateExpiration(new \DateTimeImmutable('+1 hour'));
        $this->em->persist($commande);

        $ligne = new CommandeLigne();
        $ligne->setCommande($commande);
        $ligne->setEvenement($evenement);
        $ligne->setOrganisation($evenement->getOrganisation());
        $ligne->setQuantite($quantite);
        $ligne->setPrixUnitaire($prixUnitaire);
        $ligne->setTypeBillet('SIMPLE');
        $this->em->persist($ligne);

        $this->em->flush();

        return $commande;
    }

    /**
     * Organisation du vendeur, creee au besoin et dont il est proprietaire.
     */
    private function organisationDe(User $proprietaire): Organisation
    {
        foreach ($proprietaire->getAppartenances() as $appartenance) {
            return $appartenance->getOrganisation();
        }

        $organisation = new Organisation();
        $organisation->setNom('Organisation ' . $proprietaire->getEmail());
        $organisation->setSlug('org-' . bin2hex(random_bytes(4)));
        $organisation->setActif(true);
        $this->em->persist($organisation);

        $membre = new MembreOrganisation();
        $membre->setOrganisation($organisation);
        $membre->setUtilisateur($proprietaire);
        $membre->setRole(MembreOrganisation::ROLE_PROPRIETAIRE);
        $this->em->persist($membre);
        $this->em->flush();

        return $organisation;
    }
}
