import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["container"];

    connect() {
        // Écouter les événements de notification personnalisés
        document.addEventListener('notification:show', this.showNotification.bind(this));
        document.addEventListener('notification:hide', this.hideNotification.bind(this));
        document.addEventListener('notification:clear', this.clearAll.bind(this));
    }

    showNotification(event) {
        const { type, title, message, duration = 5000, progress = false } = event.detail;
        
        const notification = this.createNotification(type, title, message, progress);
        this.containerTarget.appendChild(notification);
        
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
                this.hideNotification({ target: notification });
            }, duration);
        }
    }

    hideNotification(event) {
        const notification = event.target || event.detail?.target;
        if (notification && notification.parentNode) {
            notification.classList.add('removing');
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }

    clearAll() {
        const notifications = this.containerTarget.querySelectorAll('.notification');
        notifications.forEach((notification, index) => {
            setTimeout(() => {
                notification.classList.add('removing');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, index * 50);
        });
    }

    createNotification(type, title, message, progress) {
        const notification = document.createElement('div');
        notification.className = 'notification';
        
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        notification.innerHTML = `
            <div class="notification-header">
                <span class="notification-icon">${icons[type] || 'ℹ️'}</span>
                <span class="notification-title">${title}</span>
                <button class="notification-close" data-action="click->notification#hide">&times;</button>
            </div>
            <div class="notification-body">${message}</div>
            ${progress ? '<div class="notification-progress"><div class="notification-progress-bar"></div></div>' : ''}
        `;

        return notification;
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
}
