import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["toggle"];
    static values = {
        theme: { type: String, default: "light" }
    };

    // Cycle des thèmes: light → dark → light
    themes = ['light', 'dark'];
    
    // Icônes SVG (blanc via currentColor) – style switch moderne
    icons = {
        light: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="12" r="4" fill="currentColor"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="6.34" y1="17.66" x2="4.93" y2="19.07"/><line x1="19.07" y1="4.93" x2="17.66" y2="6.34"/></svg>',
        dark: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>'
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
        
        this.themeValue = nextTheme;
        this.applyTheme(nextTheme);
        this.saveTheme(nextTheme);
    }

    loadTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (!this.themes.includes(savedTheme)) {
            this.saveTheme('light');
            this.themeValue = 'light';
            this.applyTheme('light');
            return;
        }
        this.themeValue = savedTheme;
        this.applyTheme(savedTheme);
    }

    saveTheme(theme) {
        localStorage.setItem('theme', theme);
    }

    applyTheme(theme) {
        const html = document.documentElement;
        const toggle = this.toggleTarget;

        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
            html.style.colorScheme = 'dark';
        } else {
            html.removeAttribute('data-theme');
            html.style.colorScheme = 'light';
        }

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
                <span class="icon-sun" aria-hidden="true">${this.icons.light}</span>
                <span class="icon-moon" aria-hidden="true">${this.icons.dark}</span>
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
