<?php

declare(strict_types=1);

namespace App\Service\Upload;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service centralisé pour l'upload sécurisé des fichiers (images événements, billets).
 *
 * - Validation du type MIME côté serveur (pas seulement l'extension)
 * - Nom de fichier sécurisé (slug + bytes aléatoires)
 * - Taille maximale et types autorisés configurables
 */
final class ServiceUploadFichier
{
    /** Types MIME autorisés pour les images d'événements et billets */
    private const TYPES_MIME_AUTORISES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /** Taille max en octets (5 Mo) */
    private const TAILLE_MAX_OCTETS = 5 * 1024 * 1024;

    /** Sous-dossiers publics pour les uploads */
    private const DOSSIER_EVENEMENTS = 'evenements';
    private const DOSSIER_BILLETS = 'billets';

    public function __construct(
        private SluggerInterface $slugger,
        private string $repertoireProjet
    ) {
    }

    /**
     * Enregistre une image d'événement (affiche principale ou autre affiche).
     *
     * @param UploadedFile $fichier Fichier envoyé par le formulaire
     * @param string       $sousDossier 'evenements' ou 'billets'
     * @return string Chemin public relatif (ex: /images/evenements/nom-abc123.jpg)
     * @throws FileException Si le fichier est invalide ou l'upload échoue
     */
    public function uploaderImageEvenement(UploadedFile $fichier, string $sousDossier = self::DOSSIER_EVENEMENTS): string
    {
        $this->validerFichier($fichier);

        $nomSecurise = $this->genererNomSecurise($fichier);
        $repertoireCible = $this->repertoireProjet . '/public/images/' . $sousDossier;

        if (!is_dir($repertoireCible)) {
            mkdir($repertoireCible, 0755, true);
        }

        try {
            $fichier->move($repertoireCible, $nomSecurise);
        } catch (FileException $e) {
            throw new FileException('Échec de l\'enregistrement du fichier : ' . $e->getMessage());
        }

        return '/images/' . $sousDossier . '/' . $nomSecurise;
    }

    /**
     * Enregistre une image de billet (template billet).
     */
    public function uploaderImageBillet(UploadedFile $fichier): string
    {
        return $this->uploaderImageEvenement($fichier, self::DOSSIER_BILLETS);
    }

    /**
     * Vérifie le type MIME réel et la taille du fichier.
     */
    private function validerFichier(UploadedFile $fichier): void
    {
        $chemin = $fichier->getPathname();
        if (!is_readable($chemin)) {
            throw new FileException('Fichier illisible.');
        }

        $typeMime = mime_content_type($chemin) ?: '';
        if (!\in_array($typeMime, self::TYPES_MIME_AUTORISES, true)) {
            throw new FileException(
                'Type de fichier non autorisé. Utilisez une image JPEG, PNG, GIF ou WebP.'
            );
        }

        $taille = $fichier->getSize();
        if ($taille > self::TAILLE_MAX_OCTETS) {
            throw new FileException(
                'Le fichier ne doit pas dépasser 5 Mo.'
            );
        }
    }

    /**
     * Génère un nom de fichier unique et sécurisé (évite path traversal et noms prévisibles).
     */
    private function genererNomSecurise(UploadedFile $fichier): string
    {
        $nomOriginal = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = $this->slugger->slug($nomOriginal)->toString();
        if ($slug === '') {
            $slug = 'image';
        }
        $extension = $fichier->guessExtension() ?? 'jpg';
        $suffixe = bin2hex(random_bytes(16));

        return $slug . '-' . $suffixe . '.' . $extension;
    }
}
