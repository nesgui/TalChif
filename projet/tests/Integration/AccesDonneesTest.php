<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Command\ExpirerCommandesCommand;
use App\Application\Handler\ExpirerCommandesHandler;
use App\Application\Handler\ObtenirMesBilletsHandler;
use App\Application\Query\ObtenirMesBilletsQuery;
use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Evenement;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Service\Notification\BilletEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Couvre les acces aux donnees reconcilies lors du retrait des ports.
 *
 * Chaque cas verifie une requete qui vivait auparavant dans un adapter, et dont
 * la signature ou le filtre divergeait de la version du repository Doctrine.
 */
final class AccesDonneesTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement(
            'TRUNCATE billet, commande_ligne, commande, log_securite, evenement, "user" RESTART IDENTITY CASCADE'
        );
    }

    /**
     * L'ancien adapter filtrait sur le statut « Pending » alors que la constante
     * vaut « Pending Payment » : aucune commande n'expirait jamais.
     */
    public function testLesCommandesEnAttenteDepasseesSontExpirees(): void
    {
        $evenement = $this->creerEvenement();

        $depassee = $this->creerCommande('EVT-EXP-0001', $evenement, new \DateTimeImmutable('-1 hour'));
        $encoreValide = $this->creerCommande('EVT-EXP-0002', $evenement, new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        $nombre = static::getContainer()
            ->get(ExpirerCommandesHandler::class)
            ->handle(new ExpirerCommandesCommand());

        self::assertSame(1, $nombre, 'seule la commande depassee doit expirer');

        $this->em->refresh($depassee);
        $this->em->refresh($encoreValide);

        self::assertTrue($depassee->isExpired());
        self::assertTrue($encoreValide->isPending());
    }

    public function testFindToExpireAccepteUneDateLimite(): void
    {
        $evenement = $this->creerEvenement();
        $this->creerCommande('EVT-EXP-0003', $evenement, new \DateTimeImmutable('+2 hours'));
        $this->em->flush();

        $repository = static::getContainer()->get(CommandeRepository::class);

        self::assertCount(0, $repository->findToExpire(new \DateTimeImmutable('+1 hour')));
        self::assertCount(1, $repository->findToExpire(new \DateTimeImmutable('+3 hours')));
    }

    /**
     * findByClientId remplace le findByUser de l'adapter.
     */
    public function testObtenirMesBilletsNeRenvoieQueLesBilletsDuClient(): void
    {
        $evenement = $this->creerEvenement();
        $clientA = $this->creerClient('a@talchif.td');
        $clientB = $this->creerClient('b@talchif.td');

        $this->creerBillet($evenement, $clientA, 'QR-A-1');
        $this->creerBillet($evenement, $clientA, 'QR-A-2');
        $this->creerBillet($evenement, $clientB, 'QR-B-1');
        $this->em->flush();

        $billets = static::getContainer()
            ->get(ObtenirMesBilletsHandler::class)
            ->handle(new ObtenirMesBilletsQuery(userId: $clientA->getId()));

        self::assertCount(2, $billets);
        foreach ($billets as $billet) {
            self::assertSame($clientA->getId(), $billet->getClient()?->getId());
        }
    }

    /**
     * L'email de confirmation appelait findByTransactionId(), absent du port, et
     * generait l'URL du billet avec « qrCode » la ou la route attend « id ».
     * Les deux echecs etaient avales par le catch de l'appelant.
     */
    public function testEmailDeConfirmationSeConstruitSansErreur(): void
    {
        $evenement = $this->creerEvenement();
        $client = $this->creerClient('destinataire@talchif.td');
        $commande = $this->creerCommande('EVT-MAIL-0001', $evenement, new \DateTimeImmutable('+1 hour'));
        $commande->setClient($client);
        $this->creerBillet($evenement, $client, 'QR-MAIL-1', 'EVT-MAIL-0001');
        $this->em->flush();

        static::getContainer()->get(BilletEmailService::class)->envoyerConfirmationAchat($commande);

        $this->expectNotToPerformAssertions();
    }

    private function creerEvenement(): Evenement
    {
        $organisateur = $this->creerClient('organisateur@talchif.td', 'ORGANISATEUR');

        $evenement = new Evenement();
        $evenement->setNom('Evenement de test');
        $evenement->setSlug('evenement-de-test');
        $evenement->setDescription('Jeu de test');
        $evenement->setDateEvenement(new \DateTimeImmutable('+30 days'));
        $evenement->setLieu('Salle de test');
        $evenement->setAdresse('1 rue du test');
        $evenement->setVille('Ndjamena');
        $evenement->setPlacesDisponibles(100);
        $evenement->setPlacesVendues(0);
        $evenement->setPrixSimple(5000.0);
        $evenement->setIsActive(true);
        $evenement->setIsValide(true);
        $evenement->setOrganisateur($organisateur);
        $this->em->persist($evenement);

        return $evenement;
    }

    private function creerClient(string $email, string $role = 'CLIENT'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setNom('Utilisateur Test');
        $user->setRole($role);
        $user->setActif(true);
        $user->setIsVerified(true);
        $user->setPassword('hachage-factice');
        $this->em->persist($user);

        return $user;
    }

    private function creerCommande(string $reference, Evenement $evenement, \DateTimeImmutable $expiration): Commande
    {
        $commande = new Commande();
        $commande->setReference($reference);
        $commande->setMontantTotal(5000.0);
        $commande->setNumeroClient('+23563519678');
        $commande->setMethodePaiement('momo');
        $commande->setStatut(Commande::STATUT_PENDING);
        $commande->setCommissionPlateforme(0.0);
        $commande->setMontantNetOrganisateur(5000.0);
        $commande->setClient(null);
        $commande->setCheckoutEmail('invite@talchif.td');
        $commande->setAccessToken(bin2hex(random_bytes(8)));
        $commande->setDateExpiration($expiration);
        $this->em->persist($commande);

        $ligne = new CommandeLigne();
        $ligne->setCommande($commande);
        $ligne->setEvenement($evenement);
        $ligne->setQuantite(1);
        $ligne->setPrixUnitaire(5000.0);
        $ligne->setTypeBillet('SIMPLE');
        $this->em->persist($ligne);

        return $commande;
    }

    private function creerBillet(Evenement $evenement, User $client, string $qrCode, ?string $transactionId = null): Billet
    {
        $billet = new Billet();
        $billet->setQrCode($qrCode);
        $billet->setType('SIMPLE');
        $billet->setPrix(5000.0);
        $billet->setEvenement($evenement);
        $billet->setClient($client);
        $billet->setOrganisateur($evenement->getOrganisateur());
        $billet->setTransactionId($transactionId ?? 'EVT-TEST-0000');
        $billet->validerPaiement();
        $this->em->persist($billet);

        return $billet;
    }
}
