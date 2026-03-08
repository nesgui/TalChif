<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Command\AcheterBilletsCommand;
use App\Application\Handler\AcheterBilletsHandler;
use App\Domain\Exception\PlacesInsuffisantesException;
use App\Domain\Repository\BilletRepositoryInterface;
use App\Domain\Repository\EvenementRepositoryInterface;
use App\Entity\Evenement;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Payment\PaymentInterface;
use App\Service\Payment\PaymentResult;
use App\Service\Ticket\TicketRenderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AcheterBilletsHandlerTest extends TestCase
{
    private function creerEvenement(int $placesDisponibles = 100): Evenement
    {
        $evenement = new Evenement();
        $evenement->setNom('Concert Test');
        $evenement->setPlacesDisponibles($placesDisponibles);
        $evenement->setPlacesVendues(0);
        $evenement->setIsActive(true);
        $evenement->setPrixSimple(5000.0);
        
        $organisateur = new User();
        $organisateur->setEmail('orga@test.com');
        $organisateur->setNom('Organisateur');
        $evenement->setOrganisateur($organisateur);
        
        return $evenement;
    }

    private function creerUser(): User
    {
        $user = new User();
        $user->setEmail('client@test.com');
        $user->setNom('Client Test');
        
        return $user;
    }

    public function testAcheterBilletsAvecSucces(): void
    {
        // Arrange
        $evenement = $this->creerEvenement(100);
        $user = $this->creerUser();
        
        $evenementRepoMock = $this->createMock(EvenementRepositoryInterface::class);
        $evenementRepoMock->method('findByIdWithLock')->willReturn($evenement);
        
        $billetRepoMock = $this->createMock(BilletRepositoryInterface::class);
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->method('find')->willReturn($user);
        
        $paymentMock = $this->createMock(PaymentInterface::class);
        $paymentMock->method('supports')->willReturn(true);
        $paymentMock->method('payer')->willReturn(
            PaymentResult::succes('TXN_123', 'Paiement réussi')
        );
        
        $ticketRenderMock = $this->createMock(TicketRenderService::class);
        $ticketRenderMock->method('renderAndStoreBilletPng')->willReturn('/images/billet.png');
        
        $emMock = $this->createMock(EntityManagerInterface::class);
        
        $handler = new AcheterBilletsHandler(
            $evenementRepoMock,
            $billetRepoMock,
            $userRepoMock,
            $paymentMock,
            $ticketRenderMock,
            $emMock
        );
        
        $command = new AcheterBilletsCommand(
            userId: 1,
            panier: [1 => 2],
            methodePaiement: 'MOMO',
            telephone: '235 12 34 56 78'
        );
        
        // Act
        $resultat = $handler->handle($command);
        
        // Assert
        $this->assertEquals('TXN_123', $resultat->transactionId);
        $this->assertEquals(2, $evenement->getPlacesVendues());
    }

    public function testAcheterBilletsPlacesInsuffisantes(): void
    {
        // Arrange
        $evenement = $this->creerEvenement(5);
        $user = $this->creerUser();
        
        $evenementRepoMock = $this->createMock(EvenementRepositoryInterface::class);
        $evenementRepoMock->method('findByIdWithLock')->willReturn($evenement);
        
        $billetRepoMock = $this->createMock(BilletRepositoryInterface::class);
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->method('find')->willReturn($user);
        
        $paymentMock = $this->createMock(PaymentInterface::class);
        $paymentMock->method('supports')->willReturn(true);
        
        $ticketRenderMock = $this->createMock(TicketRenderService::class);
        $emMock = $this->createMock(EntityManagerInterface::class);
        
        $handler = new AcheterBilletsHandler(
            $evenementRepoMock,
            $billetRepoMock,
            $userRepoMock,
            $paymentMock,
            $ticketRenderMock,
            $emMock
        );
        
        $command = new AcheterBilletsCommand(
            userId: 1,
            panier: [1 => 10],
            methodePaiement: 'MOMO',
            telephone: '235 12 34 56 78'
        );
        
        // Assert
        $this->expectException(PlacesInsuffisantesException::class);
        
        // Act
        $handler->handle($command);
    }

    public function testAcheterBilletsTelephoneInvalide(): void
    {
        $user = $this->creerUser();
        
        $evenementRepoMock = $this->createMock(EvenementRepositoryInterface::class);
        $billetRepoMock = $this->createMock(BilletRepositoryInterface::class);
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->method('find')->willReturn($user);
        $paymentMock = $this->createMock(PaymentInterface::class);
        $ticketRenderMock = $this->createMock(TicketRenderService::class);
        $emMock = $this->createMock(EntityManagerInterface::class);
        
        $handler = new AcheterBilletsHandler(
            $evenementRepoMock,
            $billetRepoMock,
            $userRepoMock,
            $paymentMock,
            $ticketRenderMock,
            $emMock
        );
        
        $command = new AcheterBilletsCommand(
            userId: 1,
            panier: [1 => 2],
            methodePaiement: 'MOMO',
            telephone: 'invalide'
        );
        
        $this->expectException(\DomainException::class);
        
        $handler->handle($command);
    }
}
