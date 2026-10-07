<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Socle des tests fonctionnels : base vide, fabriques de donnees, connexion.
 *
 * Chaque test part d'un schema truque pour rester independant de l'ordre
 * d'execution.
 */
abstract class CasFonctionnel extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->catchExceptions(false);

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement(
            'TRUNCATE billet, commande_ligne, commande, log_securite, app_setting, ticket_design, evenement, "user" RESTART IDENTITY CASCADE'
        );
    }

    protected function creerUtilisateur(
        string $email,
        string $role = 'CLIENT',
        bool $compteCheckout = false,
        string $motDePasse = 'MotDePasse1!'
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setNom('Utilisateur Test');
        $user->setTelephone('+23599000000');
        $user->setRole($role);
        $user->setActif(true);
        $user->setIsVerified(true);
        $user->setCheckoutAccount($compteCheckout);
        $user->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $motDePasse)
        );
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function creerEvenement(
        User $organisateur,
        string $slug = 'concert-de-test',
        int $places = 50,
        float $prix = 5000.0,
        bool $actif = true,
        string $date = '+30 days',
        string $nom = 'Concert de test'
    ): Evenement {
        $evenement = new Evenement();
        $evenement->setNom($nom);
        $evenement->setSlug($slug);
        $evenement->setDescription('Evenement cree pour les tests fonctionnels.');
        $evenement->setDateEvenement(new \DateTimeImmutable($date));
        $evenement->setLieu('Salle de test');
        $evenement->setAdresse('1 rue du test');
        $evenement->setVille('Ndjamena');
        $evenement->setPlacesDisponibles($places);
        $evenement->setPlacesVendues(0);
        $evenement->setPrixSimple($prix);
        $evenement->setIsActive($actif);
        $evenement->setIsValide(true);
        $evenement->setOrganisateur($organisateur);
        $this->em->persist($evenement);
        $this->em->flush();

        return $evenement;
    }

    /**
     * Commande en attente de paiement, telle que la cree le checkout invite.
     */
    protected function creerCommandeEnAttente(
        Evenement $evenement,
        string $reference = 'EVT-1000-AAAA',
        int $quantite = 2,
        string $emailCheckout = 'invite@talchif.td',
        string $numero = '+23599000001',
        ?string $referenceTransactionClient = 'MP260101.1200.A12345'
    ): Commande {
        $commande = new Commande();
        $commande->setReference($reference);
        $commande->setMontantTotal($evenement->getPrixSimple() * $quantite);
        $commande->setNumeroClient($numero);
        $commande->setMethodePaiement('momo');
        $commande->setStatut(Commande::STATUT_PENDING);
        $commande->setCommissionPlateforme(0.0);
        $commande->setMontantNetOrganisateur($evenement->getPrixSimple() * $quantite);
        $commande->setClient(null);
        $commande->setCheckoutEmail($emailCheckout);
        // Une commande n'apparait dans la file de l'organisateur qu'une fois la
        // preuve de paiement soumise.
        if ($referenceTransactionClient !== null) {
            $commande->setReferenceTransactionClient($referenceTransactionClient);
            $commande->setStatut(Commande::STATUT_PROCESSING);
        }
        $commande->setAccessToken(bin2hex(random_bytes(24)));
        $commande->setDateExpiration(new \DateTimeImmutable('+1 hour'));
        $this->em->persist($commande);

        $ligne = new CommandeLigne();
        $ligne->setCommande($commande);
        $ligne->setEvenement($evenement);
        $ligne->setQuantite($quantite);
        $ligne->setPrixUnitaire($evenement->getPrixSimple());
        $ligne->setTypeBillet('SIMPLE');
        $this->em->persist($ligne);

        $this->em->flush();

        return $commande;
    }

    protected function creerBillet(Evenement $evenement, User $client, string $qrCode): Billet
    {
        $billet = new Billet();
        $billet->setQrCode($qrCode);
        $billet->setType('SIMPLE');
        $billet->setPrix($evenement->getPrixSimple());
        $billet->setEvenement($evenement);
        $billet->setClient($client);
        $billet->setOrganisateur($evenement->getOrganisateur());
        $billet->setTransactionId('EVT-TEST-0000');
        $billet->validerPaiement();
        $this->em->persist($billet);
        $this->em->flush();

        return $billet;
    }

    /**
     * Lit un jeton CSVF dans le formulaire rendu par la page.
     *
     * Les jetons sont extraits du HTML plutot que generes hors requete : le test
     * verifie ainsi que le template emet bien le jeton attendu par le controleur.
     */
    protected function jetonDuFormulaire(string $url, string $actionAttendue): string
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful("page {$url} inaccessible");

        $champ = $crawler->filter('form[action="' . $actionAttendue . '"] input[name="_token"]');
        self::assertGreaterThan(
            0,
            $champ->count(),
            "aucun jeton CSRF dans le formulaire vers {$actionAttendue} sur {$url}"
        );

        return (string) $champ->first()->attr('value');
    }

    /**
     * Lit un jeton CSRF depuis une constante JavaScript de la page.
     */
    protected function jetonDuScript(string $url, string $variable): string
    {
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful("page {$url} inaccessible");

        $motif = '/const\s+' . preg_quote($variable, '/') . '\s*=\s*"([^"]+)"/';
        self::assertMatchesRegularExpression(
            $motif,
            (string) $this->client->getResponse()->getContent(),
            "constante {$variable} absente de {$url}"
        );

        preg_match($motif, (string) $this->client->getResponse()->getContent(), $trouve);

        return $trouve[1];
    }
}
