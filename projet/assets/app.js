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

(function initDashboardSidebarDialog() {
    let dashboardSidebarDialogBound = false;

    function setExpandedState(isExpanded) {
        document.querySelectorAll('[data-dashboard-open]').forEach((button) => {
            button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });
    }

    function closeDialog(dialog) {
        if (!dialog || !dialog.open) {
            setExpandedState(false);
            return;
        }

        dialog.close();
        setExpandedState(false);
    }

    function bindOnce() {
        if (dashboardSidebarDialogBound) return;
        dashboardSidebarDialogBound = true;

        document.addEventListener('click', function(event) {
            const openButton = event.target.closest('[data-dashboard-open]');
            const closeButton = event.target.closest('[data-dashboard-close]');
            const dialog = document.getElementById('dashboard-sidebar-dialog');

            if (!dialog) return;

            if (openButton) {
                if (!window.matchMedia('(max-width: 980px)').matches) {
                    setExpandedState(false);
                    return;
                }

                if (dialog.open) {
                    closeDialog(dialog);
                } else {
                    dialog.showModal();
                    setExpandedState(true);
                }
                return;
            }

            if (closeButton) {
                closeDialog(dialog);
            }
        });

        document.addEventListener('click', function(event) {
            const dialog = document.getElementById('dashboard-sidebar-dialog');
            if (!dialog || !dialog.open || event.target !== dialog) return;
            closeDialog(dialog);
        });

        document.addEventListener('close', function(event) {
            if (event.target.id !== 'dashboard-sidebar-dialog') return;
            setExpandedState(false);
        }, true);

        window.addEventListener('resize', function() {
            const dialog = document.getElementById('dashboard-sidebar-dialog');
            if (!dialog || !dialog.open) return;
            if (!window.matchMedia('(max-width: 980px)').matches) {
                closeDialog(dialog);
            }
        });

        document.addEventListener('turbo:frame-render', function(e) {
            if (e.target.id !== 'dashboard-main-frame') return;
            closeDialog(document.getElementById('dashboard-sidebar-dialog'));
        });
    }

    document.addEventListener('turbo:load', bindOnce);
    document.addEventListener('DOMContentLoaded', bindOnce);
    if (document.readyState !== 'loading') bindOnce();
})();

// Recharger les images après navigation Turbo pour corriger le problème d'affichage
document.addEventListener('turbo:load', function() {
    console.log('🔄 Turbo:load - Rechargement des images...');
    
    // Forcer le rechargement de TOUTES les images, pas seulement celles cassées
    const images = document.querySelectorAll('img');
    let reloadedCount = 0;
    
    images.forEach((img, index) => {
        // Sauvegarder l'URL originale
        const originalSrc = img.src;
        
        // Forcer le rechargement avec un timestamp unique
        setTimeout(() => {
            if (originalSrc && originalSrc !== window.location.href) {
                const separator = originalSrc.includes('?') ? '&' : '?';
                const timestamp = Date.now() + index; // Unique pour chaque image
                const newSrc = originalSrc + separator + 'turbo_reload=' + timestamp;
                
                console.log(`🖼️ Rechargement image ${index}: ${originalSrc} -> ${newSrc}`);
                img.src = newSrc;
                reloadedCount++;
                
                // Si l'image ne se charge pas, essayer de restaurer l'original
                img.onerror = function() {
                    console.warn(`❌ Erreur rechargement image, restauration: ${originalSrc}`);
                    this.src = originalSrc;
                };
            }
        }, index * 50); // Délai progressif pour éviter la surcharge
    });
    
    console.log(`📊 ${images.length} images trouvées, rechargement en cours...`);
});

// S'assurer que les images sont visibles après le rendu Turbo
document.addEventListener('turbo:render', function() {
    console.log('🎨 Turbo:render - Forçage affichage images...');
    
    // Forcer la réaffichage des images avec plusieurs techniques
    setTimeout(() => {
        const images = document.querySelectorAll('img');
        images.forEach((img, index) => {
            // Technique 1: Forcer le repaint
            img.style.opacity = '0.99';
            setTimeout(() => {
                img.style.opacity = '1';
            }, 10);
            
            // Technique 2: Forcer le reflow
            const originalDisplay = img.style.display;
            img.style.display = 'none';
            img.offsetHeight; // Forcer le reflow
            img.style.display = originalDisplay || '';
            
            // Technique 3: Forcer le chargement si src est vide
            if (!img.src || img.src === '') {
                const dataSrc = img.getAttribute('data-src');
                if (dataSrc) {
                    img.src = dataSrc;
                }
            }
        });
        
        console.log(`✅ ${images.length} images traitées pour affichage`);
    }, 200);
});

// Écouter aussi les changements de visibilité de la page
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        console.log('👁️ Page redevenue visible - vérification images...');
        setTimeout(() => {
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                if (!img.complete || img.naturalWidth === 0) {
                    const originalSrc = img.src;
                    img.src = originalSrc + '&visibility_check=' + Date.now();
                }
            });
        }, 100);
    }
});

// Désactiver les transitions si l'utilisateur préfère les réduire
if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.style.setProperty('--transition-rapide', '0ms');
    document.documentElement.style.setProperty('--transition-normale', '0ms');
    document.documentElement.style.setProperty('--transition-lente', '0ms');
}

// Menu "Moi" : délégation d'événements (fonctionne après navigation Turbo, mobile inclus)
(function initPublicMeMenu() {
    let meMenuBound = false;

    function bindOnce() {
        if (meMenuBound) return;
        meMenuBound = true;

        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.public-me-trigger');
            const dropdown = document.getElementById('public-me-dropdown');
            if (!trigger || !dropdown) return;

            console.log('🖱️ Clic détecté sur trigger "moi"');
            console.log('🔍 Target:', e.target);
            console.log('🔍 Trigger:', trigger);
            console.log('🔍 Dropdown trouvé:', !!dropdown);

            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            const isOpen = dropdown.classList.contains('is-open');
            console.log('📂 État actuel dropdown:', isOpen, 'nouvel état:', !isOpen);
            
            dropdown.classList.toggle('is-open', !isOpen);
            dropdown.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
            trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            
            console.log('✅ Dropdown traité, return false');
            return false; // Double sécurité
        }, true);

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('public-me-dropdown');
            const trigger = document.querySelector('.public-me-trigger');
            if (!dropdown || !dropdown.classList.contains('is-open')) return;
            if (dropdown.contains(e.target) || (trigger && trigger.contains(e.target))) return;
            dropdown.classList.remove('is-open');
            dropdown.setAttribute('aria-hidden', 'true');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });

        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            const dropdown = document.getElementById('public-me-dropdown');
            const trigger = document.querySelector('.public-me-trigger');
            if (!dropdown || !dropdown.classList.contains('is-open')) return;
            dropdown.classList.remove('is-open');
            dropdown.setAttribute('aria-hidden', 'true');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });

        window.addEventListener('resize', function() {
            const dropdown = document.getElementById('public-me-dropdown');
            const trigger = document.querySelector('.public-me-trigger');
            if (!dropdown || !dropdown.classList.contains('is-open')) return;
            if (window.matchMedia('(max-width: 719px)').matches) {
                dropdown.classList.remove('is-open');
                dropdown.setAttribute('aria-hidden', 'true');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('turbo:load', bindOnce);
    document.addEventListener('DOMContentLoaded', bindOnce);
    if (document.readyState !== 'loading') bindOnce();
})();
