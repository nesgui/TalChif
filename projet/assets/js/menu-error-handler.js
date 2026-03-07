/**
 * JavaScript de gestion des erreurs pour menus et boutons
 * Fournit une gestion unifiée des erreurs d'interaction utilisateur
 */

class MenuErrorHandler {
    constructor() {
        this.init();
        // Nettoyer immédiatement les spinners existants au démarrage
        this.cleanupAllLoadingStates();
    }

    init() {
        this.setupMenuErrorHandling();
        this.setupButtonErrorHandling();
        this.setupAjaxErrorHandling();
        this.setupFormErrorHandling();
        
        // Nettoyer les états de chargement périodiquement
        setInterval(() => {
            this.cleanupAllLoadingStates();
        }, 1000);
    }

    /**
     * Nettoie tous les états de chargement
     */
    cleanupAllLoadingStates() {
        document.querySelectorAll('.dashboard-link.loading, .dashboard-sublink.loading').forEach(link => {
            this.hideMenuLoading(link);
        });
        
        // Nettoyer aussi les spinners orphelins
        document.querySelectorAll('.loading-spinner').forEach(spinner => {
            spinner.remove();
        });
    }

    /**
     * Gestion des erreurs pour les menus de navigation
     */
    setupMenuErrorHandling() {
        // Intercepter les clics sur les liens du menu
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (link && this.isMenuLink(link)) {
                this.handleMenuClick(link, e);
            }
        });

        // Gérer les événements Turbo pour masquer le chargement
        document.addEventListener('turbo:before-fetch-request', (e) => {
            const link = e.target.closest('a[href]');
            if (link && this.isMenuLink(link)) {
                this.showMenuLoading(link);
            }
        });

        document.addEventListener('turbo:before-render', (e) => {
            // Masquer tous les états de chargement
            this.cleanupAllLoadingStates();
        });

        document.addEventListener('turbo:render', (e) => {
            // Masquer tous les états de chargement après le rendu
            this.cleanupAllLoadingStates();
        });

        document.addEventListener('turbo:load', (e) => {
            // Nettoyer au chargement complet de la page
            this.cleanupAllLoadingStates();
        });

        document.addEventListener('turbo:frame-load', (e) => {
            // Nettoyer au chargement d'un frame
            this.cleanupAllLoadingStates();
        });

        document.addEventListener('turbo:fetch-request-error', (e) => {
            const link = e.target.closest('a[href]');
            if (link && this.isMenuLink(link)) {
                this.handleMenuError(link, 'Erreur de chargement du menu');
            }
        });
    }

    /**
     * Gestion des erreurs pour les boutons
     */
    setupButtonErrorHandling() {
        document.addEventListener('click', (e) => {
            const button = e.target.closest('button, [role="button"]');
            if (button) {
                this.handleButtonClick(button, e);
            }
        });
    }

    /**
     * Gestion des erreurs AJAX globales
     */
    setupAjaxErrorHandling() {
        // Intercepter les erreurs fetch globales
        window.addEventListener('unhandledrejection', (e) => {
            this.handleGlobalError(e.reason);
        });

        // Gérer les erreurs de réseau
        window.addEventListener('online', () => {
            this.hideNetworkError();
        });

        window.addEventListener('offline', () => {
            this.showNetworkError();
        });
    }

    /**
     * Gestion des erreurs de formulaire
     */
    setupFormErrorHandling() {
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.tagName === 'FORM') {
                this.handleFormSubmit(form, e);
            }
        });
    }

    /**
     * Vérifie si un lien fait partie d'un menu
     */
    isMenuLink(link) {
        return link.closest('.dashboard-nav, .nav, .menu, [role="navigation"]');
    }

    /**
     * Gère le clic sur un lien de menu
     */
    handleMenuClick(link, event) {
        try {
            // Vérifier si le lien est accessible
            if (link.hasAttribute('data-disabled') || link.classList.contains('disabled')) {
                event.preventDefault();
                this.showMenuError(link, 'Cette option n\'est pas disponible');
                return;
            }

            // Vérifier les permissions si nécessaire
            if (link.hasAttribute('data-permission')) {
                const permission = link.getAttribute('data-permission');
                if (!this.hasPermission(permission)) {
                    event.preventDefault();
                    this.showMenuError(link, 'Vous n\'avez pas les permissions nécessaires');
                    return;
                }
            }

            // N'afficher le chargement que pour les liens Turbo
            if (link.hasAttribute('data-turbo-frame') || link.closest('turbo-frame')) {
                // Le chargement sera géré par les événements Turbo
                return;
            }

            // Pour les liens normaux, ne pas afficher de chargement
            // La navigation se fera normalement

        } catch (error) {
            event.preventDefault();
            this.showMenuError(link, 'Erreur lors de la navigation');
            console.error('Menu click error:', error);
        }
    }

    /**
     * Gère le clic sur un bouton
     */
    handleButtonClick(button, event) {
        try {
            // Vérifier si le bouton est désactivé
            if (button.disabled || button.hasAttribute('data-disabled')) {
                event.preventDefault();
                this.showButtonError(button, 'Ce bouton n\'est pas disponible');
                return;
            }

            // Vérifier si le bouton est en cours de traitement
            if (button.classList.contains('loading')) {
                event.preventDefault();
                return;
            }

            // Gérer les boutons de soumission de formulaire
            if (button.type === 'submit' || button.hasAttribute('data-submit')) {
                this.handleSubmitButton(button, event);
            }

            // Gérer les boutons d'action
            if (button.hasAttribute('data-action')) {
                this.handleActionButton(button, event);
            }

        } catch (error) {
            event.preventDefault();
            this.showButtonError(button, 'Erreur lors de l\'action');
            console.error('Button click error:', error);
        }
    }

    /**
     * Gère la soumission de formulaire
     */
    handleFormSubmit(form, event) {
        try {
            // Valider le formulaire avant soumission
            if (!this.validateForm(form)) {
                event.preventDefault();
                this.showFormError(form, 'Veuillez corriger les erreurs du formulaire');
                return;
            }

            // Afficher l'état de chargement
            this.showFormLoading(form);

        } catch (error) {
            event.preventDefault();
            this.showFormError(form, 'Erreur lors de la soumission du formulaire');
            console.error('Form submit error:', error);
        }
    }

    /**
     * Affiche une erreur de menu
     */
    showMenuError(element, message) {
        this.hideMenuLoading(element);
        
        // Créer une alerte d'erreur
        const alert = this.createAlert('error', message);
        this.insertAlertAfterElement(alert, element);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    /**
     * Affiche une erreur de bouton
     */
    showButtonError(button, message) {
        // Ajouter la classe d'erreur
        button.classList.add('error');
        
        // Afficher un tooltip d'erreur
        const tooltip = this.createTooltip('error', message);
        button.appendChild(tooltip);
        
        // Auto-suppression après 3 secondes
        setTimeout(() => {
            button.classList.remove('error');
            tooltip.remove();
        }, 3000);
    }

    /**
     * Affiche une erreur de formulaire
     */
    showFormError(form, message) {
        this.hideFormLoading(form);
        
        // Créer une alerte d'erreur
        const alert = this.createAlert('error', message);
        form.insertBefore(alert, form.firstChild);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    /**
     * Affiche l'état de chargement pour un menu
     */
    showMenuLoading(element) {
        element.classList.add('loading');
        if (!element.querySelector('.loading-spinner')) {
            const spinner = this.createSpinner();
            element.appendChild(spinner);
        }
    }

    /**
     * Masque l'état de chargement pour un menu
     */
    hideMenuLoading(element) {
        element.classList.remove('loading');
        const spinner = element.querySelector('.loading-spinner');
        if (spinner) {
            spinner.remove();
        }
    }

    /**
     * Affiche l'état de chargement pour un formulaire
     */
    showFormLoading(form) {
        const buttons = form.querySelectorAll('button[type="submit"]');
        buttons.forEach(button => {
            button.disabled = true;
            button.classList.add('loading');
            if (!button.querySelector('.loading-spinner')) {
                const spinner = this.createSpinner();
                button.appendChild(spinner);
            }
        });
    }

    /**
     * Masque l'état de chargement pour un formulaire
     */
    hideFormLoading(form) {
        const buttons = form.querySelectorAll('button[type="submit"]');
        buttons.forEach(button => {
            button.disabled = false;
            button.classList.remove('loading');
            const spinner = button.querySelector('.loading-spinner');
            if (spinner) {
                spinner.remove();
            }
        });
    }

    /**
     * Affiche une erreur réseau
     */
    showNetworkError() {
        const alert = this.createAlert('warning', 'Connexion réseau perdue. Certaines fonctionnalités peuvent ne pas fonctionner.');
        document.body.appendChild(alert);
    }

    /**
     * Masque une erreur réseau
     */
    hideNetworkError() {
        const alert = document.querySelector('.alert-network');
        if (alert) {
            alert.remove();
        }
    }

    /**
     * Gère les erreurs globales
     */
    handleGlobalError(error) {
        console.error('Global error:', error);
        
        // Afficher une alerte générique en production
        if (window.location.hostname !== 'localhost') {
            const alert = this.createAlert('error', 'Une erreur technique est survenue. Veuillez réessayer plus tard.');
            document.body.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    }

    /**
     * Crée une alerte
     */
    createAlert(type, message) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible`;
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
     * Crée un tooltip
     */
    createTooltip(type, message) {
        const tooltip = document.createElement('div');
        tooltip.className = `tooltip tooltip-${type}`;
        tooltip.textContent = message;
        return tooltip;
    }

    /**
     * Crée un spinner de chargement
     */
    createSpinner() {
        const spinner = document.createElement('span');
        spinner.className = 'loading-spinner';
        spinner.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12a9 9 0 11-6.219-8.56"/>
            </svg>
        `;
        return spinner;
    }

    /**
     * Insère une alerte après un élément
     */
    insertAlertAfterElement(alert, element) {
        if (element.parentNode) {
            element.parentNode.insertBefore(alert, element.nextSibling);
        }
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

    /**
     * Vérifie les permissions (simplifié)
     */
    hasPermission(permission) {
        // Implémentation simplifiée - dans un vrai projet, vérifier avec le backend
        return true;
    }

    /**
     * Valide un formulaire
     */
    validateForm(form) {
        // Validation HTML5 native
        if (!form.checkValidity()) {
            form.reportValidity();
            return false;
        }
        
        // Validation personnalisée supplémentaire
        const requiredFields = form.querySelectorAll('[required]');
        for (const field of requiredFields) {
            if (!field.value.trim()) {
                field.focus();
                return false;
            }
        }
        
        return true;
    }

    /**
     * Gère les boutons de soumission
     */
    handleSubmitButton(button, event) {
        const form = button.closest('form');
        if (form && !form.checkValidity()) {
            event.preventDefault();
            this.showButtonError(button, 'Veuillez corriger les erreurs du formulaire');
        }
    }

    /**
     * Gère les boutons d'action
     */
    handleActionButton(button, event) {
        const action = button.getAttribute('data-action');
        const confirm = button.getAttribute('data-confirm');
        
        if (confirm && !window.confirm(confirm)) {
            event.preventDefault();
            return;
        }
        
        // Logique spécifique à l'action
        switch (action) {
            case 'delete':
                this.handleDeleteAction(button, event);
                break;
            case 'export':
                this.handleExportAction(button, event);
                break;
            // Ajouter d'autres actions au besoin
        }
    }

    /**
     * Gère les actions de suppression
     */
    handleDeleteAction(button, event) {
        const confirm = button.getAttribute('data-confirm') || 'Êtes-vous sûr de vouloir supprimer ?';
        if (!window.confirm(confirm)) {
            event.preventDefault();
        }
    }

    /**
     * Gère les actions d'export
     */
    handleExportAction(button, event) {
        try {
            button.classList.add('loading');
            // Logique d'export à implémenter
        } catch (error) {
            this.showButtonError(button, 'Erreur lors de l\'export');
        }
    }
}

// Initialiser le gestionnaire d'erreurs quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    window.menuErrorHandler = new MenuErrorHandler();
});

// Exporter pour utilisation externe si nécessaire
window.MenuErrorHandler = MenuErrorHandler;
