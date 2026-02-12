<?php

namespace App\Service\Payment;

/**
 * Abstraction pour les moyens de paiement (Mobile Money, Carte bancaire, etc.).
 * Permet d'injecter une implémentation réelle (API Mobile Money / Stripe) plus tard.
 */
interface PaymentInterface
{
    /**
     * Méthodes supportées (identifiants courts).
     */
    public const METHODE_MOMO = 'momo';
    public const METHODE_AIRTEL = 'airtel';
    public const METHODE_ORANGE = 'orange';
    public const METHODE_CARTE = 'carte';

    /**
     * Liste des méthodes actuellement supportées par cette implémentation.
     *
     * @return array<string>
     */
    public function getMethodesSupportees(): array;

    /**
     * Vérifie si une méthode de paiement est supportée.
     */
    public function supports(string $methode): bool;

    /**
     * Initie un paiement et retourne le résultat.
     *
     * @param float   $montant   Montant en unités (ex: XAF)
     * @param string  $methode   Une des constantes METHODE_*
     * @param array   $context   Données additionnelles (telephone, email, reference, etc.)
     * @return PaymentResult
     */
    public function payer(float $montant, string $methode, array $context = []): PaymentResult;
}
