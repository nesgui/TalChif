<?php

declare(strict_types=1);

namespace App\Tests\Domain\Entity;

use App\Domain\Exception\EvenementInactifException;
use App\Domain\Exception\PlacesInsuffisantesException;
use App\Entity\Evenement;
use PHPUnit\Framework\TestCase;

final class EvenementTest extends TestCase
{
    private function creerEvenement(int $placesDisponibles = 100, bool $actif = true): Evenement
    {
        $evenement = new Evenement();
        $evenement->setNom('Concert Test');
        $evenement->setPlacesDisponibles($placesDisponibles);
        $evenement->setPlacesVendues(0);
        $evenement->setIsActive($actif);
        
        return $evenement;
    }

    public function testReserverPlacesAvecSucces(): void
    {
        $evenement = $this->creerEvenement(100);
        
        $evenement->reserverPlaces(10);
        
        $this->assertEquals(10, $evenement->getPlacesVendues());
        $this->assertEquals(90, $evenement->getPlacesRestantes());
    }

    public function testReserverPlacesMultiples(): void
    {
        $evenement = $this->creerEvenement(100);
        
        $evenement->reserverPlaces(10);
        $evenement->reserverPlaces(20);
        
        $this->assertEquals(30, $evenement->getPlacesVendues());
        $this->assertEquals(70, $evenement->getPlacesRestantes());
    }

    public function testReserverPlacesInsuffisantes(): void
    {
        $evenement = $this->creerEvenement(5);
        
        $this->expectException(PlacesInsuffisantesException::class);
        $this->expectExceptionMessage('Seulement 5 places restantes');
        
        $evenement->reserverPlaces(10);
    }

    public function testReserverPlacesEvenementInactif(): void
    {
        $evenement = $this->creerEvenement(100, false);
        
        $this->expectException(EvenementInactifException::class);
        $this->expectExceptionMessage("n'est plus actif");
        
        $evenement->reserverPlaces(10);
    }

    public function testReserverPlacesQuantiteNegative(): void
    {
        $evenement = $this->creerEvenement(100);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('supérieure à 0');
        
        $evenement->reserverPlaces(-5);
    }

    public function testReserverPlacesQuantiteZero(): void
    {
        $evenement = $this->creerEvenement(100);
        
        $this->expectException(\InvalidArgumentException::class);
        
        $evenement->reserverPlaces(0);
    }

    public function testAnnulerReservation(): void
    {
        $evenement = $this->creerEvenement(100);
        $evenement->reserverPlaces(20);
        
        $evenement->annulerReservation(10);
        
        $this->assertEquals(10, $evenement->getPlacesVendues());
        $this->assertEquals(90, $evenement->getPlacesRestantes());
    }

    public function testAnnulerReservationTropGrande(): void
    {
        $evenement = $this->creerEvenement(100);
        $evenement->reserverPlaces(10);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('seulement 10 vendues');
        
        $evenement->annulerReservation(20);
    }

    public function testPeutAccepterReservation(): void
    {
        $evenement = $this->creerEvenement(100);
        
        $this->assertTrue($evenement->peutAccepterReservation(50));
        $this->assertTrue($evenement->peutAccepterReservation(100));
        $this->assertFalse($evenement->peutAccepterReservation(101));
    }

    public function testPeutAccepterReservationEvenementInactif(): void
    {
        $evenement = $this->creerEvenement(100, false);
        
        $this->assertFalse($evenement->peutAccepterReservation(10));
    }

    public function testActiver(): void
    {
        $evenement = $this->creerEvenement(100, false);
        
        $evenement->activer();
        
        $this->assertTrue($evenement->isActive());
    }

    public function testDesactiver(): void
    {
        $evenement = $this->creerEvenement(100, true);
        
        $evenement->desactiver();
        
        $this->assertFalse($evenement->isActive());
    }

    public function testIsComplet(): void
    {
        $evenement = $this->creerEvenement(10);
        
        $this->assertFalse($evenement->isComplet());
        
        $evenement->reserverPlaces(10);
        
        $this->assertTrue($evenement->isComplet());
    }

    public function testEstPasse(): void
    {
        $evenement = $this->creerEvenement(100);
        $evenement->setDateEvenement(new \DateTimeImmutable('-1 day'));
        
        $this->assertTrue($evenement->estPasse());
        $this->assertFalse($evenement->estAVenir());
    }

    public function testEstAVenir(): void
    {
        $evenement = $this->creerEvenement(100);
        $evenement->setDateEvenement(new \DateTimeImmutable('+1 day'));
        
        $this->assertTrue($evenement->estAVenir());
        $this->assertFalse($evenement->estPasse());
    }
}
