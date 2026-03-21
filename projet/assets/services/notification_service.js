/**
 * Service de Notifications Moderne - TalChif
 * Système centralisé, accessible (WCAG 2.2) et performant
 * Sans dépendances externes (Toastr remplacé)
 * @version 2.0 - Février 2026
 */

// Vérification si le service existe déjà
if (window.NotificationService) {
    export { window.NotificationService as NotificationService };
} else {

class NotificationService {
    constructor() {
        this.container = null;
        this.notifications = new Map(); // Tracking pour éviter doublons
        this.maxVisible = 5;
        this.defaultDuration = 5000;
        this.queue = [];
        this.isInitialized = false;
    }

    /**
     * Initialisation du service
     */
    init() {
        if (this.isInitialized) {
                return;
        }

        // Vérifier que document.body existe
        if (!document.body) {
            setTimeout(() => this.init(), 50);
            return;
        }
        
        // Créer le conteneur de notifications s'il n'existe pas
        this.container = document.getElementById('notifications-container');
        
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'notifications-container';
            this.container.className = 'notifications-container';
            this.container.setAttribute('role', 'region');
            this.container.setAttribute('aria-label', 'Notifications');
            this.container.setAttribute('aria-live', 'polite');
            this.container.setAttribute('aria-atomic', 'false');
            document.body.appendChild(this.container);
        }

        this.isInitialized = true;
        this.processQueue();
    }

    /**
     * Afficher une notification
     * @param {string} type - Type: success, error, warning, info
     * @param {string} title - Titre de la notification
     * @param {string} message - Message de la notification
     * @param {object} options - Options supplémentaires
     */
    show(type, title, message, options = {}) {
        // Assurer l'initialisation
        if (!this.isInitialized) {
            this.queue.push({ type, title, message, options });
            this.init();
            return;
        }

        const {
            duration = this.getDurationByType(type),
            closable = true,
            progress = true,
            action = null, // { label: string, callback: function }
            id = null
        } = options;

        // Prévenir les doublons
        const notifId = id || this.generateId(type, message);
        if (this.notifications.has(notifId)) {
            return;
        }

        // Limiter le nombre de notifications visibles
        if (this.notifications.size >= this.maxVisible) {
            this.removeOldest();
        }

        // Créer l'élément de notification
        const notification = this.createNotificationElement(
            type,
            title,
            message,
            { duration, closable, progress, action, id: notifId }
        );

        // Ajouter au DOM avec animation
        this.container.appendChild(notification);
        this.notifications.set(notifId, notification);

        // Animation d'entrée
        requestAnimationFrame(() => {
            notification.classList.add('notification-enter');
        });

        // Auto-dismiss avec barre de progression
        if (duration > 0) {
            if (progress) {
                this.animateProgress(notification, duration);
            }
            
            setTimeout(() => {
                this.hide(notifId);
            }, duration);
        }

        // Annoncer aux lecteurs d'écran
        this.announceToScreenReader(type, title, message);
    }

    /**
     * Créer l'élément HTML de la notification
     */
    createNotificationElement(type, title, message, options) {
        const { closable, progress, action, id } = options;
        const notification = document.createElement('div');
        
        notification.className = `notification notification-${type}`;
        notification.setAttribute('role', this.getRoleByType(type));
        notification.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        notification.setAttribute('aria-atomic', 'true');
        notification.setAttribute('data-notification-id', id);

        // Icône SVG
        const icon = this.getIconSVG(type);

        // Structure HTML
        notification.innerHTML = `
            <div class="notification-content">
                <div class="notification-icon" aria-hidden="true">
                    ${icon}
                </div>
                <div class="notification-body">
                    <div class="notification-title">${this.escapeHtml(title)}</div>
                    <div class="notification-message">${this.escapeHtml(message)}</div>
                    ${action ? `<button class="notification-action" data-action="custom">${this.escapeHtml(action.label)}</button>` : ''}
                </div>
                ${closable ? `
                    <button class="notification-close" 
                            aria-label="Fermer la notification"
                            data-action="close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <line x1="12" y1="4" x2="4" y2="12"/>
                            <line x1="4" y1="4" x2="12" y2="12"/>
                        </svg>
                    </button>
                ` : ''}
            </div>
            ${progress ? '<div class="notification-progress"><div class="notification-progress-bar"></div></div>' : ''}
        `;

        // Événements
        if (closable) {
            const closeBtn = notification.querySelector('[data-action="close"]');
            closeBtn.addEventListener('click', () => this.hide(id));
        }

        if (action) {
            const actionBtn = notification.querySelector('[data-action="custom"]');
            actionBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                action.callback();
                this.hide(id);
            });
        }

        // Pause auto-dismiss au survol
        notification.addEventListener('mouseenter', () => {
            notification.classList.add('notification-paused');
        });

        notification.addEventListener('mouseleave', () => {
            notification.classList.remove('notification-paused');
        });

        return notification;
    }

    /**
     * Masquer une notification
     */
    hide(id) {
        const notification = this.notifications.get(id);
        if (!notification || !notification.parentNode) {
            return;
        }

        notification.classList.add('notification-exit');
        
        setTimeout(() => {
            if (notification.parentNode) {
                this.container.removeChild(notification);
            }
            this.notifications.delete(id);
        }, 300);
    }

    /**
     * Supprimer toutes les notifications
     */
    clear() {
        const ids = Array.from(this.notifications.keys());
        ids.forEach((id, index) => {
            setTimeout(() => {
                this.hide(id);
            }, index * 50); // Animation en cascade
        });
    }

    /**
     * Supprimer la plus ancienne notification
     */
    removeOldest() {
        const firstId = this.notifications.keys().next().value;
        if (firstId) {
            this.hide(firstId);
        }
    }

    /**
     * Traiter la file d'attente
     */
    processQueue() {
        while (this.queue.length > 0) {
            const { type, title, message, options } = this.queue.shift();
            this.show(type, title, message, options);
        }
    }

    /**
     * Animer la barre de progression
     */
    animateProgress(notification, duration) {
        const progressBar = notification.querySelector('.notification-progress-bar');
        if (!progressBar) return;

        progressBar.style.transition = `width ${duration}ms linear`;
        
        requestAnimationFrame(() => {
            progressBar.style.width = '100%';
        });

        // Pause/resume au survol
        notification.addEventListener('mouseenter', () => {
            const computedStyle = window.getComputedStyle(progressBar);
            const currentWidth = computedStyle.width;
            progressBar.style.width = currentWidth;
            progressBar.style.transition = 'none';
        });

        notification.addEventListener('mouseleave', () => {
            const currentWidth = parseFloat(progressBar.style.width);
            const remainingTime = (currentWidth / notification.offsetWidth) * duration;
            progressBar.style.transition = `width ${remainingTime}ms linear`;
            progressBar.style.width = '100%';
        });
    }

    /**
     * Annoncer aux lecteurs d'écran (région aria-live)
     */
    announceToScreenReader(type, title, message) {
        // L'annonce se fait automatiquement via aria-live
        // Pas besoin d'action supplémentaire
    }

    /**
     * Générer un ID unique pour la notification
     */
    generateId(type, message) {
        return `${type}-${Date.now()}-${message.substring(0, 20).replace(/\s/g, '-')}`;
    }

    /**
     * Obtenir la durée par type
     */
    getDurationByType(type) {
        const durations = {
            success: 4000,
            error: 7000,
            warning: 6000,
            info: 5000
        };
        return durations[type] || this.defaultDuration;
    }

    /**
     * Obtenir le rôle ARIA par type
     */
    getRoleByType(type) {
        return type === 'error' ? 'alert' : 'status';
    }

    /**
     * Obtenir l'icône SVG par type
     */
    getIconSVG(type) {
        const icons = {
            success: `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            `,
            error: `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            `,
            warning: `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            `,
            info: `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            `
        };
        return icons[type] || icons.info;
    }

    /**
     * Échapper HTML pour sécurité XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================
    // API PUBLIQUE - Méthodes de convenance
    // ============================================

    success(title, message, options = {}) {
        return this.show('success', title, message, options);
    }

    error(title, message, options = {}) {
        return this.show('error', title, message, options);
    }

    warning(title, message, options = {}) {
        return this.show('warning', title, message, options);
    }

    info(title, message, options = {}) {
        return this.show('info', title, message, options);
    }

    // ============================================
    // MÉTHODES UTILITAIRES MÉTIER
    // ============================================

    loginSuccess(userName) {
        return this.success('Connexion réussie', `Bienvenue ${userName} !`);
    }

    loginError(message = 'Email ou mot de passe incorrect') {
        return this.error('Échec de connexion', message);
    }

    logoutSuccess() {
        return this.info('Déconnexion', 'Vous avez été déconnecté avec succès');
    }

    registrationSuccess() {
        return this.success('Inscription réussie', 'Votre compte a été créé avec succès');
    }

    registrationError(message) {
        return this.error('Erreur d\'inscription', message);
    }

    addToCartSuccess(eventName) {
        return this.success('Ajout au panier', `${eventName} a été ajouté à votre panier`, {
            duration: 3000,
            action: {
                label: 'Voir le panier',
                callback: () => window.location.href = '/panier'
            }
        });
    }

    removeFromCartSuccess() {
        return this.info('Panier modifié', 'L\'article a été retiré du panier');
    }

    cartCleared() {
        return this.info('Panier vidé', 'Votre panier a été vidé');
    }

    accessDenied() {
        return this.error('Accès refusé', 'Vous n\'avez pas les permissions nécessaires');
    }

    uploadProgress(fileName) {
        return this.info('Téléchargement', `Envoi de ${fileName} en cours...`, {
            duration: 0, // Manuel
            progress: true
        });
    }

    uploadSuccess(fileName) {
        return this.success('Téléchargement réussi', `${fileName} a été envoyé avec succès`);
    }

    uploadError(fileName, error) {
        return this.error('Erreur de téléchargement', `Échec de l'envoi de ${fileName}: ${error}`);
    }

    formValidationError() {
        return this.error('Formulaire invalide', 'Veuillez corriger les erreurs avant de continuer', {
            duration: 6000
        });
    }

    networkError() {
        return this.error('Erreur réseau', 'Impossible de se connecter au serveur. Vérifiez votre connexion.', {
            duration: 8000
        });
    }

    sessionExpired() {
        return this.warning('Session expirée', 'Votre session a expiré. Veuillez vous reconnecter.', {
            duration: 0, // Reste jusqu'à fermeture manuelle
            action: {
                label: 'Se reconnecter',
                callback: () => window.location.href = '/login'
            }
        });
    }
}

// Créer instance singleton
const notificationService = new NotificationService();

window.NotificationService = notificationService;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => notificationService.init());
} else {
    notificationService.init();
}

// Afficher les flash messages dès que le service est prêt
if (window.flashMessages && window.flashMessages.length > 0) {
    window.flashMessages.forEach(flash => {
        const typeMap = {
            success: 'success',
            error: 'error',
            warning: 'warning',
            info: 'info',
            danger: 'error' // Alias Symfony
        };
        
        const type = typeMap[flash.type] || 'info';
        const title = {
            success: 'Succès',
            error: 'Erreur',
            warning: 'Attention',
            info: 'Information'
        }[type];

        notificationService.show(type, title, flash.message);
    });
    
    // Nettoyer après affichage
    window.flashMessages = [];
}

// Export pour ES6 modules
export { NotificationService };

} // Fin du else
