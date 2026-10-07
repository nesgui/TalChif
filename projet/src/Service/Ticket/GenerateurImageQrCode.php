<?php

declare(strict_types=1);

namespace App\Service\Ticket;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Rend un QR Code en PNG cote serveur (bibliotheque endroid/qr-code installee
 * en local via Composer).
 *
 * Remplace toute generation de QR en JavaScript : aucune ressource externe
 * n'est requise et l'affichage ne depend plus du navigateur.
 *
 * Expose par App\Controller\BilletQrCodeController (route billet.qrcode).
 */
final class GenerateurImageQrCode
{
    private const TAILLE_MIN = 48;
    private const TAILLE_MAX = 600;

    /**
     * Retourne les octets du PNG.
     */
    public function enPng(string $valeur, int $taille = 160, int $marge = 1): string
    {
        $taille = max(self::TAILLE_MIN, min($taille, self::TAILLE_MAX));

        $builder = new Builder(
            data: $valeur,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $taille,
            margin: max(0, $marge),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(26, 26, 26),
            backgroundColor: new Color(255, 255, 255),
            writer: new PngWriter()
        );

        return $builder->build()->getString();
    }
}
