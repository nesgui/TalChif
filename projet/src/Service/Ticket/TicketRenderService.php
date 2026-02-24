<?php

declare(strict_types=1);

namespace App\Service\Ticket;

use App\Entity\Billet;
use App\Entity\TicketDesign;
use App\Repository\TicketDesignRepository;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TicketRenderService
{
    private const DOSSIER_TICKETS_RENDERED = 'tickets-rendered';

    private const FORMAT_SMALL = 'SMALL';
    private const FORMAT_MEDIUM = 'MEDIUM';
    private const FORMAT_LARGE = 'LARGE';

    private const DPI = 200;

    public function __construct(
        private TicketDesignRepository $ticketDesignRepository,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
    }

    /**
     * Génère un PNG final (design + QR) et retourne le chemin public.
     *
     * Retourne null si aucun design n'est configuré pour l'événement/type.
     */
    public function renderAndStoreBilletPng(Billet $billet): ?string
    {
        $evenement = $billet->getEvenement();
        if (!$evenement) {
            return null;
        }

        $type = strtoupper((string) $billet->getType());
        if ($type === 'SIMPLE') {
            $typeBillet = TicketDesign::TYPE_SIMPLE;
        } elseif ($type === 'VIP') {
            $typeBillet = TicketDesign::TYPE_VIP;
        } else {
            // compat legacy ("Simple")
            $typeBillet = $type === 'SIMPLE' ? TicketDesign::TYPE_SIMPLE : TicketDesign::TYPE_SIMPLE;
        }

        $ticketDesign = $this->ticketDesignRepository->findOneForEvenementAndType($evenement, $typeBillet);
        if (!$ticketDesign || !$ticketDesign->getDesignPath()) {
            return null;
        }

        $designAbs = $this->projectDir . '/public' . $ticketDesign->getDesignPath();
        if (!is_file($designAbs) || !is_readable($designAbs)) {
            return null;
        }

        // À ce stade on encode encore la valeur unique qrCode.
        // La couche de chiffrement/signature viendra ensuite.
        $payload = (string) $billet->getQrCode();

        $composed = $this->composeFrameWithDesignAndQr($designAbs, $payload);
        if ($composed === null) {
            return null;
        }

        $filename = 'billet-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $billet->getQrCode()) . '.png';
        $dirAbs = $this->projectDir . '/public/images/' . self::DOSSIER_TICKETS_RENDERED;
        if (!is_dir($dirAbs)) {
            mkdir($dirAbs, 0755, true);
        }

        $absOut = $dirAbs . '/' . $filename;
        $ok = @imagepng($composed, $absOut);
        imagedestroy($composed);

        if (!$ok) {
            return null;
        }

        return '/images/' . self::DOSSIER_TICKETS_RENDERED . '/' . $filename;
    }

    public function renderPreviewPngForDesign(TicketDesign $ticketDesign, string $payload): ?string
    {
        if (!\function_exists('imagecreatetruecolor')) {
            return null;
        }

        $designAbs = $this->projectDir . '/public' . $ticketDesign->getDesignPath();
        if (!is_file($designAbs) || !is_readable($designAbs)) {
            return null;
        }

        $composed = $this->composeFrameWithDesignAndQr($designAbs, $payload);
        if ($composed === null) {
            return null;
        }

        ob_start();
        imagepng($composed);
        $png = (string) ob_get_clean();
        imagedestroy($composed);

        return $png;
    }

    private function composeFrameWithDesignAndQr(string $designAbs, string $payload): ?\GdImage
    {
        if (!\function_exists('imagecreatetruecolor')) {
            return null;
        }

        $designImg = $this->loadImageFromFile($designAbs);
        if (!$designImg) {
            return null;
        }

        $designW = imagesx($designImg);
        $designH = imagesy($designImg);

        [$formatKey, $canvasW, $canvasH] = $this->pickCanvasSize($designW, $designH);

        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        if (!$canvas) {
            imagedestroy($designImg);
            return null;
        }

        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);

        // Layout zones (QR left, Design right)
        $padding = (int) round($canvasW * 0.03);
        $radius = (int) round(min($canvasW, $canvasH) * 0.06);
        $sepX = (int) round($canvasW * 0.72);

        $bg = imagecolorallocate($canvas, 255, 255, 255);
        $border = imagecolorallocate($canvas, 210, 210, 210);
        $qrBg = imagecolorallocate($canvas, 245, 246, 248);
        $dot = imagecolorallocate($canvas, 160, 160, 160);

        $this->drawRoundedRect($canvas, 0, 0, $canvasW - 1, $canvasH - 1, $radius, $bg, $border);

        // QR zone background
        imagefilledrectangle($canvas, $padding, $padding, $sepX - $padding, $canvasH - $padding, $qrBg);

        // Perforation dots separator
        $this->drawPerforationDots($canvas, $sepX, $padding, $canvasH - $padding, $dot);

        // Place design (contain) in right zone
        $rightX1 = $sepX + (int) round($padding * 0.7);
        $rightY1 = $padding;
        $rightX2 = $canvasW - $padding;
        $rightY2 = $canvasH - $padding;
        $this->placeContain($canvas, $designImg, $rightX1, $rightY1, $rightX2 - $rightX1, $rightY2 - $rightY1);

        // Create QR image
        $qrBoxSize = (int) round(min(($sepX - 2 * $padding), ($canvasH - 2 * $padding)) * 0.78);
        $qr = new Builder(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $qrBoxSize,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255),
            writer: new PngWriter()
        );

        $qrResult = $qr->build();
        $qrImg = @imagecreatefromstring($qrResult->getString());
        if (!$qrImg) {
            imagedestroy($designImg);
            imagedestroy($canvas);
            return null;
        }

        // Center QR in left zone
        $qrX = (int) round($padding + (($sepX - $padding) - $padding - $qrBoxSize) / 2);
        $qrY = (int) round($padding + (($canvasH - 2 * $padding) - $qrBoxSize) / 2);
        imagecopyresampled($canvas, $qrImg, $qrX, $qrY, 0, 0, $qrBoxSize, $qrBoxSize, imagesx($qrImg), imagesy($qrImg));

        imagedestroy($qrImg);
        imagedestroy($designImg);

        return $canvas;
    }

    /**
     * @return array{0:string,1:int,2:int}
     */
    private function pickCanvasSize(int $designW, int $designH): array
    {
        // Heuristic based on design area
        $area = $designW * $designH;

        // mm conventions (Option 1)
        $small = [180, 70];
        $medium = [195, 80];
        $large = [210, 90];

        if ($area <= 900_000) {
            [$mmW, $mmH] = $small;
            $key = self::FORMAT_SMALL;
        } elseif ($area <= 1_500_000) {
            [$mmW, $mmH] = $medium;
            $key = self::FORMAT_MEDIUM;
        } else {
            [$mmW, $mmH] = $large;
            $key = self::FORMAT_LARGE;
        }

        $pxW = (int) round(($mmW / 25.4) * self::DPI);
        $pxH = (int) round(($mmH / 25.4) * self::DPI);

        return [$key, $pxW, $pxH];
    }

    private function placeContain(\GdImage $dst, \GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0 || $w <= 0 || $h <= 0) {
            return;
        }

        $scale = min($w / $srcW, $h / $srcH);
        $newW = (int) floor($srcW * $scale);
        $newH = (int) floor($srcH * $scale);
        $dstX = (int) round($x + ($w - $newW) / 2);
        $dstY = (int) round($y + ($h - $newH) / 2);

        imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
    }

    private function drawPerforationDots(\GdImage $img, int $x, int $y1, int $y2, int $color): void
    {
        $step = 14;
        $r = 2;
        for ($y = $y1 + 6; $y < $y2 - 6; $y += $step) {
            imagefilledellipse($img, $x, $y, $r * 2, $r * 2, $color);
        }
    }

    private function drawRoundedRect(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $fillColor, int $borderColor): void
    {
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $fillColor);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $fillColor);
        imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $fillColor);
        imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $fillColor);
        imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $fillColor);
        imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $fillColor);

        imagesetthickness($img, 2);
        // borders
        imageline($img, $x1 + $r, $y1, $x2 - $r, $y1, $borderColor);
        imageline($img, $x1 + $r, $y2, $x2 - $r, $y2, $borderColor);
        imageline($img, $x1, $y1 + $r, $x1, $y2 - $r, $borderColor);
        imageline($img, $x2, $y1 + $r, $x2, $y2 - $r, $borderColor);
        imagearc($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $borderColor);
        imagearc($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $borderColor);
        imagearc($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $borderColor);
        imagearc($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $borderColor);
        imagesetthickness($img, 1);
    }

    /**
     * Charge une image depuis un fichier (PNG, JPEG, GIF, WebP, BMP).
     */
    private function loadImageFromFile(string $path): ?\GdImage
    {
        $data = @file_get_contents($path);
        if ($data === false || $data === '') {
            return null;
        }

        $img = @imagecreatefromstring($data);
        if ($img === false) {
            return null;
        }

        return $img;
    }
}
