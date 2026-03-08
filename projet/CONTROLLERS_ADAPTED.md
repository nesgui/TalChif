# Contrôleurs Adaptés à l'Architecture Hexagonale

## ✅ Contrôleurs Migrés

### 1. `AchatController` ✅

**Changements**:
- ✅ Injection de `CreerCommandeHandler` au lieu de `ServiceCommande`
- ✅ Méthode `paiement()` utilise maintenant `CreerCommandeCommand` + Handler
- ✅ Paramètres MoMo injectés directement via `#[Autowire]`

**Avant**:
```php
$commande = $this->serviceCommande->creerCommande(
    $panier,
    $user,
    $methodePaiement,
    $telephone
);
```

**Après**:
```php
$command = new CreerCommandeCommand(
    userId: $user->getId(),
    panier: $panier,
    methodePaiement: $methodePaiement,
    numeroClient: $telephone
);

$commande = $this->creerCommandeHandler->handle($command);
```

---

### 2. `AdminCommandeController` ✅

**Changements**:
- ✅ Injection de `ValiderPaiementHandler`
- ✅ Méthode `valider()` utilise `ValiderPaiementCommand` + Handler

**Avant**:
```php
$this->serviceCommande->validerPaiement($reference, $montant, $numero, $this->getUser());
```

**Après**:
```php
$command = new ValiderPaiementCommand(
    referenceCommande: $reference,
    montantRecu: $montant,
    numeroClient: $numero,
    validateurId: $this->getUser()->getId()
);

$this->validerPaiementHandler->handle($command);
```

---

## 🎯 Avantages Obtenus

### 1. Découplage Total
Les contrôleurs ne dépendent plus de `ServiceCommande` (couplé à Doctrine), mais de handlers abstraits.

### 2. Testabilité
```php
// Test du handler sans base de données
$commandeRepoMock = $this->createMock(CommandeRepositoryInterface::class);
$handler = new ValiderPaiementHandler($commandeRepoMock, ...);
```

### 3. Validation Automatique
Les Value Objects (`Telephone`, `Montant`) valident automatiquement les données.

### 4. Logique Métier Centralisée
Toute la logique de validation de paiement est dans le handler, pas dispersée.

---

## 📋 Handlers Créés

| Handler | Command | Responsabilité |
|---------|---------|----------------|
| `AcheterBilletsHandler` | `AcheterBilletsCommand` | Achat direct avec paiement immédiat |
| `CreerCommandeHandler` | `CreerCommandeCommand` | Création commande Mobile Money |
| `ValiderPaiementHandler` | `ValiderPaiementCommand` | Validation manuelle paiement admin |

---

## 🔄 Coexistence Ancien/Nouveau

`ServiceCommande` est **toujours injecté** dans les contrôleurs pour:
- Méthodes non encore migrées (`rejeterPaiement()`, `expirerCommandes()`)
- Compatibilité avec code legacy

**Prochaine étape**: Créer handlers pour ces méthodes et supprimer `ServiceCommande`.

---

## ✅ Configuration Services

Ajouté dans `config/services.yaml`:
```yaml
App\Application\Handler\ValiderPaiementHandler:
    arguments:
        $commandeRepository: '@App\Domain\Repository\CommandeRepositoryInterface'
        $evenementRepository: '@App\Domain\Repository\EvenementRepositoryInterface'
        $billetRepository: '@App\Domain\Repository\BilletRepositoryInterface'
```

---

**Date**: 8 Mars 2026  
**Statut**: ✅ 2 contrôleurs adaptés, architecture hexagonale opérationnelle
