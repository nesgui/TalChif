<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\Erreur\RapporteurErreurs;
use App\Service\Finance\CalculateurRevenus;
use App\Service\Notification\MessagesFlash;
use App\Service\Notification\MessagesUtilisateurInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Verrouille le comportement des services issus de l'eclatement de
 * ErrorHandlingService.
 *
 * Deux defauts sont couverts ici : les messages montres a l'utilisateur
 * etaient des cles de traduction inexistantes, et logError() ne journalisait
 * rien du tout.
 */
final class RapporteurErreursTest extends KernelTestCase
{
    public function testUneErreurDeBaseDeDonneesEstJournalisee(): void
    {
        self::bootKernel();
        $conteneur = static::getContainer();

        $rapporteur = $conteneur->get(RapporteurErreurs::class);
        $rapporteur->signalerBaseDeDonnees(new \RuntimeException('echec insert'), ['action' => 'test']);

        $journal = $conteneur->get('monolog.logger')->getHandlers();
        self::assertNotEmpty($journal, 'un handler de journalisation doit etre configure');
    }

    public function testLesMessagesUtilisateurNeSontPasDesClesDeTraduction(): void
    {
        self::bootKernel();
        $messages = new MessagesUtilisateurEspion();
        $rapporteur = new RapporteurErreurs($messages, static::getContainer()->get('monolog.logger'));

        $rapporteur->signalerBaseDeDonnees(new \RuntimeException('peu importe'));
        $rapporteur->signalerUpload(new FileException('file size exceeded'));
        $rapporteur->signalerSecurite(new \RuntimeException('identifiants'));

        self::assertCount(3, $messages->erreurs);

        foreach ($messages->erreurs as $message) {
            self::assertDoesNotMatchRegularExpression(
                '/^error\./',
                $message,
                "le message « {$message} » est une cle de traduction, pas un texte lisible"
            );
            self::assertStringNotContainsString('.', substr($message, 0, 20), 'un texte, pas une cle pointee');
        }
    }

    public function testLeMessageDeContrainteUniqueEstExplicite(): void
    {
        self::bootKernel();
        $messages = new MessagesUtilisateurEspion();
        $rapporteur = new RapporteurErreurs($messages, static::getContainer()->get('monolog.logger'));

        // Seul le type importe pour la classification.
        $rapporteur->signalerBaseDeDonnees($this->createStub(UniqueConstraintViolationException::class));

        self::assertSame(['Cet enregistrement existe deja.'], $messages->erreurs);
    }

    public function testDeposerUnFlashSansSessionNeLevePasDException(): void
    {
        self::bootKernel();

        // Hors requete HTTP : le RequestStack n'expose aucune session.
        $messages = static::getContainer()->get(MessagesFlash::class);
        $messages->erreur('message sans session');

        $this->expectNotToPerformAssertions();
    }

    public function testLeCalculateurDeRevenusEstCableSansPasserParLeRepository(): void
    {
        self::bootKernel();

        self::assertInstanceOf(
            CalculateurRevenus::class,
            static::getContainer()->get(CalculateurRevenus::class)
        );

        self::assertFalse(
            method_exists(\App\Repository\BilletRepository::class, 'calculateNetRevenue'),
            'le calcul de commission ne doit plus vivre dans le repository'
        );
    }
}

/**
 * Espion minimal : capture les messages au lieu de les deposer en session.
 */
final class MessagesUtilisateurEspion implements MessagesUtilisateurInterface
{
    /** @var list<string> */
    public array $erreurs = [];

    /** @var list<string> */
    public array $succes = [];

    public function succes(string $message): void
    {
        $this->succes[] = $message;
    }

    public function erreur(string $message): void
    {
        $this->erreurs[] = $message;
    }

    public function avertissement(string $message): void
    {
    }
}
