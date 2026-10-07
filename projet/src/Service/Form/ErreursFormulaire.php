<?php

declare(strict_types=1);

namespace App\Service\Form;

use App\Service\Notification\MessagesUtilisateurInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Traduit les erreurs d'un formulaire Symfony en messages lisibles.
 */
final class ErreursFormulaire
{
    public function __construct(
        private MessagesUtilisateurInterface $messagesFlash,
    ) {
    }

    /**
     * Erreurs globales du formulaire, puis erreurs de chaque champ prefixees
     * par son libelle.
     *
     * @return list<string>
     */
    public function enTableau(FormInterface $formulaire): array
    {
        $erreurs = [];

        foreach ($formulaire->getErrors() as $erreur) {
            $erreurs[] = $erreur->getMessage();
        }

        foreach ($formulaire->all() as $champ) {
            if ($champ->isValid()) {
                continue;
            }

            $libelle = $champ->getConfig()->getOption('label') ?: $champ->getName();

            foreach ($champ->getErrors() as $erreur) {
                $erreurs[] = sprintf('%s : %s', $libelle, $erreur->getMessage());
            }
        }

        return $erreurs;
    }

    /**
     * Publie les erreurs du formulaire en messages flash.
     */
    public function publierEnFlash(FormInterface $formulaire): void
    {
        if ($formulaire->isValid()) {
            return;
        }

        foreach ($this->enTableau($formulaire) as $erreur) {
            $this->messagesFlash->erreur($erreur);
        }
    }
}
