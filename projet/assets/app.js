import './stimulus_bootstrap.js';

/**
 * Fichier principal JavaScript de l'application TalChif.
 * 
 * Initialise les fonctionnalités modernes :
 * - View Transitions API pour navigation fluide
 * - Gestion des états de chargement
 * - Améliorations d'accessibilité
 */

// View Transitions API (navigation fluide entre pages) - 2026 standard
if ('startViewTransition' in document) {
    // Intercepter les clics sur les liens pour des transitions fluides
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        if (!link || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }
        
        const href = link.getAttribute('href');
        // Ne pas intercepter les liens externes ou les ancres
        if (href && (href.startsWith('#') || href.startsWith('http') && !href.includes(window.location.hostname))) {
            return;
        }
        
        e.preventDefault();
        document.startViewTransition(() => {
            window.location.href = href;
        });
    });
}

// Désactiver les transitions si l'utilisateur préfère les réduire
if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.style.setProperty('--transition-rapide', '0ms');
    document.documentElement.style.setProperty('--transition-normale', '0ms');
    document.documentElement.style.setProperty('--transition-lente', '0ms');
}
