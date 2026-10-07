<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Entity\MembreOrganisation;
use App\Entity\Organisation;
use App\Entity\Plan;
use App\Entity\User;
use App\Repository\PlanRepository;
use App\Service\Quota\QuotaDepasseException;
use App\Service\Quota\ServiceQuotas;
use App\Service\Tenant\ServiceOrganisation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Quotas bloquants.
 *
 * Une limite atteinte refuse l'action : aucune facturation a l'usage, aucune
 * degradation silencieuse. Les trois quotas sont verifies avant ecriture.
 */
final class QuotasTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ServiceQuotas $quotas;

    protected function setUp(): void
    {
        self::bootKernel();
        $conteneur = static::getContainer();

        $this->em = $conteneur->get(EntityManagerInterface::class);
        $this->quotas = $conteneur->get(ServiceQuotas::class);

        $this->em->getConnection()->executeStatement(
            'TRUNCATE billet, commande_ligne, commande, log_securite, app_setting, ticket_design, evenement, membre_organisation, organisation, utilisateur RESTART IDENTITY CASCADE'
        );
    }

    public function testLesOffresDeDepartSontEnBase(): void
    {
        $repository = static::getContainer()->get(PlanRepository::class);

        foreach (['DECOUVERTE', 'PRO', 'ILLIMITE'] as $code) {
            self::assertInstanceOf(Plan::class, $repository->findParCode($code), "offre {$code} absente");
        }

        self::assertTrue($repository->findParCode('ILLIMITE')->estIllimite());
    }

    public function testUneOrganisationSansPlanRetombeSurLOffreParDefaut(): void
    {
        $organisation = $this->creerOrganisation(null);

        self::assertSame(Plan::CODE_DECOUVERTE, $this->quotas->planDe($organisation)?->getCode());
    }

    public function testLeQuotaDEvenementsActifsBloqueAuDela(): void
    {
        // Decouverte : 1 evenement actif.
        $organisation = $this->creerOrganisation('DECOUVERTE');
        $proprietaire = $this->proprietaireDe($organisation);

        // Rien encore : autorise.
        $this->quotas->verifierCreationEvenement($organisation);

        $this->creerEvenement($organisation, $proprietaire, 'ev-1', actif: true);

        try {
            $this->quotas->verifierCreationEvenement($organisation);
            self::fail('la creation d un second evenement actif devait etre refusee');
        } catch (QuotaDepasseException $e) {
            self::assertSame(ServiceQuotas::QUOTA_EVENEMENTS, $e->quota);
            self::assertSame(1, $e->consomme);
            self::assertSame(1, $e->limite);
            self::assertStringContainsString('1 evenement', $e->getMessage());
        }
    }

    public function testUnEvenementDesactiveLibereLeQuota(): void
    {
        $organisation = $this->creerOrganisation('DECOUVERTE');
        $proprietaire = $this->proprietaireDe($organisation);
        $evenement = $this->creerEvenement($organisation, $proprietaire, 'ev-1', actif: true);

        $evenement->setIsActive(false);
        $this->em->flush();

        // Le quota porte sur les evenements actifs : la place est rendue.
        $this->quotas->verifierCreationEvenement($organisation);
        $this->expectNotToPerformAssertions();
    }

    public function testLeQuotaDeBilletsCompteLeMoisCourantEtLaQuantiteDemandee(): void
    {
        $organisation = $this->creerOrganisation('DECOUVERTE'); // 100 billets / mois
        $proprietaire = $this->proprietaireDe($organisation);
        $evenement = $this->creerEvenement($organisation, $proprietaire, 'ev-1', actif: true);

        $this->creerBillets($organisation, $evenement, $proprietaire, 98);

        // 98 + 2 = 100 : a la limite, autorise.
        $this->quotas->verifierEmissionBillets($organisation, 2);

        try {
            // 98 + 3 = 101 : refuse.
            $this->quotas->verifierEmissionBillets($organisation, 3);
            self::fail('le depassement du quota mensuel devait etre refuse');
        } catch (QuotaDepasseException $e) {
            self::assertSame(ServiceQuotas::QUOTA_BILLETS, $e->quota);
            self::assertSame(98, $e->consomme);
            self::assertSame(100, $e->limite);
        }
    }

    public function testLesBilletsDuMoisPrecedentNeConsommentPasLeQuotaCourant(): void
    {
        $organisation = $this->creerOrganisation('DECOUVERTE');
        $proprietaire = $this->proprietaireDe($organisation);
        $evenement = $this->creerEvenement($organisation, $proprietaire, 'ev-1', actif: true);

        $this->creerBillets($organisation, $evenement, $proprietaire, 100);

        // On repousse ces billets au mois precedent.
        $this->em->getConnection()->executeStatement(
            "UPDATE billet SET created_at = :date",
            ['date' => (new \DateTimeImmutable('first day of last month'))->format('Y-m-d H:i:s')]
        );
        $this->em->clear();

        $organisation = $this->em->getRepository(Organisation::class)->findOneBy(['slug' => 'org-quota']);

        $this->quotas->verifierEmissionBillets($organisation, 100);
        $this->expectNotToPerformAssertions();
    }

    public function testLeQuotaDeMembresBloqueAuDela(): void
    {
        // Decouverte : 2 membres.
        $organisation = $this->creerOrganisation('DECOUVERTE');
        $this->proprietaireDe($organisation);

        $service = static::getContainer()->get(ServiceOrganisation::class);
        $second = $this->creerUtilisateur('second@talchif.td');
        $service->rattacher($organisation, $second, MembreOrganisation::ROLE_GESTIONNAIRE);
        $this->em->flush();

        try {
            $this->quotas->verifierAjoutMembre($organisation);
            self::fail('un troisieme membre devait etre refuse');
        } catch (QuotaDepasseException $e) {
            self::assertSame(ServiceQuotas::QUOTA_MEMBRES, $e->quota);
            self::assertSame(2, $e->consomme);
            self::assertSame(2, $e->limite);
        }
    }

    public function testUneOffreIllimiteeNeBloqueJamais(): void
    {
        $organisation = $this->creerOrganisation('ILLIMITE');
        $proprietaire = $this->proprietaireDe($organisation);

        for ($i = 0; $i < 3; $i++) {
            $this->creerEvenement($organisation, $proprietaire, 'ev-' . $i, actif: true);
        }

        $this->quotas->verifierCreationEvenement($organisation);
        $this->quotas->verifierEmissionBillets($organisation, 10_000);
        $this->quotas->verifierAjoutMembre($organisation);

        $this->expectNotToPerformAssertions();
    }

    public function testLaConsommationEstExposeePourAffichage(): void
    {
        $organisation = $this->creerOrganisation('PRO');
        $proprietaire = $this->proprietaireDe($organisation);
        $evenement = $this->creerEvenement($organisation, $proprietaire, 'ev-1', actif: true);
        $this->creerBillets($organisation, $evenement, $proprietaire, 5);

        $etat = $this->quotas->consommation($organisation);

        self::assertSame(['consomme' => 1, 'limite' => 10], $etat[ServiceQuotas::QUOTA_EVENEMENTS]);
        self::assertSame(['consomme' => 5, 'limite' => 5000], $etat[ServiceQuotas::QUOTA_BILLETS]);
        self::assertSame(['consomme' => 1, 'limite' => 10], $etat[ServiceQuotas::QUOTA_MEMBRES]);
    }

    private function creerOrganisation(?string $codePlan): Organisation
    {
        $organisation = new Organisation();
        $organisation->setNom('Organisation Quota');
        $organisation->setSlug('org-quota');
        $organisation->setActif(true);

        if ($codePlan !== null) {
            $organisation->setPlan(static::getContainer()->get(PlanRepository::class)->findParCode($codePlan));
        }

        $this->em->persist($organisation);
        $this->em->flush();

        return $organisation;
    }

    private function proprietaireDe(Organisation $organisation): User
    {
        $utilisateur = $this->creerUtilisateur('proprio@talchif.td');

        $membre = new MembreOrganisation();
        $membre->setOrganisation($organisation);
        $membre->setUtilisateur($utilisateur);
        $membre->setRole(MembreOrganisation::ROLE_PROPRIETAIRE);
        $this->em->persist($membre);
        $this->em->flush();

        return $utilisateur;
    }

    private function creerUtilisateur(string $email): User
    {
        $utilisateur = new User();
        $utilisateur->setEmail($email);
        $utilisateur->setNom('Utilisateur Test');
        $utilisateur->setRole('ORGANISATEUR');
        $utilisateur->setActif(true);
        $utilisateur->setIsVerified(true);
        $utilisateur->setPassword('hachage-factice');
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    private function creerEvenement(Organisation $organisation, User $organisateur, string $slug, bool $actif): Evenement
    {
        $evenement = new Evenement();
        $evenement->setNom('Concert ' . $slug);
        $evenement->setSlug($slug);
        $evenement->setDescription('Jeu de test');
        $evenement->setDateEvenement(new \DateTimeImmutable('+30 days'));
        $evenement->setLieu('Salle');
        $evenement->setAdresse('1 rue du test');
        $evenement->setVille('Ndjamena');
        $evenement->setPlacesDisponibles(10_000);
        $evenement->setPlacesVendues(0);
        $evenement->setPrixSimple(5000.0);
        $evenement->setIsActive($actif);
        $evenement->setIsValide(true);
        $evenement->setOrganisateur($organisateur);
        $evenement->setOrganisation($organisation);
        $this->em->persist($evenement);
        $this->em->flush();

        return $evenement;
    }

    private function creerBillets(Organisation $organisation, Evenement $evenement, User $client, int $nombre): void
    {
        for ($i = 0; $i < $nombre; $i++) {
            $billet = new Billet();
            $billet->setQrCode('QR-' . $i . '-' . bin2hex(random_bytes(4)));
            $billet->setType('SIMPLE');
            $billet->setPrix(5000.0);
            $billet->setEvenement($evenement);
            $billet->setClient($client);
            $billet->setOrganisateur($client);
            $billet->setOrganisation($organisation);
            $billet->setTransactionId('EVT-QUOTA');
            $billet->validerPaiement();
            $this->em->persist($billet);
        }

        $this->em->flush();
    }
}
