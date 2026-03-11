/**
 * JavaScript de validation en temps réel pour les formulaires
 * Fournit une validation instantanée et des indicateurs visuels
 */

(function() {
if (window.FormValidator) return;

class FormValidator {
    constructor() {
        this.init();
    }

    init() {
        this.setupRealTimeValidation();
        this.setupFieldIndicators();
        this.setupAccessibility();
    }

    /**
     * Configure la validation en temps réel
     */
    setupRealTimeValidation() {
        document.addEventListener('input', (e) => {
            if (e.target.matches('.auth-input, .auth-textarea, .auth-select')) {
                this.validateField(e.target);
            }
        });

        document.addEventListener('blur', (e) => {
            if (e.target.matches('.auth-input, .auth-textarea, .auth-select')) {
                this.validateField(e.target, true);
            }
        });

        document.addEventListener('focus', (e) => {
            if (e.target.matches('.auth-input, .auth-textarea, .auth-select')) {
                this.showFieldHelp(e.target);
            }
        });
    }

    /**
     * Configure les indicateurs de champs
     */
    setupFieldIndicators() {
        // Ajouter les classes required/optional aux champs
        document.querySelectorAll('.auth-input[required], .auth-textarea[required], .auth-select[required]').forEach(field => {
            field.classList.add('required');
            this.updateFieldStatus(field);
        });

        document.querySelectorAll('.auth-input:not([required]), .auth-textarea:not([required]), .auth-select:not([required])').forEach(field => {
            field.classList.add('optional');
            this.updateFieldStatus(field);
        });

        // Mettre à jour les groupes de champs
        document.querySelectorAll('.auth-form-group').forEach(group => {
            const field = group.querySelector('.auth-input, .auth-textarea, .auth-select');
            if (field) {
                if (field.hasAttribute('required') || field.classList.contains('required')) {
                    group.classList.add('required-field');
                } else {
                    group.classList.add('optional-field');
                }
            }
        });
    }

    /**
     * Configure l'accessibilité
     */
    setupAccessibility() {
        // Ajouter les attributs ARIA
        document.querySelectorAll('.form-input, .form-textarea, .form-select').forEach(field => {
            const groupId = field.closest('.form-group')?.id;
            const helpId = groupId ? `${groupId}_help` : null;
            const errorId = groupId ? `${groupId}_errors` : null;

            if (field.hasAttribute('required')) {
                field.setAttribute('aria-required', 'true');
            }

            const descriptors = [];
            if (helpId) descriptors.push(helpId);
            if (errorId) descriptors.push(errorId);
            
            if (descriptors.length > 0) {
                field.setAttribute('aria-describedby', descriptors.join(' '));
            }
        });
    }

    /**
     * Valide un champ individuel
     */
    validateField(field, showErrors = false) {
        const isValid = this.checkFieldValidity(field);
        const statusElement = document.getElementById(`${field.id}_status`);
        
        if (statusElement) {
            const validIcon = statusElement.querySelector('.validation-icon.valid');
            const invalidIcon = statusElement.querySelector('.validation-icon.invalid');
            
            if (field.value.trim() === '') {
                // Champ vide - masquer les icônes
                validIcon.style.display = 'none';
                invalidIcon.style.display = 'none';
                field.classList.remove('is-valid', 'is-invalid');
            } else if (isValid) {
                // Champ valide
                validIcon.style.display = 'flex';
                invalidIcon.style.display = 'none';
                field.classList.add('is-valid');
                field.classList.remove('is-invalid');
            } else {
                // Champ invalide
                validIcon.style.display = 'none';
                invalidIcon.style.display = 'flex';
                field.classList.add('is-invalid');
                field.classList.remove('is-valid');
                
                if (showErrors) {
                    this.showFieldError(field);
                }
            }
        }

        return isValid;
    }

    /**
     * Vérifie la validité d'un champ
     */
    checkFieldValidity(field) {
        // Validation HTML5 native
        if (!field.checkValidity()) {
            return false;
        }

        // Validations personnalisées
        const fieldType = field.type || field.tagName.toLowerCase();
        const value = field.value.trim();

        switch (fieldType) {
            case 'email':
                return this.isValidEmail(value);
            case 'tel':
                return this.isValidPhone(value);
            case 'text':
                return this.isValidText(field, value);
            default:
                return true;
        }
    }

    /**
     * Validation email
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validation téléphone
     */
    isValidPhone(phone) {
        if (phone === '') return true; // Champ facultatif
        const phoneRegex = /^[\d\s+\-()]*$/;
        return phoneRegex.test(phone) && phone.length >= 8;
    }

    /**
     * Validation texte
     */
    isValidText(field, value) {
        const fieldName = field.name || field.id;
        
        // Validation spécifique selon le nom du champ
        if (fieldName.includes('nom')) {
            return value.length >= 2 && /^[\p{L}\p{M}\s\-']+$/u.test(value);
        }
        
        if (fieldName.includes('password')) {
            return value.length >= 6;
        }
        
        return true;
    }

    /**
     * Met à jour le statut du champ
     */
    updateFieldStatus(field) {
        const label = document.querySelector(`label[for="${field.id}"]`);
        if (label) {
            if (field.hasAttribute('required') || field.classList.contains('required')) {
                label.classList.add('required');
                label.classList.remove('optional');
            } else {
                label.classList.add('optional');
                label.classList.remove('required');
            }
        }
    }

    /**
     * Affiche l'aide contextuelle
     */
    showFieldHelp(field) {
        const helpElement = document.getElementById(`${field.id}_help`);
        if (helpElement) {
            helpElement.style.display = 'flex';
        }
    }

    /**
     * Affiche l'erreur de champ
     */
    showFieldError(field) {
        const errorElement = document.getElementById(`${field.id}_errors`);
        if (errorElement && field.validationMessage) {
            errorElement.style.display = 'block';
            errorElement.setAttribute('aria-live', 'polite');
        }
    }

    /**
     * Valide un formulaire complet
     */
    validateForm(form) {
        const fields = form.querySelectorAll('.auth-input, .auth-textarea, .auth-select');
        let isValid = true;

        fields.forEach(field => {
            if (!this.validateField(field, true)) {
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * Configure la validation pour un formulaire spécifique
     */
    setupFormValidation(form) {
        form.addEventListener('submit', (e) => {
            if (!this.validateForm(form)) {
                e.preventDefault();
                this.showFormError(form);
                return false;
            }
        });
    }

    /**
     * Affiche l'erreur de formulaire
     */
    showFormError(form) {
        const firstInvalidField = form.querySelector('.is-invalid');
        if (firstInvalidField) {
            firstInvalidField.focus();
        }

        // Créer une alerte d'erreur
        const alert = this.createAlert('error', 'Veuillez corriger les erreurs dans le formulaire');
        form.insertBefore(alert, form.firstChild);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    /**
     * Crée une alerte
     */
    createAlert(type, message) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <div class="alert-content">
                <span class="alert-icon">${this.getIconForType(type)}</span>
                <span class="alert-message">${message}</span>
                <button type="button" class="btn-close" aria-label="Fermer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        `;
        
        // Gérer la fermeture
        const closeBtn = alert.querySelector('.btn-close');
        closeBtn.addEventListener('click', () => alert.remove());
        
        return alert;
    }

    /**
     * Retourne l'icône pour un type d'alerte
     */
    getIconForType(type) {
        const icons = {
            error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20,6 9,17 4,12"/></svg>',
            info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };
        return icons[type] || icons.info;
    }
}

// Initialiser le validateur de formulaire quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    window.formValidator = new FormValidator();
    
    // Configurer la validation pour tous les formulaires
    document.querySelectorAll('form').forEach(form => {
        window.formValidator.setupFormValidation(form);
    });
});

// Exporter pour utilisation externe si nécessaire
window.FormValidator = FormValidator;

})();
