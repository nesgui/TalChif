<?php

declare(strict_types=1);

namespace App\Service\Paiement;

/**
 * Decrit l'origine d'une confirmation de paiement.
 *
 * Chaque appelant (validation manuelle, webhook PawaPay, verification par
 * sondage) fournit son propre libelle de journalisation et, le cas echeant,
 * l'identifiant du valideur humain.
 */
final readonly class ContexteConfirmation
{
    public function __construct(
        public string $actionJournal,
        public string $detailsJournal,
        public ?int $validateurId = null,
        public ?string $adresseIp = null,
    ) {
    }

    public static function validationManuelle(int $validateurId): self
    {
        return new self(
            actionJournal: 'VALIDATION_PAIEMENT',
            detailsJournal: "Paiement valide par l'utilisateur ID {$validateurId}",
            validateurId: $validateurId,
        );
    }

    public static function webhookPawaPay(string $depositId): self
    {
        return new self(
            actionJournal: 'PAWAPAY_DEPOT_CONFIRME',
            detailsJournal: "Depot PawaPay {$depositId} confirme automatiquement",
            adresseIp: 'pawapay-webhook',
        );
    }

    public static function sondagePawaPay(string $depositId): self
    {
        return new self(
            actionJournal: 'PAWAPAY_DEPOT_CONFIRME_SONDAGE',
            detailsJournal: "Depot PawaPay {$depositId} confirme par verification periodique",
            adresseIp: 'pawapay-polling',
        );
    }
}
