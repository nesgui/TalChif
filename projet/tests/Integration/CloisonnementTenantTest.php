<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Doctrine\Filter\TenantFilter;
use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Evenement;
use App\Entity\MembreOrganisation;
use App\Entity\Organisation;
use App\Entity\User;
use App\Service\CommissionRateProvider;
use App\Service\Tenant\ContexteTenant;
use App\Service\Tenant\ServiceOrganisation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verrouille le cloisonnement automatique.
 *
 * L'enjeu : une requete qui oublie sa clause « WHERE organisation = ... » doit
 * rester cloisonnee. C'est l'inverse du modele precedent, ou l'isolation etait
 * a la charge de chaque requete et donc faillible par omission.
 */
final class CloisonnementTenantTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ContexteTenant $contexte;

    protected function setUp(): void
    {
        self::bootKernel();
        $conteneur = static::getContainer();

        $this->em = $conteneur->get(EntityManagerInterface::class);
        $this->contexte = $conteneur->get(ContexteTenant::class);

        $this->em->getConnection()->executeStatement(
            'TRUNCATE billet, commande_ligne, commande, log_securite, app_setting, ticket_design, evenement, membre_organisation, organisation, utilisateur RESTART IDENTITY CASCADE'
        );
    }

    protected function tearDown(): void
    {
        $this->contexte->liberer();
        parent::tearDown();
    }

    /**
     * Le cas qui justifie tout : une requete sans filtre explicite.
     */
    public function testUneRequeteSansClauseNeVoitQueSonOrganisation(): void
    {
        [$alpha, $beta] = $this->deuxOrganisationsAvecEvenements();

        // Hors contexte : vue transverse (catalogue public, administration).
        self::assertCount(2, $this->em->getRepository(Evenement::class)->findAll());

        $this->contexte->etablir($alpha);
        $this->em->clear();

        $evenements = $this->em->getRepository(Evenement::class)->findAll();
        self::assertCount(1, $evenements, 'findAll() doit etre cloisonne sans y penser');
        self::assertSame('Concert Alpha', $evenements[0]->getNom());

        $this->contexte->etablir($beta);
        $this->em->clear();

        $evenements = $this->em->getRepository(Evenement::class)->findAll();
        self::assertCount(1, $evenements);
        self::assertSame('Concert Beta', $evenements[0]->getNom());
    }

    public function testUnEvenementDUneAutreOrganisationEstIntrouvableParSonIdentifiant(): void
    {
        [$alpha, $beta] = $this->deuxOrganisationsAvecEvenements();

        $idBeta = $this->em->getRepository(Evenement::class)->findOneBy(['nom' => 'Concert Beta'])->getId();

        $this->contexte->etablir($alpha);
        $this->em->clear();

        self::assertNull(
            $this->em->getRepository(Evenement::class)->find($idBeta),
            'une lecture par identifiant doit aussi etre cloisonnee'
        );
    }

    public function testLesBilletsEtLesLignesDeCommandeSontCloisonnes(): void
    {
        [$alpha] = $this->deuxOrganisationsAvecEvenements(avecBilletsEtCommandes: true);

        self::assertCount(2, $this->em->getRepository(Billet::class)->findAll());
        self::assertCount(2, $this->em->getRepository(CommandeLigne::class)->findAll());

        $this->contexte->etablir($alpha);
        $this->em->clear();

        self::assertCount(1, $this->em->getRepository(Billet::class)->findAll());
        self::assertCount(1, $this->em->getRepository(CommandeLigne::class)->findAll());
    }

    /**
     * Une commande peut couvrir plusieurs vendeurs : c'est la ligne qui porte
     * le tenant, pas l'enveloppe de paiement.
     */
    public function testUneCommandeMultiVendeursExposeUneSeuleLigneAChaqueOrganisation(): void
    {
        [$alpha, $beta] = $this->deuxOrganisationsAvecEvenements();

        $evAlpha = $this->em->getRepository(Evenement::class)->findOneBy(['nom' => 'Concert Alpha']);
        $evBeta = $this->em->getRepository(Evenement::class)->findOneBy(['nom' => 'Concert Beta']);

        $commande = new Commande();
        $commande->setReference('EVT-MIXTE-001');
        $commande->setMontantTotal(10000.0);
        $commande->setNumeroClient('+23599000001');
        $commande->setMethodePaiement('momo');
        $commande->setStatut(Commande::STATUT_PENDING);
        $commande->setCommissionPlateforme(0.0);
        $commande->setMontantNetOrganisateur(10000.0);
        $commande->setCheckoutEmail('invite@talchif.td');
        $commande->setAccessToken(bin2hex(random_bytes(8)));
        $commande->setDateExpiration(new \DateTimeImmutable('+1 hour'));
        $this->em->persist($commande);

        foreach ([$evAlpha, $evBeta] as $evenement) {
            $ligne = new CommandeLigne();
            $ligne->setCommande($commande);
            $ligne->setEvenement($evenement);
            $ligne->setOrganisation($evenement->getOrganisation());
            $ligne->setQuantite(1);
            $ligne->setPrixUnitaire(5000.0);
            $ligne->setTypeBillet('SIMPLE');
            $this->em->persist($ligne);
        }
        $this->em->flush();

        $this->contexte->etablir($alpha);
        $this->em->clear();
        $lignes = $this->em->getRepository(CommandeLigne::class)->findAll();
        self::assertCount(1, $lignes);
        self::assertSame('Concert Alpha', $lignes[0]->getEvenement()->getNom());

        $this->contexte->etablir($beta);
        $this->em->clear();
        $lignes = $this->em->getRepository(CommandeLigne::class)->findAll();
        self::assertCount(1, $lignes);
        self::assertSame('Concert Beta', $lignes[0]->getEvenement()->getNom());
    }

    public function testLibererLeContexteRendLaVueTransverse(): void
    {
        [$alpha] = $this->deuxOrganisationsAvecEvenements();

        $this->contexte->etablir($alpha);
        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(Evenement::class)->findAll());

        $this->contexte->liberer();
        $this->em->clear();
        self::assertCount(2, $this->em->getRepository(Evenement::class)->findAll());
        self::assertFalse($this->em->getFilters()->isEnabled(TenantFilter::NOM));
    }

    public function testLeTauxDeCommissionPeutEtrePropreAUneOrganisation(): void
    {
        [$alpha, $beta] = $this->deuxOrganisationsAvecEvenements();

        $alpha->setTauxCommission(0.07);
        $this->em->flush();

        $fournisseur = static::getContainer()->get(CommissionRateProvider::class);

        self::assertSame(0.07, $fournisseur->getRateForOrganisation($alpha), 'taux negocie');
        self::assertSame(
            $fournisseur->getRate(),
            $fournisseur->getRateForOrganisation($beta),
            'sans taux propre, on retombe sur celui de la plateforme'
        );
        self::assertSame(
            $fournisseur->getRate(),
            $fournisseur->getRateForOrganisation(null),
            'hors organisation, taux plateforme'
        );
    }

    /**
     * Le role vendeur vient de l'appartenance, plus de la chaine unique.
     */
    public function testUnCompteClientDevientOrganisateurParAppartenance(): void
    {
        $utilisateur = $this->creerUtilisateur('polyvalent@talchif.td', 'CLIENT');

        self::assertContains('ROLE_CLIENT', $utilisateur->getRoles());
        self::assertNotContains('ROLE_ORGANISATEUR', $utilisateur->getRoles());

        static::getContainer()->get(ServiceOrganisation::class)->assurerPour($utilisateur);
        $this->em->clear();

        $relu = $this->em->getRepository(User::class)->find($utilisateur->getId());
        self::assertContains('ROLE_CLIENT', $relu->getRoles(), 'le role plateforme est conserve');
        self::assertContains('ROLE_ORGANISATEUR', $relu->getRoles(), 'l appartenance accorde le role vendeur');
    }

    public function testUneOrganisationNePeutPasPerdreSonDernierProprietaire(): void
    {
        $proprietaire = $this->creerUtilisateur('proprio@talchif.td', 'ORGANISATEUR');
        $service = static::getContainer()->get(ServiceOrganisation::class);
        $organisation = $service->assurerPour($proprietaire);

        $this->expectException(\RuntimeException::class);
        $service->detacher($organisation, $proprietaire);
    }

    public function testUnSecondMembrePeutGererLesEvenementsDeLOrganisation(): void
    {
        $proprietaire = $this->creerUtilisateur('proprio@talchif.td', 'ORGANISATEUR');
        $collegue = $this->creerUtilisateur('collegue@talchif.td', 'CLIENT');

        $service = static::getContainer()->get(ServiceOrganisation::class);
        $organisation = $service->assurerPour($proprietaire);
        $service->rattacher($organisation, $collegue, MembreOrganisation::ROLE_GESTIONNAIRE);
        $this->em->flush();
        $this->em->clear();

        $relu = $this->em->getRepository(User::class)->find($collegue->getId());
        $organisationRelue = $this->em->getRepository(Organisation::class)->find($organisation->getId());

        self::assertSame(
            MembreOrganisation::ROLE_GESTIONNAIRE,
            $relu->roleDansOrganisation($organisationRelue)
        );
        self::assertContains('ROLE_ORGANISATEUR', $relu->getRoles());
    }

    /**
     * @return array{Organisation, Organisation}
     */
    private function deuxOrganisationsAvecEvenements(bool $avecBilletsEtCommandes = false): array
    {
        $organisations = [];

        foreach (['Alpha', 'Beta'] as $nom) {
            $proprietaire = $this->creerUtilisateur(mb_strtolower($nom) . '@talchif.td', 'ORGANISATEUR');

            $organisation = new Organisation();
            $organisation->setNom('Organisation ' . $nom);
            $organisation->setSlug(mb_strtolower($nom));
            $organisation->setActif(true);
            $this->em->persist($organisation);

            $membre = new MembreOrganisation();
            $membre->setOrganisation($organisation);
            $membre->setUtilisateur($proprietaire);
            $membre->setRole(MembreOrganisation::ROLE_PROPRIETAIRE);
            $this->em->persist($membre);

            $evenement = new Evenement();
            $evenement->setNom('Concert ' . $nom);
            $evenement->setSlug('concert-' . mb_strtolower($nom));
            $evenement->setDescription('Jeu de test');
            $evenement->setDateEvenement(new \DateTimeImmutable('+30 days'));
            $evenement->setLieu('Salle');
            $evenement->setAdresse('1 rue du test');
            $evenement->setVille('Ndjamena');
            $evenement->setPlacesDisponibles(50);
            $evenement->setPlacesVendues(0);
            $evenement->setPrixSimple(5000.0);
            $evenement->setIsActive(true);
            $evenement->setIsValide(true);
            $evenement->setOrganisateur($proprietaire);
            $evenement->setOrganisation($organisation);
            $this->em->persist($evenement);

            if ($avecBilletsEtCommandes) {
                $billet = new Billet();
                $billet->setQrCode('QR-' . mb_strtoupper($nom));
                $billet->setType('SIMPLE');
                $billet->setPrix(5000.0);
                $billet->setEvenement($evenement);
                $billet->setClient($proprietaire);
                $billet->setOrganisateur($proprietaire);
                $billet->setOrganisation($organisation);
                $billet->setTransactionId('EVT-' . mb_strtoupper($nom));
                $billet->validerPaiement();
                $this->em->persist($billet);

                $commande = new Commande();
                $commande->setReference('EVT-' . mb_strtoupper($nom) . '-001');
                $commande->setMontantTotal(5000.0);
                $commande->setNumeroClient('+23599000001');
                $commande->setMethodePaiement('momo');
                $commande->setStatut(Commande::STATUT_PENDING);
                $commande->setCommissionPlateforme(0.0);
                $commande->setMontantNetOrganisateur(5000.0);
                $commande->setCheckoutEmail('invite@talchif.td');
                $commande->setAccessToken(bin2hex(random_bytes(8)));
                $commande->setDateExpiration(new \DateTimeImmutable('+1 hour'));
                $this->em->persist($commande);

                $ligne = new CommandeLigne();
                $ligne->setCommande($commande);
                $ligne->setEvenement($evenement);
                $ligne->setOrganisation($organisation);
                $ligne->setQuantite(1);
                $ligne->setPrixUnitaire(5000.0);
                $ligne->setTypeBillet('SIMPLE');
                $this->em->persist($ligne);
            }

            $organisations[] = $organisation;
        }

        $this->em->flush();

        return [$organisations[0], $organisations[1]];
    }

    private function creerUtilisateur(string $email, string $role): User
    {
        $utilisateur = new User();
        $utilisateur->setEmail($email);
        $utilisateur->setNom('Utilisateur Test');
        $utilisateur->setRole($role);
        $utilisateur->setActif(true);
        $utilisateur->setIsVerified(true);
        $utilisateur->setPassword('hachage-factice');
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }
}
