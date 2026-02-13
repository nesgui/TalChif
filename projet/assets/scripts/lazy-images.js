/**
 * Script pour améliorer le lazy loading des images avec fade-in.
 * Compatible avec l'attribut natif loading="lazy" et ajoute une transition fluide.
 */
(function() {
    'use strict';
    
    // Fonction pour gérer le chargement des images lazy
    function initLazyImages() {
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');
        
        if ('loading' in HTMLImageElement.prototype) {
            // Le navigateur supporte le lazy loading natif
            lazyImages.forEach(img => {
                img.addEventListener('load', function() {
                    this.classList.add('loaded');
                }, { once: true });
                
                // Si l'image est déjà chargée (cache)
                if (img.complete && img.naturalHeight !== 0) {
                    img.classList.add('loaded');
                }
            });
        } else {
            // Fallback : Intersection Observer pour les navigateurs plus anciens
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        img.classList.remove('lazy');
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            });
            
            lazyImages.forEach(img => {
                imageObserver.observe(img);
            });
        }
    }
    
    // Initialiser au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLazyImages);
    } else {
        initLazyImages();
    }
})();
