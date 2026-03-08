# Guide de Migration vers l'Architecture Hexagonale

## ✅ Travail Effectué

### 1. Structure Créée

```
src/
├── Domain/                    # Cœur métier (zéro dépendance infrastructure)
│   ├── Entity/               # (à migrer depuis src/Entity/)
│   ├── ValueObject/          # ✅ Telephone, Montant, Email
│   ├── Repository/           # ✅ Interfaces (ports)
│   └── Exception/            # ✅ Exceptions métier
│
├── Application/              # Cas d'usage (use cases)
│   ├── Command/              # ✅ Commands CQRS
│   ├── Handler/              # ✅ Handlers
│   └── Query/                # (à créer pour les lectures)
│
└── Infrastructure/           # Implémentations techniques
    ├── Doctrine/
    │   └── Repository/       # ✅ Adapters Doctrine
    └── Payment/              # (à migrer depuis src/Service/Payment/)
```

### 2. Value Objects Créés ✅

- **`Telephone`**: Validation automatique des numéros tchadiens (235 XX XX XX XX)
- **`Montant`**: Gestion des montants XAF avec opérations (addition, soustraction, pourcentage)
- **`Email`**: Validation des emails

### 3. Exceptions Domaine Créées ✅

- `InvalidTelephoneException`
- `MontantNegatifException`
- `InvalidEmailException`
- `PlacesInsuffisantesException`
- `EvenementInactifException`

### 4. Interfaces Repositories (Ports) ✅

- `EvenementRepositoryInterface`
- `BilletRepositoryInterface`
- `CommandeRepositoryInterface`

### 5. Adapters Doctrine ✅

- `DoctrineEvenementRepository`
- `DoctrineBilletRepository`
- `DoctrineCommandeRepository`

### 6. Commands CQRS ✅

- `AcheterBilletsCommand`
- `CreerCommandeCommand`
- `ValiderPaiementCommand`

### 7. Handlers (Use Cases) ✅

- `AcheterBilletsHandler`: Logique d'achat de billets découplée
- `CreerCommandeHandler`: Création de commande Mobile Money

### 8. Configuration Services ✅

`config/services.yaml` configuré avec les alias pour l'injection de dépendances.

---

## 🔄 Comment Utiliser la Nouvelle Architecture

### Exemple 1: Acheter des Billets

**Avant** (couplé à Doctrine):
```php
// Dans le contrôleur
$serviceAchat = $this->serviceAchat;
$resultat = $serviceAchat->traiterAchat($panier, $user, $methode, $telephone);
```

**Après** (architecture hexagonale):
```php
// Dans le contrôleur
use App\Application\Command\AcheterBilletsCommand;
use App\Application\Handler\AcheterBilletsHandler;

$command = new AcheterBilletsCommand(
    userId: $user->getId(),
    panier: $panier,
    methodePaiement: $methode,
    telephone: $telephone
);

$resultat = $this->acheterBilletsHandler->handle($command);
```

### Exemple 2: Créer une Commande

```php
use App\Application\Command\CreerCommandeCommand;
use App\Application\Handler\CreerCommandeHandler;

$command = new CreerCommandeCommand(
    userId: $user->getId(),
    panier: $panier,
    methodePaiement: 'MOMO',
    numeroClient: '235 XX XX XX XX'
);

$commande = $this->creerCommandeHandler->handle($command);
```

### Exemple 3: Utiliser les Value Objects

```php
use App\Domain\ValueObject\Telephone;
use App\Domain\ValueObject\Montant;

// Validation automatique
$telephone = Telephone::fromString('235 12 34 56 78'); // ✅ Valide
$telephone = Telephone::fromString('123456'); // ❌ Exception

// Opérations sur les montants
$prix = Montant::fromFloat(5000.0);
$total = $prix->multiplier(3); // 15000 XAF
$commission = $total->appliquerPourcentage(10); // 1500 XAF
```

---

## 📋 Prochaines Étapes

### Phase 1: Adapter les Contrôleurs (URGENT)

#### 1.1 Modifier `AchatController`

**Fichier**: `src/Controller/AchatController.php`

```php
// Injecter le handler au lieu du service
public function __construct(
    private AcheterBilletsHandler $acheterBilletsHandler,
    // ... autres dépendances
) {}

// Dans l'action
$command = new AcheterBilletsCommand(
    userId: $user->getId(),
    panier: $panier,
    methodePaiement: $methodePaiement,
    telephone: $telephone
);

$resultat = $this->acheterBilletsHandler->handle($command);
```

#### 1.2 Modifier `AdminCommandeController`

Utiliser `CreerCommandeHandler` au lieu de `ServiceCommande`.

### Phase 2: Migrer les Entités vers Rich Domain Model

#### 2.1 Transformer `Evenement` en Entité Riche

**Avant** (anémique):
```php
// Dans ServiceAchat
$evenement->setPlacesVendues($evenement->getPlacesVendues() + $quantite);
```

**Après** (riche):
```php
// Dans l'entité Evenement
public function reserverPlaces(int $quantite): void {
    if ($quantite > $this->getPlacesRestantes()) {
        throw new PlacesInsuffisantesException(
            "Seulement {$this->getPlacesRestantes()} places disponibles"
        );
    }
    $this->placesVendues += $quantite;
}

// Dans le handler
$evenement->reserverPlaces($quantite);
```

#### 2.2 Ajouter des Méthodes Métier

```php
// Evenement
public function estComplet(): bool {
    return $this->placesVendues >= $this->placesDisponibles;
}

public function peutAccepterReservation(int $quantite): bool {
    return $quantite <= $this->getPlacesRestantes();
}

// Billet
public function estValide(): bool {
    return $this->isValide && !$this->isUtilise;
}

public function utiliser(): void {
    if (!$this->estValide()) {
        throw new BilletInvalideException();
    }
    $this->isUtilise = true;
    $this->dateUtilisation = new \DateTimeImmutable();
}
```

### Phase 3: Créer les Queries (CQRS - Lecture)

```php
// Application/Query/ObtenirMesBilletsQuery.php
final readonly class ObtenirMesBilletsQuery {
    public function __construct(public int $userId) {}
}

// Application/Handler/ObtenirMesBilletsHandler.php
final class ObtenirMesBilletsHandler {
    public function handle(ObtenirMesBilletsQuery $query): array {
        return $this->billetRepository->findByUser($query->userId);
    }
}
```

### Phase 4: Migrer les Services Restants

- [ ] `ServiceCommande::validerPaiement()` → `ValiderPaiementHandler`
- [ ] `ServiceCommande::rejeterPaiement()` → `RejeterPaiementHandler`
- [ ] `ServiceCommande::expirerCommandes()` → `ExpirerCommandesHandler`
- [ ] `TicketRenderService` → Déplacer dans `Infrastructure/Ticket/`
- [ ] `ServiceUploadFichier` → Déplacer dans `Infrastructure/FileSystem/`

### Phase 5: Tests Unitaires

```php
// tests/Domain/ValueObject/TelephoneTest.php
class TelephoneTest extends TestCase {
    public function testValidTelephone(): void {
        $tel = Telephone::fromString('235 12 34 56 78');
        $this->assertEquals('23512345678', $tel->toString());
    }
    
    public function testInvalidTelephone(): void {
        $this->expectException(InvalidTelephoneException::class);
        Telephone::fromString('123456');
    }
}

// tests/Application/Handler/AcheterBilletsHandlerTest.php
class AcheterBilletsHandlerTest extends TestCase {
    public function testAchatAvecSucces(): void {
        // Mock des repositories
        $evenementRepo = $this->createMock(EvenementRepositoryInterface::class);
        // ... tests
    }
}
```

---

## 🎯 Avantages de la Nouvelle Architecture

### 1. Découplage Total ✅

```php
// Le domaine ne dépend PAS de Doctrine
namespace App\Domain\Repository;

interface EvenementRepositoryInterface {
    public function findById(int $id): ?Evenement;
}

// L'infrastructure implémente le port
namespace App\Infrastructure\Doctrine\Repository;

class DoctrineEvenementRepository implements EvenementRepositoryInterface {
    // Implémentation Doctrine
}
```

**Bénéfice**: On peut changer de BDD (MongoDB, Redis, etc.) sans toucher au domaine.

### 2. Testabilité Maximale ✅

```php
// Test unitaire SANS base de données
$evenementRepoMock = $this->createMock(EvenementRepositoryInterface::class);
$handler = new AcheterBilletsHandler($evenementRepoMock, ...);
```

### 3. Logique Métier Centralisée ✅

```php
// Avant: logique dispersée dans les services
$evenement->setPlacesVendues($evenement->getPlacesVendues() + $quantite);

// Après: logique dans l'entité
$evenement->reserverPlaces($quantite); // Validation automatique
```

### 4. Value Objects Auto-Validants ✅

```php
// Impossible de créer un téléphone invalide
$tel = Telephone::fromString('235 12 34 56 78'); // ✅
$tel = Telephone::fromString('abc'); // ❌ Exception
```

### 5. CQRS: Séparation Lecture/Écriture ✅

```php
// Commande (écriture)
$command = new AcheterBilletsCommand(...);
$handler->handle($command);

// Query (lecture)
$query = new ObtenirMesBilletsQuery(userId: 123);
$billets = $queryHandler->handle($query);
```

---

## ⚠️ Points d'Attention

### 1. Coexistence Ancien/Nouveau Code

Les anciens services (`ServiceAchat`, `ServiceCommande`) **coexistent** avec les nouveaux handlers. Migration progressive recommandée.

### 2. Doctrine Entities vs Domain Entities

Pour l'instant, on utilise encore les entités Doctrine (`src/Entity/`). À terme, on pourrait:
- Créer des entités domaine pures dans `src/Domain/Entity/`
- Mapper Doctrine entities ↔ Domain entities

### 3. Transactions

Les handlers utilisent encore `EntityManagerInterface` pour les transactions. C'est acceptable car:
- Les transactions sont une préoccupation d'infrastructure
- Alternative: créer un `UnitOfWork` abstrait

---

## 📚 Ressources

- **Architecture Hexagonale**: https://alistair.cockburn.us/hexagonal-architecture/
- **CQRS**: https://martinfowler.com/bliki/CQRS.html
- **Value Objects**: https://martinfowler.com/bliki/ValueObject.html
- **Rich Domain Model**: https://martinfowler.com/bliki/AnemicDomainModel.html

---

## ✅ Checklist de Migration

- [x] Créer structure Domain/Application/Infrastructure
- [x] Créer Value Objects (Telephone, Montant, Email)
- [x] Créer Exceptions domaine
- [x] Créer Interfaces repositories (ports)
- [x] Implémenter Adapters Doctrine
- [x] Créer Commands CQRS
- [x] Créer Handlers (AcheterBillets, CreerCommande)
- [x] Configurer services.yaml
- [ ] Adapter contrôleurs pour utiliser handlers
- [ ] Transformer entités en Rich Domain Model
- [ ] Créer Queries CQRS
- [ ] Migrer services restants
- [ ] Écrire tests unitaires domaine
- [ ] Documentation API handlers

---

**Date de création**: 8 Mars 2026  
**Statut**: ✅ Fondations complètes, migration contrôleurs en cours
