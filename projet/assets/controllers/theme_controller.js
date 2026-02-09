import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["toggle"];
    static values = {
        theme: { type: String, default: "light" }
    };

    // Cycle des thèmes: light → dark → light
    themes = ['light', 'dark'];
    
    // Icônes pour chaque thème
    icons = {
        light: '☀️',
        dark: '🌙'
    };
    
    // Labels pour chaque thème
    labels = {
        light: 'Thème clair (cliquer pour thème sombre)', 
        dark: 'Thème sombre (cliquer pour thème clair)'
    };

    connect() {
        // Charger le thème sauvegardé au démarrage
        this.loadTheme();
    }

    toggle() {
        // Obtenir l'index du thème actuel
        const currentIndex = this.themes.indexOf(this.themeValue);
        // Calculer l'index du prochain thème
        const nextIndex = (currentIndex + 1) % this.themes.length;
        const nextTheme = this.themes[nextIndex];
        
        // Appliquer le nouveau thème
        this.themeValue = nextTheme;
        this.applyTheme(nextTheme);
        this.saveTheme(nextTheme);
        
        // Animation de transition
        this.animateTransition();
    }

    loadTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        this.themeValue = savedTheme;
        this.applyTheme(savedTheme);
    }

    saveTheme(theme) {
        localStorage.setItem('theme', theme);
    }

    applyTheme(theme) {
        const html = document.documentElement;
        const toggle = this.toggleTarget;
        
        // Appliquer le thème
        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
        
        // Mettre à jour le bouton
        if (toggle) {
            this.updateToggleIcon(theme);
        }
    }

    updateToggleIcon(theme) {
        const toggle = this.toggleTarget;
        if (!toggle) return;

        const title = this.labels[theme];
        
        toggle.setAttribute('title', title);
        toggle.setAttribute('data-theme', theme);
        toggle.setAttribute('aria-label', title);
        
        // Animation fluide des icônes
        requestAnimationFrame(() => {
            toggle.innerHTML = `
                <span class="icon-sun">${this.icons.light}</span>
                <span class="icon-moon">${this.icons.dark}</span>
            `;
        });
    }

    isDarkMode() {
        return this.themeValue === 'dark';
    }

    // Méthodes utilitaires pour debugging
    getCurrentTheme() {
        return this.themeValue;
    }
}
