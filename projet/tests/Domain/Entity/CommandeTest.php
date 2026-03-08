<?php

declare(strict_types=1);

namespace App\Tests\Domain\Entity;

use App\Entity\Commande;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class CommandeTest extends TestCase
{
    private function creerCommande(string $statut = 'Pending', bool $expiree = false): Commande
    {
        $commande = new Commande();
        $commande->setReference('EVT-TEST-1234');
        $commande->setStatut($statut);
        
        if ($expiree) {
            $commande->setDateExpiration(new \DateTimeImmutable('-1 hour'));
        } else {
            $commande->setDateExpiration(new \DateTimeImmutable('+10 minutes'));
        }
        
        return $commande;
    }

    private function creerUser(): User
    {
        $user = new User();
        $user->setEmail('admin@example.com');
        $user->setNom('Admin');
        
        return $user;
    }

    public function testMarquerPayeeAvecSucces(): void
    {
        $commande = $this->creerCommande();
        $validateur = $this->creerUser();
        
        $commande->marquerPayee($validateur);
        
        $this->assertTrue($commande->isPaid());
        $this->assertEquals($validateur, $commande->getValidePar());
        $this->assertNotNull($commande->getDateValidation());
    }

    public function testMarquerPayeeCommandeDejaPayee(): void
    {
        $commande = $this->creerCommande('Paid');
        $validateur = $this->creerUser();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("n'est pas en attente");
        
        $commande->marquerPayee($validateur);
    }

    public function testMarquerPayeeCommandeExpiree(): void
    {
        $commande = $this->creerCommande(expiree: true);
        $validateur = $this->creerUser();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expiré');
        
        $commande->marquerPayee($validateur);
    }

    public function testMarquerExpiree(): void
    {
        $commande = $this->creerCommande();
        
        $commande->marquerExpiree();
        
        $this->assertTrue($commande->isExpired());
    }

    public function testMarquerExpireeCommandePayee(): void
    {
        $commande = $this->creerCommande('Paid');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('en attente peuvent expirer');
        
        $commande->marquerExpiree();
    }

    public function testMarquerRejetee(): void
    {
        $commande = $this->creerCommande();
        $validateur = $this->creerUser();
        
        $commande->marquerRejetee($validateur);
        
        $this->assertTrue($commande->isRejected());
        $this->assertEquals($validateur, $commande->getValidePar());
    }

    public function testMarquerRejeteeCommandePayee(): void
    {
        $commande = $this->creerCommande('Paid');
        $validateur = $this->creerUser();
        
        $this->expectException(\RuntimeException::class);
        
        $commande->marquerRejetee($validateur);
    }

    public function testPeutEtreValidee(): void
    {
        $commandeValide = $this->creerCommande();
        $commandeExpiree = $this->creerCommande(expiree: true);
        $commandePayee = $this->creerCommande('Paid');
        
        $this->assertTrue($commandeValide->peutEtreValidee());
        $this->assertFalse($commandeExpiree->peutEtreValidee());
        $this->assertFalse($commandePayee->peutEtreValidee());
    }

    public function testEstDansDelaiValidation(): void
    {
        $commandeValide = $this->creerCommande();
        $commandeExpiree = $this->creerCommande(expiree: true);
        
        $this->assertTrue($commandeValide->estDansDelaiValidation());
        $this->assertFalse($commandeExpiree->estDansDelaiValidation());
    }

    public function testAnnuler(): void
    {
        $commande = $this->creerCommande();
        
        $commande->annuler();
        
        $this->assertEquals('Cancelled', $commande->getStatut());
    }

    public function testAnnulerCommandePayee(): void
    {
        $commande = $this->creerCommande('Paid');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('déjà payée');
        
        $commande->annuler();
    }

    public function testGetTempsRestantMinutes(): void
    {
        $commande = $this->creerCommande();
        
        $tempsRestant = $commande->getTempsRestantMinutes();
        
        $this->assertNotNull($tempsRestant);
        $this->assertGreaterThan(0, $tempsRestant);
        $this->assertLessThanOrEqual(10, $tempsRestant);
    }

    public function testGetTempsRestantMinutesCommandeExpiree(): void
    {
        $commande = $this->creerCommande(expiree: true);
        
        $this->assertNull($commande->getTempsRestantMinutes());
    }

    public function testEstExpiree(): void
    {
        $commandeValide = $this->creerCommande();
        $commandeExpiree = $this->creerCommande(expiree: true);
        
        $this->assertFalse($commandeValide->estExpiree());
        $this->assertTrue($commandeExpiree->estExpiree());
    }
}
