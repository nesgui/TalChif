/**
 * Theme Toggle Controller
 * Handles light/dark theme switching for authentication pages and main layout
 */

// Global debug log
const DEBUG = true;
const debugLog = (...args) => {
    if (DEBUG) {
        console.log('[THEME DEBUG]', ...args);
    }
};

debugLog('Theme service script loaded');

class ThemeToggle {
    constructor() {
        debugLog('ThemeToggle constructor called');
        this.themeToggle = null;
        this.themeIconLight = null;
        this.themeIconDark = null;
        this.initialized = false;
        
        this.init();
    }
    
    init() {
        debugLog('ThemeToggle init called, document.readyState:', document.readyState);
        debugLog('Current URL:', window.location.href);
        debugLog('Looking for elements in DOM...');
        
        // Try to find elements immediately
        this.findElements();
        
        if (!this.themeToggle) {
            debugLog('Theme toggle not found, scheduling retry...');
            // If elements not found, wait for DOM and retry multiple times
            this.waitForElements();
        } else {
            debugLog('Theme toggle found immediately');
            this.setupTheme();
        }
    }
    
    waitForElements() {
        debugLog('Starting element wait process...');
        let attempts = 0;
        const maxAttempts = 20;
        const retryInterval = setInterval(() => {
            attempts++;
            debugLog(`Looking for theme toggle elements, attempt ${attempts}/${maxAttempts}`);
            
            this.findElements();
            
            if (this.themeToggle || attempts >= maxAttempts) {
                clearInterval(retryInterval);
                
                if (this.themeToggle) {
                    debugLog('Theme toggle found after retries');
                    this.setupTheme();
                } else {
                    debugLog('ERROR: Theme toggle elements not found after', maxAttempts, 'attempts');
                    debugLog('Available buttons:', document.querySelectorAll('button').length);
                    debugLog('Available elements with IDs:', Array.from(document.querySelectorAll('[id]')).map(el => el.id));
                    debugLog('DOM content preview:', document.body.innerHTML.substring(0, 500));
                }
            }
        }, 300);
    }
    
    findElements() {
        this.themeToggle = document.getElementById('theme-toggle');
        this.themeIconLight = document.querySelector('.theme-icon-light');
        this.themeIconDark = document.querySelector('.theme-icon-dark');
        
        debugLog('Elements found:', {
            toggle: !!this.themeToggle,
            toggleElement: this.themeToggle,
            iconLight: !!this.themeIconLight,
            iconDark: !!this.themeIconDark,
            allButtons: document.querySelectorAll('button').length,
            themeToggleButtons: document.querySelectorAll('#theme-toggle').length
        });
        
        if (this.themeToggle && !this.themeToggle.hasAttribute('data-theme-listener')) {
            debugLog('Adding click listener to theme toggle');
            // Add click handler if toggle exists and doesn't already have a listener
            this.themeToggle.addEventListener('click', (e) => {
                debugLog('THEME TOGGLE CLICKED!');
                e.preventDefault();
                e.stopPropagation();
                this.toggleTheme();
            });
            this.themeToggle.setAttribute('data-theme-listener', 'true');
            debugLog('Theme toggle listener attached successfully');
        } else if (this.themeToggle) {
            debugLog('Theme toggle already has listener');
        }
    }
    
    setupTheme() {
        if (this.initialized) {
            debugLog('Theme already initialized, skipping');
            return;
        }
        
        debugLog('Setting up theme...');
        
        // Load and apply saved theme
        const savedTheme = localStorage.getItem('talchif-theme') || localStorage.getItem('theme') || 'light';
        debugLog('Saved theme from localStorage:', savedTheme);
        debugLog('Available localStorage keys:', Object.keys(localStorage));
        
        this.setTheme(savedTheme);
        this.initialized = true;
        debugLog('Theme setup complete');
    }
    
    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    }
    
    setTheme(theme) {
        debugLog('setTheme called with:', theme);
        debugLog('Current theme before change:', document.documentElement.getAttribute('data-theme'));
        
        // Set theme on HTML element
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.documentElement.style.colorScheme = 'dark';
            debugLog('Applied dark theme to HTML element');
        } else {
            document.documentElement.removeAttribute('data-theme');
            document.documentElement.style.colorScheme = 'light';
            debugLog('Applied light theme to HTML element');
        }
        
        // Verify theme was applied
        const appliedTheme = document.documentElement.getAttribute('data-theme') || 'light';
        debugLog('Theme actually applied:', appliedTheme);
        
        // Save to both storage keys for compatibility
        localStorage.setItem('talchif-theme', theme);
        localStorage.setItem('theme', theme);
        debugLog('Theme saved to localStorage');
        
        // Update icons for auth-style toggle
        if (this.themeIconLight && this.themeIconDark) {
            debugLog('Updating theme icons');
            if (theme === 'dark') {
                this.themeIconLight.style.display = 'none';
                this.themeIconDark.style.display = 'block';
                debugLog('Set dark icon visible');
            } else {
                this.themeIconLight.style.display = 'block';
                this.themeIconDark.style.display = 'none';
                debugLog('Set light icon visible');
            }
        } else {
            debugLog('Theme icons not found for updating');
        }
        
        // Update title and aria-label for accessibility
        if (this.themeToggle) {
            const title = theme === 'dark' ? 'Thème sombre (cliquer pour thème clair)' : 'Thème clair (cliquer pour thème sombre)';
            this.themeToggle.setAttribute('title', title);
            this.themeToggle.setAttribute('aria-label', title);
            debugLog('Updated accessibility attributes');
        } else {
            debugLog('Theme toggle button not found for accessibility update');
        }
        
        // Dispatch custom event for other components
        const event = new CustomEvent('themeChanged', { detail: { theme } });
        document.dispatchEvent(event);
        debugLog('Theme changed event dispatched for theme:', theme);
        debugLog('Theme change complete');
    }
}

// Create singleton instance
const themeToggle = new ThemeToggle();

// Export for ES module usage
export default themeToggle;

// Also make available globally for non-module scripts
window.ThemeToggle = ThemeToggle;
window.themeToggle = themeToggle;
