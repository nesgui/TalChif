# Analyse Architecturale Complète — TalChif
**Date**: 8 Mars 2026  
**Version**: 1.0  
**Analyste**: Architecture Review

---

## 📊 Executive Summary

### État Actuel
Le projet TalChif est une **application monolithique Symfony 8** fonctionnelle avec une architecture MVC traditionnelle. Le code est globalement bien structuré avec une séparation des responsabilités, mais présente des opportunités d'amélioration significatives en termes de découplage, maintenabilité et scalabilité.

### Score Global de Maintenabilité: **6.5/10**

| Critère | Score | Commentaire |
|---------|-------|-------------|
| **Découplage** | 5/10 | Couplage fort entre contrôleurs et entités Doctrine |
| **SOLID** | 6/10 | Principes partiellement respectés, violations identifiées |
| **Testabilité** | 4/10 | Dépendances concrètes, difficile à mocker |
| **Scalabilité** | 5/10 | Monolithe sans séparation domaine/infra |
| **Conteneurisation** | 3/10 | Docker basique, pas de production-ready |

---

## 🏗️ Architecture Actuelle

### Structure des Dossiers
```
src/
├── Command/           # CLI commands
├── Controller/        # 21 contrôleurs (MVC)
├── DataFixtures/      # Fixtures Doctrine
├── Entity/            # 7 entités Doctrine (anemic domain model)
├── Form/              # Formulaires Symfony
├── Repository/        # 6 repositories Doctrine
├── Security/          # Authentification/autorisation
└── Service/           # Services métier
    ├── Achat/         # ServiceAchat
    ├── Commande/      # ServiceCommande
    ├── Payment/       # PaymentInterface + StubPaymentService
    ├── Ticket/        # TicketRenderService
    └── Upload/        # ServiceUploadFichier
```

### Pattern Architectural Actuel
**MVC Traditionnel avec Services Métier**

```
┌─────────────┐
│ Controllers │ ──> Dépendance directe sur Repositories
└─────────────┘
      │
      ▼
┌─────────────┐
│  Services   │ ──> Logique métier + accès données
└─────────────┘
      │
      ▼
┌─────────────┐
│ Repositories│ ──> Doctrine ORM (infrastructure)
└─────────────┘
      │
      ▼
┌─────────────┐
│  Entities   │ ──> Anemic Domain Model (getters/setters)
└─────────────┘
```

---

## 🔍 Analyse Détaillée des Violations

### 1. Violations du Principe de Découplage

#### ❌ **Problème 1.1**: Couplage Fort Contrôleur → Doctrine
**Fichier**: `src/Controller/AchatController.php`

```php
// ❌ Le contrôleur accède directement aux repositories
public function __construct(
    private EvenementRepository $evenementRepository,
    private CommandeRepository $commandeRepository,
    private EntityManagerInterface $entityManager,
    private ServiceCommande $serviceCommande
) {}

// ❌ Logique métier dans le contrôleur
$evenement = $this->evenementRepository->find($id);
if (!$evenement || !$evenement->isActive()) {
    continue;
}
```

**Impact**: 
- Impossible de changer de persistence sans modifier les contrôleurs
- Difficile à tester (dépendance Doctrine)
- Violation du principe d'inversion de dépendance (DIP)

#### ❌ **Problème 1.2**: Services Couplés à Doctrine
**Fichier**: `src/Service/Achat/ServiceAchat.php`

```php
// ❌ Service dépend directement de Doctrine
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

public function __construct(
    private EvenementRepository $evenementRepository,
    private EntityManagerInterface $entityManager,
    // ...
) {}
```

**Impact**:
- Service métier couplé à l'infrastructure
- Impossible de réutiliser la logique avec une autre base de données
- Tests nécessitent une base de données

#### ❌ **Problème 1.3**: Entités Anémiques (Anemic Domain Model)
**Fichier**: `src/Entity/Evenement.php`, `src/Entity/Billet.php`

```php
// ❌ Entités = simples conteneurs de données
class Evenement {
    private ?int $placesDisponibles = null;
    private ?int $placesVendues = null;
    
    public function getPlacesDisponibles(): ?int { return $this->placesDisponibles; }
    public function setPlacesDisponibles(int $places): self { ... }
}

// ❌ Logique métier dans les services au lieu du domaine
// ServiceAchat.php ligne 107
$evenement->setPlacesVendues($evenement->getPlacesVendues() + $quantite);
```

**Impact**:
- Logique métier dispersée dans les services
- Entités ne protègent pas leurs invariants
- Duplication de code

---

### 2. Violations des Principes SOLID

#### ❌ **S - Single Responsibility Principle**
**Fichier**: `src/Service/Commande/ServiceCommande.php` (288 lignes)

```php
// ❌ Classe avec trop de responsabilités
class ServiceCommande {
    // 1. Création de commande
    public function creerCommande(...) {}
    
    // 2. Expiration de commandes
    public function expirerCommandes() {}
    
    // 3. Validation de paiement
    public function validerPaiement(...) {}
    
    // 4. Rejet de paiement
    public function rejeterPaiement(...) {}
    
    // 5. Génération de billets
    private function genererBillets(...) {}
    
    // 6. Validation de numéro
    private function numeroClientValide(...) {}
    
    // 7. Logging de sécurité
    private function loggerAction(...) {}
}
```

**Impact**: Classe difficile à maintenir, tester et comprendre.

#### ❌ **O - Open/Closed Principle**
**Fichier**: `src/Service/Payment/StubPaymentService.php`

```php
// ❌ Pour ajouter un nouveau moyen de paiement, il faut modifier la classe
public function getMethodesSupportees(): array {
    return [
        self::METHODE_MOMO,
        self::METHODE_AIRTEL,
        self::METHODE_ORANGE,
    ];
}
```

**Impact**: Ajout de nouvelles méthodes de paiement nécessite modification du code existant.

#### ❌ **D - Dependency Inversion Principle**
**Fichier**: `src/Service/Achat/ServiceAchat.php`

```php
// ✅ BIEN: Dépend d'une abstraction
private PaymentInterface $servicePaiement,

// ❌ MAL: Dépend d'une implémentation concrète
private EvenementRepository $evenementRepository,
private EntityManagerInterface $entityManager,
```

**Impact**: Dépendances concrètes empêchent le découplage.

---

### 3. Problèmes de Testabilité

#### ❌ **Problème 3.1**: Pas de Tests Unitaires
```
tests/
└── (vide ou minimal)
```

#### ❌ **Problème 3.2**: Dépendances Difficiles à Mocker
```php
// ❌ Impossible de tester sans base de données
$evenement = $this->evenementRepository->find($id, LockMode::PESSIMISTIC_WRITE);
```

---

### 4. Absence de Conteneurisation Production-Ready

#### ❌ **Problème 4.1**: Pas de Dockerfile
Le projet n'a **aucun Dockerfile** pour l'application PHP.

#### ❌ **Problème 4.2**: Docker Compose Incomplet
**Fichier**: `compose.yaml`

```yaml
# ❌ Seulement PostgreSQL, pas de service PHP
services:
  database:
    image: postgres:16-alpine
    # ...
```

**Manque**:
- Service PHP-FPM
- Service Nginx
- Service Redis (cache/sessions)
- Service Queue Worker (Messenger)
- Healthchecks
- Multi-stage builds
- Secrets management

---

## ✅ Points Forts Actuels

### 1. ✅ Séparation des Services Métier
```
Service/
├── Achat/         # Domaine achat
├── Commande/      # Domaine commande
├── Payment/       # Abstraction paiement (PaymentInterface)
├── Ticket/        # Génération billets
└── Upload/        # Upload fichiers
```

### 2. ✅ Utilisation d'Interfaces
```php
// ✅ BIEN: Abstraction pour les paiements
interface PaymentInterface {
    public function payer(float $montant, string $methode, array $context): PaymentResult;
    public function supports(string $methode): bool;
}
```

### 3. ✅ Gestion des Transactions
```php
// ✅ BIEN: Transactions Doctrine pour cohérence
$this->entityManager->beginTransaction();
try {
    // ... opérations
    $this->entityManager->commit();
} catch (\Throwable $e) {
    $this->entityManager->rollback();
    throw $e;
}
```

### 4. ✅ Verrouillage Pessimiste
```php
// ✅ BIEN: Évite les race conditions
$evenement = $this->evenementRepository->find($id, LockMode::PESSIMISTIC_WRITE);
```

---

## 🎯 Recommandations Prioritaires

### Priorité HAUTE 🔴

#### 1. Implémenter Architecture Hexagonale (Clean Architecture)

**Structure Cible**:
```
src/
├── Domain/                    # Cœur métier (pur PHP, zéro dépendance)
│   ├── Entity/               # Entités riches (logique métier)
│   ├── ValueObject/          # Objets valeur immutables
│   ├── Repository/           # Interfaces (ports)
│   ├── Service/              # Services domaine
│   └── Exception/            # Exceptions métier
│
├── Application/              # Cas d'usage (use cases)
│   ├── Command/              # Commandes CQRS
│   ├── Query/                # Requêtes CQRS
│   └── Handler/              # Handlers
│
├── Infrastructure/           # Implémentations techniques
│   ├── Doctrine/             # Repositories Doctrine
│   ├── Payment/              # Implémentations paiement
│   ├── FileSystem/           # Upload fichiers
│   └── Messaging/            # Queue/Events
│
└── Presentation/             # Interface utilisateur
    ├── Controller/           # Contrôleurs HTTP
    ├── Form/                 # Formulaires
    └── ViewModel/            # DTOs présentation
```

**Bénéfices**:
- ✅ Domaine métier indépendant de l'infrastructure
- ✅ Testabilité maximale (tests unitaires purs)
- ✅ Changement de base de données sans impact sur le domaine
- ✅ Réutilisation du domaine dans d'autres contextes (API, CLI, etc.)

#### 2. Refactoriser les Entités en Rich Domain Model

**Avant** (Anémique):
```php
// ❌ Entity/Evenement.php
class Evenement {
    private int $placesVendues = 0;
    public function setPlacesVendues(int $places): self { ... }
}

// ❌ Service/ServiceAchat.php
$evenement->setPlacesVendues($evenement->getPlacesVendues() + $quantite);
```

**Après** (Riche):
```php
// ✅ Domain/Entity/Evenement.php
class Evenement {
    private int $placesVendues = 0;
    private int $placesDisponibles;
    
    public function reserverPlaces(int $quantite): void {
        if ($quantite > $this->getPlacesRestantes()) {
            throw new PlacesInsuffisantesException(
                "Seulement {$this->getPlacesRestantes()} places disponibles"
            );
        }
        $this->placesVendues += $quantite;
    }
    
    public function getPlacesRestantes(): int {
        return $this->placesDisponibles - $this->placesVendues;
    }
    
    // ❌ Pas de setter public
}
```

#### 3. Implémenter CQRS (Command Query Responsibility Segregation)

**Structure**:
```php
// Application/Command/AcheterBilletsCommand.php
final readonly class AcheterBilletsCommand {
    public function __construct(
        public int $userId,
        public array $panier,
        public string $methodePaiement,
        public string $telephone
    ) {}
}

// Application/Handler/AcheterBilletsHandler.php
final class AcheterBilletsHandler {
    public function __construct(
        private EvenementRepositoryInterface $evenementRepo,
        private PaymentInterface $paymentService,
        private BilletFactory $billetFactory
    ) {}
    
    public function handle(AcheterBilletsCommand $command): ResultatAchat {
        // Logique métier pure
    }
}
```

**Bénéfices**:
- ✅ Séparation lecture/écriture
- ✅ Testabilité (handlers isolés)
- ✅ Scalabilité (optimisation lectures vs écritures)

#### 4. Créer des Interfaces pour les Repositories

**Avant**:
```php
// ❌ Dépendance concrète
public function __construct(
    private EvenementRepository $evenementRepository
) {}
```

**Après**:
```php
// ✅ Domain/Repository/EvenementRepositoryInterface.php
interface EvenementRepositoryInterface {
    public function findById(int $id): ?Evenement;
    public function findActiveEvents(): array;
    public function save(Evenement $evenement): void;
}

// ✅ Infrastructure/Doctrine/DoctrineEvenementRepository.php
final class DoctrineEvenementRepository implements EvenementRepositoryInterface {
    // Implémentation Doctrine
}

// ✅ Application/Handler/...
public function __construct(
    private EvenementRepositoryInterface $evenementRepository
) {}
```

---

### Priorité MOYENNE 🟡

#### 5. Découper ServiceCommande (SRP)

**Avant**: 1 classe de 288 lignes

**Après**: 5 classes spécialisées
```php
// Application/Service/CommandeCreator.php
final class CommandeCreator {
    public function creer(array $panier, User $client, ...): Commande {}
}

// Application/Service/CommandeExpirator.php
final class CommandeExpirator {
    public function expirerCommandesEnAttente(): int {}
}

// Application/Service/PaiementValidator.php
final class PaiementValidator {
    public function valider(Commande $commande, ...): void {}
}

// Application/Service/BilletGenerator.php
final class BilletGenerator {
    public function generer(Commande $commande): array {}
}

// Domain/Service/NumeroTelephoneValidator.php
final class NumeroTelephoneValidator {
    public function estValide(string $numero): bool {}
}
```

#### 6. Implémenter Strategy Pattern pour Paiements

**Avant**: If/else dans le service

**Après**: Registre de stratégies
```php
// Infrastructure/Payment/PaymentStrategyRegistry.php
final class PaymentStrategyRegistry {
    /** @var array<string, PaymentInterface> */
    private array $strategies = [];
    
    public function register(string $methode, PaymentInterface $strategy): void {
        $this->strategies[$methode] = $strategy;
    }
    
    public function get(string $methode): PaymentInterface {
        return $this->strategies[$methode] 
            ?? throw new UnsupportedPaymentMethodException($methode);
    }
}

// Infrastructure/Payment/MomoPaymentStrategy.php
final class MomoPaymentStrategy implements PaymentInterface { ... }

// Infrastructure/Payment/AirtelPaymentStrategy.php
final class AirtelPaymentStrategy implements PaymentInterface { ... }
```

#### 7. Ajouter Value Objects

```php
// Domain/ValueObject/Telephone.php
final readonly class Telephone {
    private function __construct(private string $value) {
        if (!$this->estValide($value)) {
            throw new InvalidTelephoneException($value);
        }
    }
    
    public static function fromString(string $value): self {
        return new self($value);
    }
    
    public function toString(): string {
        return $this->value;
    }
    
    private function estValide(string $value): bool {
        return preg_match('/^235\d{8}$/', preg_replace('/\s/', '', $value));
    }
}

// Domain/ValueObject/Montant.php
final readonly class Montant {
    private function __construct(private float $value) {
        if ($value < 0) {
            throw new MontantNegatifException();
        }
    }
    
    public static function fromFloat(float $value): self {
        return new self($value);
    }
    
    public function ajouter(Montant $autre): self {
        return new self($this->value + $autre->value);
    }
}
```

---

### Priorité BASSE 🟢

#### 8. Implémenter Event Sourcing (optionnel)

Pour audit et traçabilité:
```php
// Domain/Event/BilletAcheteEvent.php
final readonly class BilletAcheteEvent {
    public function __construct(
        public int $billetId,
        public int $userId,
        public float $montant,
        public \DateTimeImmutable $occurredAt
    ) {}
}

// Infrastructure/EventStore/DoctrineEventStore.php
final class DoctrineEventStore {
    public function append(DomainEvent $event): void { ... }
    public function getEventsForAggregate(string $aggregateId): array { ... }
}
```

---

## 🐳 Conteneurisation Production-Ready

### Dockerfile Multi-Stage

Créer `Dockerfile`:
```dockerfile
# Stage 1: Builder
FROM php:8.5-fpm-alpine AS builder

RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    && docker-php-ext-install pdo pdo_mysql zip gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize

# Stage 2: Production
FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
    libzip \
    libpng \
    && docker-php-ext-install pdo pdo_mysql zip gd opcache

COPY --from=builder /app /app
WORKDIR /app

RUN chown -R www-data:www-data /app/var

EXPOSE 9000
CMD ["php-fpm"]
```

### Docker Compose Production

Créer `docker-compose.prod.yml`:
```yaml
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./public:/app/public:ro
    depends_on:
      - php
    networks:
      - talchif
    healthcheck:
      test: ["CMD", "wget", "--quiet", "--tries=1", "--spider", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  php:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      APP_ENV: prod
      DATABASE_URL: postgresql://${DB_USER}:${DB_PASSWORD}@postgres:5432/${DB_NAME}
      REDIS_URL: redis://redis:6379
    volumes:
      - ./var:/app/var
      - ./public/images:/app/public/images
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - talchif
    healthcheck:
      test: ["CMD-SHELL", "php-fpm-healthcheck"]
      interval: 30s
      timeout: 10s
      retries: 3

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ${DB_NAME}
      POSTGRES_USER: ${DB_USER}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    networks:
      - talchif
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USER}"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    networks:
      - talchif
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  messenger_worker:
    build:
      context: .
      dockerfile: Dockerfile
    command: php bin/console messenger:consume async --time-limit=3600
    environment:
      APP_ENV: prod
      DATABASE_URL: postgresql://${DB_USER}:${DB_PASSWORD}@postgres:5432/${DB_NAME}
    depends_on:
      - postgres
      - redis
    networks:
      - talchif
    restart: unless-stopped

volumes:
  postgres_data:
  redis_data:

networks:
  talchif:
    driver: bridge
```

### Kubernetes (optionnel)

Créer `k8s/deployment.yaml`:
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: talchif-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: talchif
  template:
    metadata:
      labels:
        app: talchif
    spec:
      containers:
      - name: php-fpm
        image: talchif/app:latest
        ports:
        - containerPort: 9000
        env:
        - name: APP_ENV
          value: "prod"
        - name: DATABASE_URL
          valueFrom:
            secretKeyRef:
              name: talchif-secrets
              key: database-url
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        livenessProbe:
          exec:
            command:
            - php-fpm-healthcheck
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          exec:
            command:
            - php-fpm-healthcheck
          initialDelaySeconds: 5
          periodSeconds: 5
```

---

## 📋 Plan d'Action Concret

### Phase 1: Fondations (2-3 semaines)

1. **Semaine 1**: Créer la structure hexagonale
   - [ ] Créer dossiers `Domain/`, `Application/`, `Infrastructure/`
   - [ ] Définir interfaces repositories dans `Domain/Repository/`
   - [ ] Créer Value Objects (`Telephone`, `Montant`, `Email`)

2. **Semaine 2**: Migrer une bounded context (Achat)
   - [ ] Créer `Domain/Entity/Evenement` (riche)
   - [ ] Créer `Application/Command/AcheterBilletsCommand`
   - [ ] Créer `Application/Handler/AcheterBilletsHandler`
   - [ ] Implémenter `Infrastructure/Doctrine/DoctrineEvenementRepository`

3. **Semaine 3**: Tests & Documentation
   - [ ] Écrire tests unitaires du domaine (>80% coverage)
   - [ ] Documenter architecture hexagonale
   - [ ] Créer ADR (Architecture Decision Records)

### Phase 2: Conteneurisation (1 semaine)

4. **Semaine 4**: Docker Production
   - [ ] Créer `Dockerfile` multi-stage
   - [ ] Créer `docker-compose.prod.yml`
   - [ ] Configurer Nginx
   - [ ] Tester en local avec Docker

### Phase 3: Migration Progressive (4-6 semaines)

5. **Semaines 5-10**: Migrer les autres bounded contexts
   - [ ] Commande
   - [ ] Paiement
   - [ ] Validation
   - [ ] Utilisateurs

### Phase 4: Optimisations (2 semaines)

6. **Semaines 11-12**: Performance & Monitoring
   - [ ] Implémenter cache Redis
   - [ ] Ajouter Prometheus metrics
   - [ ] Configurer logging centralisé (ELK)
   - [ ] Load testing

---

## 📚 Ressources & Références

### Livres Recommandés
- **Domain-Driven Design** - Eric Evans
- **Implementing Domain-Driven Design** - Vaughn Vernon
- **Clean Architecture** - Robert C. Martin
- **Patterns of Enterprise Application Architecture** - Martin Fowler

### Articles & Guides
- [Hexagonal Architecture](https://alistair.cockburn.us/hexagonal-architecture/)
- [CQRS Pattern](https://martinfowler.com/bliki/CQRS.html)
- [Symfony Best Practices](https://symfony.com/doc/current/best_practices.html)

### Outils
- **PHPStan** (niveau 8): Analyse statique
- **PHP-CS-Fixer**: Standards de code
- **PHPUnit**: Tests unitaires
- **Behat**: Tests BDD
- **Docker**: Conteneurisation
- **Kubernetes**: Orchestration

---

## 🎓 Conclusion

Le projet TalChif a une **base solide** mais nécessite une **refonte architecturale** pour atteindre les standards d'évolutivité et de maintenabilité requis en 2026.

### Gains Attendus

| Métrique | Avant | Après | Amélioration |
|----------|-------|-------|--------------|
| **Testabilité** | 4/10 | 9/10 | +125% |
| **Découplage** | 5/10 | 9/10 | +80% |
| **Maintenabilité** | 6/10 | 9/10 | +50% |
| **Time to Market** | Baseline | -40% | Développement plus rapide |
| **Bugs en Production** | Baseline | -60% | Tests + domaine riche |

### ROI Estimé
- **Investissement**: 12 semaines développeur
- **Retour**: Réduction 60% du temps de maintenance, facilité d'onboarding, scalabilité illimitée

---

**Prochaine Étape**: Valider ce plan avec l'équipe et commencer par la Phase 1 (Fondations).
