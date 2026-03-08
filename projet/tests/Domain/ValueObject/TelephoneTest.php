<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\Exception\InvalidTelephoneException;
use App\Domain\ValueObject\Telephone;
use PHPUnit\Framework\TestCase;

final class TelephoneTest extends TestCase
{
    public function testCreerTelephoneValide(): void
    {
        $tel = Telephone::fromString('235 12 34 56 78');
        
        $this->assertEquals('23512345678', $tel->toString());
    }

    public function testCreerTelephoneAvecPlus(): void
    {
        $tel = Telephone::fromString('+235 12 34 56 78');
        
        $this->assertEquals('+23512345678', $tel->toString());
    }

    public function testCreerTelephoneSansEspaces(): void
    {
        $tel = Telephone::fromString('23512345678');
        
        $this->assertEquals('23512345678', $tel->toString());
    }

    public function testFormatageAffichage(): void
    {
        $tel = Telephone::fromString('23512345678');
        
        $this->assertEquals('235 12 34 56 78', $tel->toFormattedString());
    }

    public function testTelephoneInvalide(): void
    {
        $this->expectException(InvalidTelephoneException::class);
        $this->expectExceptionMessage("n'est pas valide");
        
        Telephone::fromString('123456');
    }

    public function testTelephoneTropCourt(): void
    {
        $this->expectException(InvalidTelephoneException::class);
        
        Telephone::fromString('235 12 34');
    }

    public function testTelephoneAvecLettres(): void
    {
        $this->expectException(InvalidTelephoneException::class);
        
        Telephone::fromString('235 AB CD EF GH');
    }

    public function testEgalite(): void
    {
        $tel1 = Telephone::fromString('235 12 34 56 78');
        $tel2 = Telephone::fromString('23512345678');
        
        $this->assertTrue($tel1->equals($tel2));
    }

    public function testInegalite(): void
    {
        $tel1 = Telephone::fromString('235 12 34 56 78');
        $tel2 = Telephone::fromString('235 98 76 54 32');
        
        $this->assertFalse($tel1->equals($tel2));
    }

    public function testToString(): void
    {
        $tel = Telephone::fromString('235 12 34 56 78');
        
        $this->assertEquals('23512345678', (string) $tel);
    }
}
