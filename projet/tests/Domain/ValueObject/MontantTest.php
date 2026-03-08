<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\Exception\MontantNegatifException;
use App\Domain\ValueObject\Montant;
use PHPUnit\Framework\TestCase;

final class MontantTest extends TestCase
{
    public function testCreerMontantValide(): void
    {
        $montant = Montant::fromFloat(5000.0);
        
        $this->assertEquals(5000.0, $montant->toFloat());
    }

    public function testCreerMontantZero(): void
    {
        $montant = Montant::zero();
        
        $this->assertEquals(0.0, $montant->toFloat());
    }

    public function testMontantNegatif(): void
    {
        $this->expectException(MontantNegatifException::class);
        $this->expectExceptionMessage("ne peut pas être négatif");
        
        Montant::fromFloat(-100.0);
    }

    public function testAddition(): void
    {
        $montant1 = Montant::fromFloat(1000.0);
        $montant2 = Montant::fromFloat(500.0);
        
        $resultat = $montant1->ajouter($montant2);
        
        $this->assertEquals(1500.0, $resultat->toFloat());
    }

    public function testSoustraction(): void
    {
        $montant1 = Montant::fromFloat(1000.0);
        $montant2 = Montant::fromFloat(300.0);
        
        $resultat = $montant1->soustraire($montant2);
        
        $this->assertEquals(700.0, $resultat->toFloat());
    }

    public function testSoustractionResultatNegatif(): void
    {
        $montant1 = Montant::fromFloat(100.0);
        $montant2 = Montant::fromFloat(300.0);
        
        $this->expectException(MontantNegatifException::class);
        
        $montant1->soustraire($montant2);
    }

    public function testMultiplication(): void
    {
        $montant = Montant::fromFloat(500.0);
        
        $resultat = $montant->multiplier(3);
        
        $this->assertEquals(1500.0, $resultat->toFloat());
    }

    public function testPourcentage(): void
    {
        $montant = Montant::fromFloat(10000.0);
        
        $commission = $montant->appliquerPourcentage(10);
        
        $this->assertEquals(1000.0, $commission->toFloat());
    }

    public function testComparaison(): void
    {
        $montant1 = Montant::fromFloat(1000.0);
        $montant2 = Montant::fromFloat(500.0);
        $montant3 = Montant::fromFloat(1000.0);
        
        $this->assertTrue($montant1->estSuperieurA($montant2));
        $this->assertFalse($montant2->estSuperieurA($montant1));
        $this->assertTrue($montant1->estEgalA($montant3));
    }

    public function testEstPositif(): void
    {
        $montantPositif = Montant::fromFloat(100.0);
        $montantZero = Montant::zero();
        
        $this->assertTrue($montantPositif->estPositif());
        $this->assertFalse($montantZero->estPositif());
    }

    public function testArrondi(): void
    {
        $montant = Montant::fromFloat(123.456789);
        
        $this->assertEquals(123.46, $montant->toFloat());
    }

    public function testToString(): void
    {
        $montant = Montant::fromFloat(5000.0);
        
        $this->assertEquals('5 000.00 XAF', (string) $montant);
    }

    public function testImmutabilite(): void
    {
        $montant1 = Montant::fromFloat(1000.0);
        $montant2 = $montant1->ajouter(Montant::fromFloat(500.0));
        
        // L'original ne doit pas être modifié
        $this->assertEquals(1000.0, $montant1->toFloat());
        $this->assertEquals(1500.0, $montant2->toFloat());
    }
}
