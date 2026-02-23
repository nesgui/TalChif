import './stimulus_bootstrap.js';
import '@hotwired/turbo';

/**
 * Fichier principal JavaScript de l'application TalChif.
 *
 * - Turbo Drive + Turbo Frames pour navigation SPA-like dans les dashboards
 * - Shim DOMContentLoaded pour compatibilité avec les scripts inline des Turbo Frames
 * - Améliorations d'accessibilité
 */

// Shim : quand un script inline est évalué dans un Turbo Frame,
// DOMContentLoaded a déjà été émis. Ce shim exécute immédiatement
// les callbacks si le DOM est déjà prêt (même comportement que jQuery.ready).
const _origAddEventListener = document.addEventListener.bind(document);
document.addEventListener = function(type, listener, options) {
    if (type === 'DOMContentLoaded' && document.readyState !== 'loading') {
        setTimeout(listener, 0);
        return;
    }
    return _origAddEventListener(type, listener, options);
};

// Fallback : si une réponse Turbo Frame ne contient pas le frame attendu
// (ex: redirect après soumission de formulaire), naviguer en pleine page.
document.addEventListener('turbo:frame-missing', function(event) {
    event.preventDefault();
    event.detail.visit(event.detail.response);
});

// Fermer le sidebar mobile après navigation dans le Turbo Frame dashboard
document.addEventListener('turbo:frame-render', function(e) {
    if (e.target.id === 'dashboard-main-frame') {
        const toggle = document.getElementById('dashboard-menu');
        if (toggle) toggle.checked = false;
    }
});

// Désactiver les transitions si l'utilisateur préfère les réduire
if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.style.setProperty('--transition-rapide', '0ms');
    document.documentElement.style.setProperty('--transition-normale', '0ms');
    document.documentElement.style.setProperty('--transition-lente', '0ms');
}

function initPublicMeMenu() {
    const trigger = document.querySelector('.public-me-trigger');
    const dropdown = document.getElementById('public-me-dropdown');
    if (!trigger || !dropdown) return;

    function isMobile() {
        return window.matchMedia('(max-width: 719px)').matches;
    }

    function setOpen(open) {
        dropdown.classList.toggle('is-open', open);
        dropdown.setAttribute('aria-hidden', open ? 'false' : 'true');
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function toggle() {
        const open = dropdown.classList.contains('is-open');
        setOpen(!open);
    }

    trigger.addEventListener('click', function(e) {
        if (isMobile()) {
            return;
        }
        e.preventDefault();
        toggle();
    });

    document.addEventListener('click', function(e) {
        if (!dropdown.classList.contains('is-open')) return;
        if (dropdown.contains(e.target) || trigger.contains(e.target)) return;
        setOpen(false);
    });

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (!dropdown.classList.contains('is-open')) return;
        setOpen(false);
    });

    window.addEventListener('resize', function() {
        if (dropdown.classList.contains('is-open') && isMobile()) {
            setOpen(false);
        }
    });
}

document.addEventListener('turbo:load', initPublicMeMenu);
document.addEventListener('DOMContentLoaded', initPublicMeMenu);
