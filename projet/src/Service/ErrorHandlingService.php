<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ErrorHandlingService
{
    private ?RequestStack $requestStack;
    private ?TranslatorInterface $translator;
    private ?CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(
        RequestStack $requestStack,
        ?TranslatorInterface $translator = null,
        ?CsrfTokenManagerInterface $csrfTokenManager = null
    ) {
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Ajoute un message d'erreur flash avec traduction si disponible
     */
    public function addErrorFlash(string $message, array $parameters = []): void
    {
        $translatedMessage = $this->translator ? 
            $this->translator->trans($message, $parameters, 'messages') : 
            $message;
        
        $this->getSession()->getFlashBag()->add('error', $translatedMessage);
    }

    /**
     * Ajoute un message de succès flash avec traduction si disponible
     */
    public function addSuccessFlash(string $message, array $parameters = []): void
    {
        $translatedMessage = $this->translator ? 
            $this->translator->trans($message, $parameters, 'messages') : 
            $message;
        
        $this->getSession()->getFlashBag()->add('success', $translatedMessage);
    }

    /**
     * Ajoute un message d'avertissement flash avec traduction si disponible
     */
    public function addWarningFlash(string $message, array $parameters = []): void
    {
        $translatedMessage = $this->translator ? 
            $this->translator->trans($message, $parameters, 'messages') : 
            $message;
        
        $this->getSession()->getFlashBag()->add('warning', $translatedMessage);
    }

    /**
     * Obtient la session depuis le RequestStack
     */
    private function getSession()
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request ? $request->getSession() : null;
    }

    /**
     * Valide et nettoie les données de requête
     */
    public function validateAndCleanRequestData(Request $request, array $fields): array
    {
        $cleanedData = [];
        $errors = [];

        foreach ($fields as $fieldName => $rules) {
            $value = $request->get($fieldName);
            
            if ($value !== null) {
                // Nettoyage de base
                $value = trim($value);
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
                
                // Validation des règles
                if (isset($rules['required']) && $rules['required'] && empty($value)) {
                    $errors[$fieldName] = $rules['required_message'] ?? "Le champ {$fieldName} est obligatoire";
                    continue;
                }

                if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                    $msg = $rules['max_length_message'] ?? "Le champ {$fieldName} ne peut pas dépasser {{ limit }} caractères";
                    $errors[$fieldName] = str_replace('{{ limit }}', (string) $rules['max_length'], $msg);
                    continue;
                }

                if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                    $msg = $rules['min_length_message'] ?? "Le champ {$fieldName} doit faire au moins {{ limit }} caractères";
                    $errors[$fieldName] = str_replace('{{ limit }}', (string) $rules['min_length'], $msg);
                    continue;
                }

                if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
                    $errors[$fieldName] = $rules['pattern_message'] ?? "Le format du champ {$fieldName} est invalide";
                    continue;
                }

                $cleanedData[$fieldName] = $value;
            } elseif (isset($rules['required']) && $rules['required']) {
                $errors[$fieldName] = $rules['required_message'] ?? "Le champ {$fieldName} est obligatoire";
            }
        }

        return ['data' => $cleanedData, 'errors' => $errors];
    }

    /**
     * Extrait les erreurs de formulaire et les ajoute en messages flash
     */
    public function handleFormErrors(FormInterface $form): void
    {
        if (!$form->isValid()) {
            $errors = $this->getFormErrorsAsArray($form);
            
            foreach ($errors as $error) {
                $this->addErrorFlash($error);
            }
        }
    }

    /**
     * Convertit les erreurs de formulaire en tableau de chaînes
     */
    public function getFormErrorsAsArray(FormInterface $form): array
    {
        $errors = [];
        
        // Erreurs globales du formulaire
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }
        
        // Erreurs des champs
        foreach ($form->all() as $child) {
            if ($child->isValid()) {
                continue;
            }
            
            foreach ($child->getErrors() as $error) {
                $fieldName = $child->getName();
                $label = $child->getConfig()->getOption('label', $fieldName);
                $errors[] = "{$label}: {$error->getMessage()}";
            }
        }
        
        return $errors;
    }

    /**
     * Gère les erreurs de sécurité (accès refusé, CSRF, etc.)
     */
    public function handleSecurityError(\Throwable $exception): void
    {
        if ($exception instanceof \Symfony\Component\Security\Core\Exception\AccessDeniedException) {
            $this->addErrorFlash('error.access_denied');
        } elseif ($exception instanceof \Symfony\Component\Security\Csrf\Exception\InvalidCsrfTokenException) {
            $this->addErrorFlash('error.invalid_csrf_token');
        } else {
            $this->addErrorFlash('Mot de passe ou email incorrect');
        }
    }

    /**
     * Gère les erreurs de base de données
     */
    public function handleDatabaseError(\Throwable $exception): void
    {
        if ($exception instanceof \Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            $this->addErrorFlash('error.duplicate_entry');
        } elseif ($exception instanceof \Doctrine\DBAL\Exception\ConnectionException) {
            $this->addErrorFlash('error.database_connection');
        } else {
            $this->addErrorFlash('error.database_general');
        }
    }

    /**
     * Gère les erreurs d'upload de fichiers
     */
    public function handleFileUploadError(\Throwable $exception): void
    {
        if ($exception instanceof \Symfony\Component\HttpFoundation\File\Exception\FileException) {
            if (str_contains($exception->getMessage(), 'size')) {
                $this->addErrorFlash('error.file_too_large');
            } elseif (str_contains($exception->getMessage(), 'mime')) {
                $this->addErrorFlash('error.file_invalid_type');
            } else {
                $this->addErrorFlash('error.file_upload_general');
            }
        } else {
            $this->addErrorFlash('error.file_upload_general');
        }
    }

    /**
     * Valide la sécurité d'une action (CSRF, permissions, etc.)
     */
    public function validateSecurity(Request $request, ?string $csrfTokenId = null): array
    {
        $errors = [];
        
        // Validation CSRF si un token ID est fourni
        if ($csrfTokenId && !$request->isXmlHttpRequest()) {
            $submittedToken = $request->request->get('_csrf_token');
            if (!$submittedToken || !$this->csrfTokenManager || !$this->csrfTokenManager->isTokenValid(new CsrfToken($csrfTokenId, $submittedToken))) {
                $errors[] = 'Token CSRF invalide';
            }
        }
        
        return $errors;
    }


    /**
     * Ajoute des erreurs de validation personnalisées pour les menus/boutons
     */
    public function addMenuError(string $action, string $reason): void
    {
        $message = "Impossible d'effectuer l'action \"{$action}\": {$reason}";
        $this->addErrorFlash($message);
    }

    /**
     * Journalise une erreur pour le débogage
     */
    public function logError(\Throwable $exception, array $context = []): void
    {
        // Dans un environnement de production, utiliser un vrai logger
        // Pour l'instant, on ajoute juste un message flash en mode développement
        if ($_ENV['APP_ENV'] === 'dev') {
            $this->addErrorFlash('Erreur technique: ' . $exception->getMessage());
        }
    }
}
