<?php

namespace App\Service\Payment;

/**
 * Implémentation stub du paiement pour développement et tests.
 * Simule Mobile Money / Carte sans appeler d'API réelle.
 * En production, remplacer par MomoPaymentService ou CardPaymentService.
 */
class StubPaymentService implements PaymentInterface
{
    private const PREFIX_TRANSACTION = 'TEST_';

    public function getMethodesSupportees(): array
    {
        return [
            self::METHODE_MOMO,
            self::METHODE_AIRTEL,
            self::METHODE_ORANGE,
        ];
    }

    public function supports(string $methode): bool
    {
        return \in_array($methode, $this->getMethodesSupportees(), true);
    }

    public function payer(float $montant, string $methode, array $context = []): PaymentResult
    {
        if (!$this->supports($methode)) {
            return PaymentResult::echec(
                '',
                "Méthode de paiement non supportée : {$methode}"
            );
        }

        $telephone = $context['telephone'] ?? null;
        if (empty($telephone) || !$this->validerTelephone($telephone)) {
            return PaymentResult::echec(
                '',
                'Format du numéro de téléphone invalide.'
            );
        }

        $transactionId = self::PREFIX_TRANSACTION . strtoupper($methode) . '_' . uniqid('', true);

        // Simulation délai réseau
        usleep(200_000);

        return PaymentResult::succes(
            $transactionId,
            "Paiement de {$montant} XAF simulé avec succès par {$methode}."
        );
    }

    /**
     * Validation basique des numéros (Tchad / formats courants).
     */
    private function validerTelephone(string $telephone): bool
    {
        $cleaned = preg_replace('/\s/', '', $telephone);
        $patterns = [
            '/^235\d{8}$/',
            '/^\+235\d{8}$/',
            '/^235\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{2}$/',
            '/^00\d{11}$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleaned)) {
                return true;
            }
        }

        return false;
    }
}
