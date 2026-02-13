# 💡 Exemples d'Utilisation - Système de Notifications v2.0

**Cas d'usage réels tirés de OSEA.td**

---

## 📦 Exemple 1: Panier - Ajout avec Action Inline

### Avant (Basique)

```php
// PanierController.php
public function add(Evenement $evenement): Response
{
    $panier->addItem($evenement);
    $this->addFlash('success', 'Événement ajouté au panier');
    return $this->redirectToRoute('evenement.index');
}
```

### Après (Avec Action Inline)

#### Backend (inchangé)
```php
// PanierController.php - Reste identique !
public function add(Evenement $evenement): Response
{
    $panier->addItem($evenement);
    $this->addFlash('success', sprintf(
        'L\'événement "%s" a été ajouté au panier',
        $evenement->getNom()
    ));
    return $this->redirectToRoute('evenement.index');
}
```

#### Frontend (interception pour action)
```javascript
// Si vous voulez une action "Voir le panier"
// Option 1: Intercepter les flash messages (avancé)
document.addEventListener('DOMContentLoaded', () => {
    const messages = window.flashMessages || [];
    const panierAdded = messages.find(m => 
        m.type === 'success' && m.message.includes('ajouté au panier')
    );
    
    if (panierAdded) {
        NotificationService.show('success', 'Panier', panierAdded.message, {
            action: {
                label: 'Voir le panier',
                callback: () => window.location.href = '/panier'
            }
        });
    }
});

// Option 2: Méthode utilitaire (recommandé)
// Dans votre code AJAX d'ajout au panier
fetch('/panier/ajouter/' + eventId, { method: 'POST' })
    .then(response => response.json())
    .then(data => {
        NotificationService.addToCartSuccess(data.eventName);
        // La méthode addToCartSuccess inclut déjà l'action !
    });
```

---

## 🎫 Exemple 2: Validation de Billets

### Scénario : Scanner de billets avec feedback en temps réel

```javascript
// validation.js
async function validerBillet(code) {
    // Notification de traitement
    const scanId = 'scan-' + Date.now();
    NotificationService.show('info', 'Validation', 'Vérification en cours...', {
        id: scanId,
        duration: 0, // Infini pendant traitement
        progress: true
    });
    
    try {
        const response = await fetch(`/validation/scan/${code}`, {
            method: 'POST'
        });
        
        const data = await response.json();
        
        // Fermer notification de traitement
        NotificationService.hide(scanId);
        
        if (data.valid) {
            // Succès avec détails
            NotificationService.show('success', 'Billet Valide ✓', 
                `${data.participant} - ${data.evenement}`, {
                duration: 4000
            });
            
            // Effet sonore (optionnel)
            playSuccessSound();
        } else {
            // Erreur avec raison
            NotificationService.show('error', 'Billet Invalide', 
                data.reason || 'Ce billet ne peut pas être validé', {
                duration: 6000
            });
            
            // Effet sonore d'erreur
            playErrorSound();
        }
    } catch (error) {
        NotificationService.hide(scanId);
        NotificationService.networkError();
    }
}
```

---

## 🎨 Exemple 3: Upload d'Images (Événements)

### Upload avec progression et preview

```javascript
// upload-evenement.js
async function uploadImageEvenement(file, eventId) {
    // Validation côté client
    if (!file.type.startsWith('image/')) {
        NotificationService.error('Format invalide', 
            'Veuillez sélectionner une image (JPG, PNG, WEBP)');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        NotificationService.error('Fichier trop lourd', 
            'La taille maximale est de 5 MB');
        return;
    }
    
    // Notification de début
    const uploadId = 'upload-' + eventId;
    NotificationService.show('info', 'Upload', 
        `Envoi de ${file.name}...`, {
        id: uploadId,
        duration: 0,
        progress: true,
        closable: false // Empêcher fermeture pendant upload
    });
    
    const formData = new FormData();
    formData.append('image', file);
    
    try {
        const response = await fetch(`/organisateur/evenement/${eventId}/upload-image`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        // Fermer notification de progression
        NotificationService.hide(uploadId);
        
        if (response.ok) {
            // Succès
            NotificationService.uploadSuccess(file.name);
            
            // Mettre à jour l'aperçu
            document.getElementById('event-preview').src = data.imageUrl;
        } else {
            NotificationService.uploadError(file.name, data.error);
        }
    } catch (error) {
        NotificationService.hide(uploadId);
        NotificationService.networkError();
    }
}

// Drag & Drop support
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file) {
        uploadImageEvenement(file, currentEventId);
    }
});
```

---

## 🔐 Exemple 4: Session Expirée avec Reconnexion

### Intercepteur AJAX global

```javascript
// app.js - Intercepteur global pour les erreurs 401
async function fetchWithAuth(url, options = {}) {
    try {
        const response = await fetch(url, options);
        
        if (response.status === 401) {
            // Session expirée
            NotificationService.sessionExpired();
            // La méthode sessionExpired() inclut déjà un bouton "Se reconnecter"
            
            // Alternative: redirection automatique après 3s
            setTimeout(() => {
                window.location.href = '/login?redirect=' + 
                    encodeURIComponent(window.location.pathname);
            }, 3000);
        }
        
        return response;
    } catch (error) {
        NotificationService.networkError();
        throw error;
    }
}

// Usage
fetchWithAuth('/api/evenements')
    .then(response => response.json())
    .then(data => {
        // Traiter les données
    });
```

---

## 📊 Exemple 5: DataTables avec Feedback

### Export CSV avec notification

```javascript
// datatables-config.js
$('#evenements-table').DataTable({
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'csv',
            text: 'Exporter CSV',
            action: function(e, dt, button, config) {
                // Notification de début
                NotificationService.show('info', 'Export', 
                    'Génération du fichier CSV...', {
                    duration: 0,
                    progress: true
                });
                
                // Export natif
                $.fn.dataTable.ext.buttons.csvHtml5.action.call(
                    this, e, dt, button, config
                );
                
                // Succès après 500ms (temps d'export)
                setTimeout(() => {
                    NotificationService.success('Export réussi', 
                        'Le fichier CSV a été téléchargé');
                }, 500);
            }
        }
    ]
});
```

---

## 🔔 Exemple 6: Notifications Temps Réel (Avec Mercure)

### Système de notifications push

```javascript
// mercure-notifications.js
const eventSource = new EventSource(
    'https://mercure.osea.td/.well-known/mercure?topic=/notifications/{userId}'
);

eventSource.onmessage = (event) => {
    const notification = JSON.parse(event.data);
    
    // Mapper les types backend vers frontend
    const typeMap = {
        'nouveau_message': 'info',
        'nouveau_billet': 'success',
        'evenement_annule': 'warning',
        'paiement_echoue': 'error'
    };
    
    const type = typeMap[notification.type] || 'info';
    
    // Afficher avec action
    NotificationService.show(type, notification.title, notification.message, {
        duration: 8000,
        action: notification.actionUrl ? {
            label: notification.actionLabel || 'Voir',
            callback: () => window.location.href = notification.actionUrl
        } : null
    });
    
    // Notification navigateur (optionnel)
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(notification.title, {
            body: notification.message,
            icon: '/icon-192.png'
        });
    }
};
```

---

## 🛒 Exemple 7: Panier - Gestion de Stock en Temps Réel

### Alertes de stock faible

```javascript
// panier-check.js
async function verifierStockAvantPaiement() {
    const items = panier.getItems();
    
    for (const item of items) {
        const response = await fetch(`/api/evenements/${item.id}/stock`);
        const data = await response.json();
        
        if (data.placesRestantes === 0) {
            // Complet
            NotificationService.error('Événement Complet', 
                `"${item.nom}" n'a plus de places disponibles`, {
                duration: 0, // Reste affiché
                action: {
                    label: 'Retirer du panier',
                    callback: () => retirerDuPanier(item.id)
                }
            });
            return false;
        } else if (data.placesRestantes < 10) {
            // Stock faible
            NotificationService.warning('Stock Limité', 
                `Plus que ${data.placesRestantes} places pour "${item.nom}"`, {
                duration: 6000
            });
        }
    }
    
    return true;
}

// Avant paiement
btnPayer.addEventListener('click', async (e) => {
    e.preventDefault();
    
    const stockOk = await verifierStockAvantPaiement();
    
    if (stockOk) {
        procederAuPaiement();
    }
});
```

---

## 🎭 Exemple 8: Formulaires avec Validation

### Feedback inline + toast

```javascript
// form-validation.js
function validerFormulaireEvenement(form) {
    const errors = [];
    
    // Validation
    const nom = form.querySelector('#nom').value;
    if (!nom || nom.length < 3) {
        errors.push('Le nom doit contenir au moins 3 caractères');
    }
    
    const date = form.querySelector('#date').value;
    if (new Date(date) < new Date()) {
        errors.push('La date ne peut pas être dans le passé');
    }
    
    const capacite = parseInt(form.querySelector('#capacite').value);
    if (capacite < 1 || capacite > 10000) {
        errors.push('La capacité doit être entre 1 et 10 000');
    }
    
    if (errors.length > 0) {
        // Notification globale
        NotificationService.error('Formulaire invalide', 
            `${errors.length} erreur(s) détectée(s)`, {
            duration: 6000
        });
        
        // Feedback inline (bonus)
        errors.forEach(error => {
            console.error('Validation:', error);
        });
        
        return false;
    }
    
    return true;
}

form.addEventListener('submit', (e) => {
    if (!validerFormulaireEvenement(e.target)) {
        e.preventDefault();
    }
});
```

---

## 🔄 Exemple 9: Actions Multiples en Batch

### Suppression multiple avec confirmation

```javascript
// batch-delete.js
async function supprimerMultipleEvenements(eventIds) {
    // Confirmation
    if (!confirm(`Supprimer ${eventIds.length} événement(s) ?`)) {
        return;
    }
    
    // Notification de progression
    const batchId = 'batch-delete';
    NotificationService.show('info', 'Suppression', 
        `Suppression de ${eventIds.length} événement(s)...`, {
        id: batchId,
        duration: 0,
        progress: false,
        closable: false
    });
    
    let success = 0;
    let errors = 0;
    
    for (const eventId of eventIds) {
        try {
            const response = await fetch(`/organisateur/evenement/${eventId}`, {
                method: 'DELETE'
            });
            
            if (response.ok) {
                success++;
            } else {
                errors++;
            }
        } catch (error) {
            errors++;
        }
    }
    
    // Fermer progression
    NotificationService.hide(batchId);
    
    // Résultat
    if (errors === 0) {
        NotificationService.success('Suppression réussie', 
            `${success} événement(s) supprimé(s)`);
    } else if (success === 0) {
        NotificationService.error('Échec complet', 
            `Impossible de supprimer les événements`);
    } else {
        NotificationService.warning('Suppression partielle', 
            `${success} supprimé(s), ${errors} échec(s)`);
    }
    
    // Recharger la liste
    location.reload();
}
```

---

## 📱 Exemple 10: Responsive - Notifications Mobile

### Adaptation automatique

```javascript
// mobile-notifications.js
// Le système s'adapte automatiquement au mobile
// Mais vous pouvez personnaliser :

function notifierMobile(type, title, message) {
    const isMobile = window.innerWidth < 768;
    
    NotificationService.show(type, title, message, {
        duration: isMobile ? 3000 : 5000, // Plus court sur mobile
        progress: !isMobile, // Pas de barre sur mobile (espace limité)
        action: isMobile ? null : { // Actions seulement desktop
            label: 'En savoir plus',
            callback: () => openModal()
        }
    });
}

// Utilisation
notifierMobile('success', 'Billet acheté', 'Confirmation envoyée par SMS');
```

---

## 🎯 Bonnes Pratiques

### ✅ À FAIRE

```javascript
// Durées adaptées au type
NotificationService.error('Erreur', 'Message', { duration: 7000 }); // Long
NotificationService.success('OK', 'Message', { duration: 3000 });   // Court

// Messages clairs et actionables
NotificationService.error('Paiement échoué', 
    'Votre carte a été refusée. Vérifiez les informations.');

// Actions pertinentes
NotificationService.show('warning', 'Session expirée', 'Reconnectez-vous', {
    action: {
        label: 'Se reconnecter',
        callback: () => window.location.href = '/login'
    }
});
```

### ❌ À ÉVITER

```javascript
// Messages trop vagues
NotificationService.error('Erreur', 'Une erreur est survenue'); // ❌

// Notifications trop fréquentes
setInterval(() => {
    NotificationService.info('Sync', 'Synchronisation...'); // ❌ Spam
}, 1000);

// Durées trop longues pour infos non critiques
NotificationService.info('Info', 'Message', { duration: 30000 }); // ❌
```

---

## 🔍 Debug & Monitoring

### Inspector pour développeurs

```javascript
// Console DevTools
function inspectNotifications() {
    console.group('🔔 Notification Service');
    console.log('Initialisé:', NotificationService.isInitialized);
    console.log('Actives:', NotificationService.notifications.size);
    console.log('Max visible:', NotificationService.maxVisible);
    console.log('Queue:', NotificationService.queue.length);
    console.log('Conteneur:', NotificationService.container);
    console.groupEnd();
}

// Appeler
inspectNotifications();

// Ou directement
NotificationService.info('Debug', 'Voir console pour détails');
inspectNotifications();
```

---

## 🚀 Résumé

**Le système est prêt à l'emploi avec :**
- ✅ Rétrocompatibilité totale
- ✅ Nouvelles fonctionnalités puissantes
- ✅ Accessibilité WCAG 2.2 AA
- ✅ Performance optimale

**Testez maintenant:** `/test/notifications`

**Documentation:** 
- Guide rapide: `NOTIFICATION_QUICKSTART.md`
- Migration: `NOTIFICATION_SYSTEM_MIGRATION.md`
- Audit: `NOTIFICATION_SYSTEM_AUDIT.md`
