<?php

declare(strict_types=1);

namespace App\Service\Panier;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Transforme le panier stocke en session en lignes affichables.
 *
 * Les evenements sont charges en une seule requete : la page panier et le
 * recapitulatif de paiement faisaient auparavant un find() par ligne, chacun
 * avec sa propre copie de ce code.
 */
final class ResolveurPanier
{
    public const CLE_SESSION = 'panier';

    private const TYPE_SIMPLE = 'SIMPLE';
    private const TYPE_VIP = 'VIP';

    public function __construct(
        private EvenementRepository $evenementRepository,
    ) {
    }

    public function resoudre(SessionInterface $session): ContenuPanier
    {
        $entrees = $this->normaliser($session->get(self::CLE_SESSION, []));
        if ($entrees === []) {
            return new ContenuPanier([], 0.0);
        }

        $evenements = $this->evenementRepository->findActifsParIds(array_keys($entrees));

        $lignes = [];
        $total = 0.0;

        foreach ($entrees as $idEvenement => $entree) {
            $evenement = $evenements[$idEvenement] ?? null;
            if (!$evenement instanceof Evenement) {
                // Evenement supprime ou desactive depuis l'ajout au panier.
                continue;
            }

            $prix = $this->prix($evenement, $entree['type']);
            $sousTotal = $prix * $entree['quantite'];
            $total += $sousTotal;

            $lignes[] = [
                'id' => $idEvenement,
                'quantite' => $entree['quantite'],
                'type' => $entree['type'],
                'sous_total' => $sousTotal,
                'produit' => [
                    'id' => $evenement->getId(),
                    'slug' => $evenement->getSlug(),
                    'titre' => $evenement->getNom(),
                    'image' => $evenement->getAffichePrincipale() ?: '/images/evenements/default.svg',
                    'prix_simple' => $evenement->getPrixSimple(),
                    'prix_vip' => $evenement->getPrixVip(),
                    'prix_choisi' => $prix,
                    'ville' => $evenement->getVille(),
                    'date' => $evenement->getDateEvenement()->format('Y-m-d H:i'),
                    'places_restantes' => $evenement->getPlacesRestantes(),
                ],
            ];
        }

        return new ContenuPanier($lignes, $total);
    }

    /**
     * Ramene les entrees de session a une forme unique.
     *
     * L'ancienne structure stockait un entier par evenement, la nouvelle un
     * tableau quantite/type.
     *
     * @return array<int, array{quantite: int, type: string}>
     */
    private function normaliser(mixed $panier): array
    {
        if (!is_array($panier)) {
            return [];
        }

        $entrees = [];

        foreach ($panier as $id => $donnees) {
            $identifiant = (int) $id;
            if ($identifiant <= 0) {
                continue;
            }

            $quantite = (int) (is_array($donnees) ? ($donnees['quantite'] ?? 0) : $donnees);
            if ($quantite <= 0) {
                continue;
            }

            $type = strtoupper(trim((string) (is_array($donnees) ? ($donnees['type'] ?? '') : '')));
            if (!\in_array($type, [self::TYPE_SIMPLE, self::TYPE_VIP], true)) {
                $type = self::TYPE_SIMPLE;
            }

            $entrees[$identifiant] = ['quantite' => $quantite, 'type' => $type];
        }

        return $entrees;
    }

    /**
     * Le tarif VIP ne s'applique que s'il est renseigne sur l'evenement.
     */
    private function prix(Evenement $evenement, string $type): float
    {
        if ($type === self::TYPE_VIP && $evenement->getPrixVip()) {
            return (float) $evenement->getPrixVip();
        }

        return (float) $evenement->getPrixSimple();
    }
}
