<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\Exception\InvalidEmailException;
use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testCreerEmailValide(): void
    {
        $email = Email::fromString('user@example.com');
        
        $this->assertEquals('user@example.com', $email->toString());
    }

    public function testNormalisationMinuscules(): void
    {
        $email = Email::fromString('USER@EXAMPLE.COM');
        
        $this->assertEquals('user@example.com', $email->toString());
    }

    public function testSuppressionEspaces(): void
    {
        $email = Email::fromString('  user@example.com  ');
        
        $this->assertEquals('user@example.com', $email->toString());
    }

    public function testEmailInvalide(): void
    {
        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage("n'est pas valide");
        
        Email::fromString('invalid-email');
    }

    public function testEmailSansArobase(): void
    {
        $this->expectException(InvalidEmailException::class);
        
        Email::fromString('userexample.com');
    }

    public function testEmailSansDomaine(): void
    {
        $this->expectException(InvalidEmailException::class);
        
        Email::fromString('user@');
    }

    public function testGetDomain(): void
    {
        $email = Email::fromString('user@example.com');
        
        $this->assertEquals('example.com', $email->getDomain());
    }

    public function testGetLocalPart(): void
    {
        $email = Email::fromString('user@example.com');
        
        $this->assertEquals('user', $email->getLocalPart());
    }

    public function testEgalite(): void
    {
        $email1 = Email::fromString('user@example.com');
        $email2 = Email::fromString('USER@EXAMPLE.COM');
        
        $this->assertTrue($email1->equals($email2));
    }

    public function testInegalite(): void
    {
        $email1 = Email::fromString('user1@example.com');
        $email2 = Email::fromString('user2@example.com');
        
        $this->assertFalse($email1->equals($email2));
    }

    public function testToString(): void
    {
        $email = Email::fromString('user@example.com');
        
        $this->assertEquals('user@example.com', (string) $email);
    }
}
