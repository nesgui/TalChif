// Service de notifications globales

// Prevent duplicate script loading
if (window.NotificationService) {
    console.log('[NOTIFICATION DEBUG] NotificationService already loaded, skipping...');
} else {
    window.NotificationService = class NotificationService {
        constructor() {
            this.container = null;
            this.init();
        }

    init() {
        // Créer le conteneur de notifications s'il n'existe pas
        if (!document.getElementById('notifications-container')) {
            this.container = document.createElement('div');
            this.container.id = 'notifications-container';
            this.container.className = 'notifications-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('notifications-container');
        }
    }

    show(type, title, message, options = {}) {
        const {
            duration = 5000,
            progress = false,
            persistent = false
        } = options;

        // Créer directement la notification sans passer par les événements
        this.createDirectNotification(type, title, message, duration, progress);
    }

    createDirectNotification(type, title, message, duration, progress) {
        const container = document.getElementById('notifications-container');
        if (!container) {
            console.error('Conteneur de notifications non trouvé');
            return;
        }

        const notification = this.createNotificationElement(type, title, message, progress);
        container.appendChild(notification);
        
        // Animation d'entrée
        requestAnimationFrame(() => {
            notification.classList.add('notification', type);
        });

        // Barre de progression si nécessaire
        if (progress) {
            this.showProgress(notification);
        }

        // Auto-suppression
        if (duration > 0) {
            setTimeout(() => {
                this.hideNotificationElement(notification);
            }, duration);
        }
    }

    escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    createNotificationElement(type, title, message, progress) {
        const notification = document.createElement('div');
        notification.className = 'notification';
        
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        const safeTitle = this.escapeHtml(String(title));
        const safeMessage = this.escapeHtml(String(message));

        notification.innerHTML = `
            <div class="notification-header">
                <span class="notification-icon">${icons[type] || 'ℹ️'}</span>
                <span class="notification-title">${safeTitle}</span>
                <button class="notification-close" onclick="this.closest('.notification').remove()" aria-label="Fermer">&times;</button>
            </div>
            <div class="notification-body">${safeMessage}</div>
            ${progress ? '<div class="notification-progress"><div class="notification-progress-bar"></div></div>' : ''}
        `;

        return notification;
    }

    hideNotificationElement(notification) {
        if (notification && notification.parentNode) {
            notification.classList.add('removing');
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }

    showProgress(notification) {
        const progressBar = notification.querySelector('.notification-progress-bar');
        if (progressBar) {
            progressBar.style.width = '0%';
            requestAnimationFrame(() => {
                progressBar.style.width = '100%';
            });
        }
    }

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

    progress(title, message, options = {}) {
        return this.show('info', title, message, { ...options, progress: true });
    }

    clear() {
        const event = new CustomEvent('notification:clear');
        document.dispatchEvent(event);
    }

    // Méthodes utilitaires pour les événements courants
    loginSuccess(userName) {
        return this.success('Connexion réussie', `Bienvenue ${userName} !`, { duration: 4000 });
    }

    loginError(message = 'Email ou mot de passe incorrect') {
        return this.error('Échec de connexion', message, { duration: 6000 });
    }

    logoutSuccess() {
        return this.info('Déconnexion', 'Vous avez été déconnecté avec succès', { duration: 3000 });
    }

    registrationSuccess() {
        return this.success('Inscription réussie', 'Votre compte a été créé avec succès', { duration: 4000 });
    }

    registrationError(message) {
        return this.error('Erreur d\'inscription', message, { duration: 6000 });
    }

    addToCartSuccess(eventName) {
        return this.success('Ajout au panier', `${eventName} a été ajouté à votre panier`, { duration: 3000 });
    }

    removeFromCartSuccess() {
        return this.info('Panier modifié', 'L\'article a été retiré du panier', { duration: 3000 });
    }

    cartCleared() {
        return this.info('Panier vidé', 'Votre panier a été vidé', { duration: 3000 });
    }

    accessDenied() {
        return this.error('Accès refusé', 'Vous n\'avez pas les permissions nécessaires', { duration: 5000 });
    }
}

// Exporter pour utilisation globale
window.NotificationService = new NotificationService();

// Afficher les flash messages dès que le service est prêt (évite les races avec defer)
(function showPendingFlashes() {
    if (!window.flashMessages || window.flashMessages.length === 0) return;
    var ns = window.NotificationService;
    if (!ns) return;
    window.flashMessages.forEach(function(flash) {
        var title = flash.type === 'success' ? 'Succès' : flash.type === 'error' ? 'Erreur' : flash.type === 'warning' ? 'Attention' : 'Information';
        var duration = flash.type === 'error' ? 6000 : flash.type === 'warning' ? 5000 : 4000;
        if (flash.type === 'success') {
            ns.success(title, flash.message, { duration: duration });
        } else if (flash.type === 'error') {
            ns.error(title, flash.message, { duration: duration });
        } else if (flash.type === 'warning') {
            ns.warning(title, flash.message, { duration: duration });
        } else {
            ns.info(title, flash.message, { duration: duration });
        }
    });
    window.flashMessages = [];
})();

} // Close the else block from duplicate check
