<?php

declare(strict_types=1);

namespace App\Tests\Domain\Entity;

use App\Entity\Billet;
use App\Entity\User;
use App\Entity\Evenement;
use PHPUnit\Framework\TestCase;

final class BilletTest extends TestCase
{
    private function creerBillet(bool $valide = true, bool $paye = true, bool $utilise = false): Billet
    {
        $billet = new Billet();
        $billet->setQrCode('TEST_' . uniqid());
        $billet->setIsValide($valide);
        $billet->setStatutPaiement($paye ? 'PAYE' : 'EN_ATTENTE');
        $billet->setIsUtilise($utilise);
        
        return $billet;
    }

    private function creerUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setNom('Test User');
        
        return $user;
    }

    public function testUtiliserBilletValide(): void
    {
        $billet = $this->creerBillet();
        $validateur = $this->creerUser();
        
        $billet->utiliser($validateur);
        
        $this->assertTrue($billet->isUtilise());
        $this->assertNotNull($billet->getDateUtilisation());
        $this->assertEquals($validateur, $billet->getValidePar());
    }

    public function testUtiliserBilletDejaUtilise(): void
    {
        $billet = $this->creerBillet(utilise: true);
        $validateur = $this->creerUser();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ne peut pas être utilisé');
        
        $billet->utiliser($validateur);
    }

    public function testUtiliserBilletInvalide(): void
    {
        $billet = $this->creerBillet(valide: false);
        $validateur = $this->creerUser();
        
        $this->expectException(\RuntimeException::class);
        
        $billet->utiliser($validateur);
    }

    public function testUtiliserBilletNonPaye(): void
    {
        $billet = $this->creerBillet(paye: false);
        $validateur = $this->creerUser();
        
        $this->expectException(\RuntimeException::class);
        
        $billet->utiliser($validateur);
    }

    public function testEstUtilisable(): void
    {
        $billetUtilisable = $this->creerBillet();
        $billetInvalide = $this->creerBillet(valide: false);
        $billetUtilise = $this->creerBillet(utilise: true);
        $billetNonPaye = $this->creerBillet(paye: false);
        
        $this->assertTrue($billetUtilisable->estUtilisable());
        $this->assertFalse($billetInvalide->estUtilisable());
        $this->assertFalse($billetUtilise->estUtilisable());
        $this->assertFalse($billetNonPaye->estUtilisable());
    }

    public function testInvalider(): void
    {
        $billet = $this->creerBillet();
        
        $billet->invalider('Fraude détectée');
        
        $this->assertFalse($billet->isValide());
    }

    public function testInvaliderBilletDejaUtilise(): void
    {
        $billet = $this->creerBillet(utilise: true);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('déjà utilisé');
        
        $billet->invalider();
    }

    public function testRembourser(): void
    {
        $billet = $this->creerBillet();
        
        $billet->rembourser();
        
        $this->assertEquals('REMBOURSE', $billet->getStatutPaiement());
        $this->assertFalse($billet->isValide());
    }

    public function testRembourserBilletDejaUtilise(): void
    {
        $billet = $this->creerBillet(utilise: true);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('déjà utilisé');
        
        $billet->rembourser();
    }

    public function testGetStatutUtilisation(): void
    {
        $billetValide = $this->creerBillet();
        $billetInvalide = $this->creerBillet(valide: false);
        $billetUtilise = $this->creerBillet(utilise: true);
        $billetEnAttente = $this->creerBillet(paye: false);
        
        $this->assertEquals('Valide', $billetValide->getStatutUtilisation());
        $this->assertEquals('Invalide', $billetInvalide->getStatutUtilisation());
        $this->assertEquals('Utilisé', $billetUtilise->getStatutUtilisation());
        $this->assertEquals('Paiement en attente', $billetEnAttente->getStatutUtilisation());
    }

    public function testAppartientA(): void
    {
        $billet = $this->creerBillet();
        $user1 = $this->creerUser();
        $user2 = $this->creerUser();
        
        $billet->setClient($user1);
        
        $this->assertTrue($billet->appartientA($user1));
        $this->assertFalse($billet->appartientA($user2));
    }

    public function testEstPourEvenement(): void
    {
        $billet = $this->creerBillet();
        $evenement1 = new Evenement();
        $evenement2 = new Evenement();
        
        $billet->setEvenement($evenement1);
        
        $this->assertTrue($billet->estPourEvenement($evenement1));
        $this->assertFalse($billet->estPourEvenement($evenement2));
    }
}
