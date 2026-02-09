// Service de notifications globales
class NotificationService {
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

        const event = new CustomEvent('notification:show', {
            detail: { type, title, message, duration, progress }
        });

        document.dispatchEvent(event);

        // Retourner l'ID de la notification pour pouvoir la supprimer manuellement
        return Date.now().toString();
    }

    hide(notificationId) {
        const event = new CustomEvent('notification:hide', {
            detail: { notificationId }
        });

        document.dispatchEvent(event);
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
