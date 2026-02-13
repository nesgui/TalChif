/**
 * Contrôleur Stimulus pour gérer les états de chargement sur les formulaires.
 * Ajoute automatiquement la classe "loading" au bouton submit pendant la soumission.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['submitButton'];
    
    connect() {
        // Trouver le bouton submit dans le formulaire
        if (!this.hasSubmitButtonTarget) {
            const submitBtn = this.element.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                this.submitButtonTarget = submitBtn;
            }
        }
        
        // Écouter la soumission du formulaire
        this.element.addEventListener('submit', this.handleSubmit.bind(this));
    }
    
    handleSubmit(event) {
        const submitBtn = this.submitButtonTarget || this.element.querySelector('button[type="submit"], input[type="submit"]');
        
        if (submitBtn && !submitBtn.disabled) {
            // Ajouter l'état de chargement
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            // Si le formulaire est annulé (ex: validation côté client), retirer l'état
            this.element.addEventListener('invalid', () => {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }, { once: true });
        }
    }
    
    disconnect() {
        this.element.removeEventListener('submit', this.handleSubmit);
    }
}
