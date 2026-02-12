<?php

namespace App\Tests\Service\Payment;

use App\Service\Payment\PaymentInterface;
use App\Service\Payment\StubPaymentService;
use PHPUnit\Framework\TestCase;

final class StubPaymentServiceTest extends TestCase
{
    private StubPaymentService $service;

    protected function setUp(): void
    {
        $this->service = new StubPaymentService();
    }

    public function testMethodesSupportees(): void
    {
        $methodes = $this->service->getMethodesSupportees();
        $this->assertContains(PaymentInterface::METHODE_MOMO, $methodes);
        $this->assertContains(PaymentInterface::METHODE_AIRTEL, $methodes);
        $this->assertContains(PaymentInterface::METHODE_ORANGE, $methodes);
    }

    public function testSupportsMomo(): void
    {
        $this->assertTrue($this->service->supports('momo'));
    }

    public function testSupportsCarteNonSupportee(): void
    {
        $this->assertFalse($this->service->supports('carte'));
    }

    public function testPayerSansTelephoneEchoue(): void
    {
        $result = $this->service->payer(1000, 'momo', []);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('téléphone', $result->getMessage());
    }

    public function testPayerAvecTelephoneValideReussit(): void
    {
        $result = $this->service->payer(5000, 'momo', ['telephone' => '23512345678']);
        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getTransactionId());
        $this->assertStringStartsWith('TEST_', $result->getTransactionId());
    }

    public function testPayerMethodeInvalideEchoue(): void
    {
        $result = $this->service->payer(1000, 'carte', ['telephone' => '23512345678']);
        $this->assertFalse($result->isSuccess());
    }
}
