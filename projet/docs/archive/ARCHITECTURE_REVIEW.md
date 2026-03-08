# 🔍 Architecture Review - Production Audit
**Date:** 2026-02-13  
**Reviewer:** Senior Full-Stack Architect  
**Project:** OSEA.td - Event Ticketing Platform  
**Status:** ⚠️ **NOT PRODUCTION READY**

---

## Executive Summary

This Symfony 8.0 application shows good structure but has **critical security vulnerabilities** and **architectural inconsistencies** that must be addressed before production deployment. The codebase demonstrates understanding of Symfony patterns but lacks proper separation of concerns, transaction management, and security hardening.

**Overall Assessment:** 🔴 **CRITICAL ISSUES FOUND**

---

## 1. 🔴 CRITICAL SECURITY VULNERABILITIES

### 1.1 Empty APP_SECRET Configuration
**Severity:** 🔴 **CRITICAL**  
**Location:** `projet/.env:19`

```env
APP_SECRET=
```

**Issue:** Empty `APP_SECRET` breaks CSRF protection, session security, and encryption.

**Impact:**
- CSRF tokens are predictable/weak
- Session hijacking possible
- Encrypted data can be decrypted by attackers

**Fix:**
```bash
# Generate secure secret
php -r "echo bin2hex(random_bytes(32));"
# Add to .env.local (never commit)
APP_SECRET=<generated_secret>
```

**Recommendation:** Use Symfony Secrets Management for production.

---

### 1.2 Debug Code in Production Controllers
**Severity:** 🔴 **CRITICAL**  
**Locations:**
- `projet/src/Controller/EvenementController.php:121-137`
- `projet/src/Controller/AccueilController.php:24-26`
- `projet/src/Controller/OrganisateurEvenementController.php:65-66, 184-272`

**Issue:** Multiple `error_log()` calls expose internal application state.

**Examples:**
```php
error_log('DEBUG: showRedirect called with slug: ' . $slug);
error_log('DEBUG: Available slugs:');
error_log('DEBUG: Form submitted - isValid: ' . ($form->isValid() ? 'true' : 'false'));
```

**Impact:**
- Information disclosure
- Performance degradation
- Log file pollution
- Potential sensitive data exposure

**Fix:** Remove all debug code or wrap in `if ($this->getParameter('kernel.debug'))`.

---

### 1.3 Race Condition in Payment Processing
**Severity:** 🔴 **CRITICAL**  
**Location:** `projet/src/Controller/AchatController.php:151-202`

**Issue:** Payment processing lacks database transactions, allowing:
- Double booking (concurrent purchases)
- Inventory inconsistencies
- Payment without ticket creation

**Current Code:**
```php
// No transaction wrapper
$result = $this->paymentService->payer($total, $methodePaiement, [...]);
if (!$result->isSuccess()) {
    return $this->redirectToRoute('achat.index');
}
// Multiple entities created without transaction
foreach ($lignes as $ligne) {
    // ... create billets
    $this->entityManager->persist($billet);
}
$this->entityManager->flush(); // Single flush - no rollback on failure
```

**Impact:**
- **Financial loss:** Users can purchase tickets that don't exist
- **Data corruption:** `placesVendues` can be incorrect
- **Race conditions:** Two users buying last ticket simultaneously

**Fix:**
```php
$this->entityManager->beginTransaction();
try {
    // Lock rows for update
    foreach ($panier as $id => $quantite) {
        $evenement = $this->evenementRepository->find($id, LockMode::PESSIMISTIC_WRITE);
        // ... validation and payment
    }
    $this->entityManager->flush();
    $this->entityManager->commit();
} catch (\Exception $e) {
    $this->entityManager->rollback();
    throw $e;
}
```

---

### 1.4 Insecure File Upload Handling
**Severity:** 🔴 **CRITICAL**  
**Location:** `projet/src/Controller/OrganisateurEvenementController.php:79-91`

**Issues:**
1. **No filename sanitization:** Uses `uniqid()` but doesn't validate extension
2. **No virus scanning:** Malicious files can be uploaded
3. **No file size limits enforced:** Only form validation (can be bypassed)
4. **Guessable filenames:** `uniqid()` is predictable

**Current Code:**
```php
$newFilename = uniqid() . '.' . $affichePrincipaleFile->guessExtension();
$affichePrincipaleFile->move(
    $this->getParameter('kernel.project_dir') . '/public/images/evenements',
    $newFilename
);
```

**Impact:**
- **Path traversal attacks:** `../../../etc/passwd` in filename
- **Malware distribution:** Uploaded files served publicly
- **Storage exhaustion:** No quota limits

**Fix:**
```php
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

// Validate MIME type server-side (not just extension)
$mimeType = mime_content_type($file->getPathname());
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mimeType, $allowedMimes)) {
    throw new FileException('Invalid file type');
}

// Generate secure filename
$originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
$safeFilename = $slugger->slug($originalFilename);
$newFilename = $safeFilename . '-' . bin2hex(random_bytes(16)) . '.' . $file->guessExtension();

// Move to secure location (outside web root ideally)
$file->move($this->getParameter('kernel.project_dir') . '/public/images/evenements', $newFilename);
```

---

### 1.5 TestController Exposed in Production
**Severity:** 🔴 **CRITICAL**  
**Location:** `projet/src/Controller/TestController.php`

**Issue:** Test routes accessible in production:
- `/test/connexion`
- `/test/dashboard-theme`
- `/test/datatables`

**Impact:**
- Information disclosure
- Test data exposure
- Potential security bypass

**Fix:** Remove or protect with:
```php
#[Route('/test', name: 'test.')]
#[IsGranted('ROLE_ADMIN')]
// Or better: only enable in dev environment
```

---

### 1.6 Missing CSRF Protection on Some Routes
**Severity:** 🟠 **HIGH**  
**Location:** `projet/src/Controller/PanierController.php:127-137`

**Issue:** `supprimer()` method doesn't verify CSRF token.

**Current Code:**
```php
#[Route('/panier/supprimer/{id}', name: 'panier.supprimer', methods: ['POST'])]
public function supprimer(int $id, SessionInterface $session): RedirectResponse
{
    // No CSRF check!
    $panier = $session->get('panier', []);
    if (isset($panier[$id])) {
        unset($panier[$id]);
        // ...
    }
}
```

**Fix:**
```php
if (!$this->isCsrfTokenValid('panier_delete_' . $id, $request->request->get('_token'))) {
    throw $this->createAccessDeniedException('Invalid CSRF token');
}
```

---

## 2. 🟠 HIGH PRIORITY ARCHITECTURAL ISSUES

### 2.1 Violation of Separation of Concerns
**Severity:** 🟠 **HIGH**  
**Pattern:** Controllers contain business logic

**Examples:**

#### AchatController - Payment Logic
**Location:** `projet/src/Controller/AchatController.php:123-216`

Controllers should delegate to services:
```php
// ❌ BAD: Business logic in controller
public function paiement(Request $request, SessionInterface $session): Response
{
    // ... 50+ lines of payment processing logic
    foreach ($panier as $id => $quantite) {
        // Complex business rules here
    }
    // Ticket creation logic
    // Inventory management
}
```

**Fix:** Create `App\Service\PurchaseService`:
```php
class PurchaseService
{
    public function __construct(
        private PaymentInterface $paymentService,
        private EntityManagerInterface $em,
        private EvenementRepository $eventRepo
    ) {}
    
    public function processPurchase(Cart $cart, User $user, PaymentMethod $method): PurchaseResult
    {
        // All business logic here
    }
}
```

#### EvenementController - Data Transformation
**Location:** `projet/src/Controller/EvenementController.php:36-52`

Controllers transform entities to arrays manually:
```php
// ❌ BAD: Data transformation in controller
$evenementsArray = [];
foreach ($evenements as $evenement) {
    $evenementsArray[] = [
        'id' => $evenement->getId(),
        'slug' => $evenement->getSlug(),
        // ... 10+ lines
    ];
}
```

**Fix:** Use Symfony Serializer or DTOs:
```php
use Symfony\Component\Serializer\SerializerInterface;

public function index(Request $request, SerializerInterface $serializer): Response
{
    $evenements = $this->evenementRepository->findActiveEvents();
    $data = $serializer->normalize($evenements, 'json', ['groups' => 'event:list']);
    return $this->render('evenement/index.html.twig', ['evenements' => $data]);
}
```

---

### 2.2 Missing Transaction Management
**Severity:** 🟠 **HIGH**  
**Impact:** Data inconsistency risks

**Issues:**
1. **Payment processing:** No transactions (see 1.3)
2. **Event updates:** Multiple entities modified without transaction
3. **Billet validation:** No atomic updates

**Example - ValidationController:**
```php
// ❌ BAD: No transaction
$billet->setUtilise(true);
$billet->setDateUtilisation($now);
$billet->setValidePar($this->getUser());
$this->entityManager->flush(); // What if this fails?
```

**Fix:** Wrap critical operations:
```php
$this->entityManager->beginTransaction();
try {
    $billet->marquerCommeUtilise($this->getUser());
    $this->entityManager->flush();
    $this->entityManager->commit();
} catch (\Exception $e) {
    $this->entityManager->rollback();
    throw $e;
}
```

---

### 2.3 Duplicate Code - File Upload Logic
**Severity:** 🟠 **HIGH**  
**Locations:**
- `projet/src/Controller/OrganisateurEvenementController.php:77-91`
- `projet/src/Controller/AdminEvenementController.php:40-54`

**Issue:** Identical file upload code duplicated across controllers.

**Fix:** Extract to service:
```php
class FileUploadService
{
    public function uploadEventImage(UploadedFile $file, string $type): string
    {
        // Centralized upload logic
    }
}
```

---

### 2.4 Inconsistent Error Handling
**Severity:** 🟠 **HIGH**

**Issues:**
1. **Mixed patterns:** Some use exceptions, others return redirects
2. **No global exception handler:** Errors leak to users
3. **Inconsistent flash messages:** Different formats across controllers

**Example:**
```php
// Pattern 1: Exception
throw $this->createNotFoundException('Événement non trouvé');

// Pattern 2: Flash + Redirect
$this->addFlash('error', 'Événement non disponible');
return $this->redirectToRoute('evenement.index');

// Pattern 3: Silent continue
if (!$evenement || !$evenement->isIsActive()) {
    continue; // Silent failure
}
```

**Fix:** Standardize error handling:
```php
// Use exceptions for errors, flash for user feedback
try {
    $result = $this->purchaseService->processPurchase(...);
    $this->addFlash('success', 'Purchase completed');
} catch (InsufficientInventoryException $e) {
    $this->addFlash('error', $e->getUserMessage());
    return $this->redirectToRoute('panier.index');
} catch (\Exception $e) {
    $this->logger->error('Purchase failed', ['exception' => $e]);
    throw $this->createAccessDeniedException('Purchase failed');
}
```

---

### 2.5 Missing Validation Layers
**Severity:** 🟠 **HIGH**

**Issues:**
1. **Business rule validation:** Done in controllers instead of services
2. **No DTOs:** Raw request data used directly
3. **Missing domain validation:** Entity validation insufficient

**Example - AchatController:**
```php
// ❌ BAD: Validation in controller
if ($quantite > $evenement->getPlacesRestantes()) {
    throw new \Exception("Plus assez de places disponibles");
}
```

**Fix:** Use Value Objects and Domain Services:
```php
class TicketPurchase
{
    public function __construct(
        private Event $event,
        private int $quantity,
        private User $buyer
    ) {
        $this->validate();
    }
    
    private function validate(): void
    {
        if ($this->quantity > $this->event->getPlacesRestantes()) {
            throw new InsufficientInventoryException(...);
        }
    }
}
```

---

## 3. 🟡 MEDIUM PRIORITY ISSUES

### 3.1 Naming Inconsistencies
**Severity:** 🟡 **MEDIUM**

**Issues:**

#### Boolean Method Naming
- `Evenement::isIsActive()` ❌ (double "is")
- `Evenement::isValide()` ✅
- `Billet::isUtilise()` ✅

**Fix:** Standardize to `isActive()`, `isValid()`, `isUsed()`.

#### Repository Method Naming
- `findPaginated()` ✅
- `findPaginatedByOrganisateur()` ✅
- `countByOrganisateur()` ✅
- But: `countTotal()` ❌ (should be `count()` or `countAll()`)

---

### 3.2 Hardcoded Business Values
**Severity:** 🟡 **MEDIUM**

**Locations:**
- `projet/src/Repository/BilletRepository.php:194` - Commission rate `0.10`
- `projet/src/Controller/ValidationController.php:118-119` - Validation windows `-2 hours`, `+4 hours`
- `projet/src/Controller/EvenementController.php:152` - Popular threshold `50`

**Fix:** Move to configuration:
```yaml
# config/packages/app.yaml
app:
    commission_rate: '%env(float:COMMISSION_RATE)%'
    validation:
        start_offset: '-2 hours'
        end_offset: '+4 hours'
    events:
        popular_threshold: 50
```

---

### 3.3 Inconsistent Date Handling
**Severity:** 🟡 **MEDIUM**

**Issue:** Mix of `\DateTime` and `\DateTimeImmutable`.

**Examples:**
- Entities use `DateTimeImmutable` ✅
- `ValidationController:114` uses `new \DateTime()` ❌
- `BilletRepository:100,115` uses `new \DateTime()` ❌

**Fix:** Use `DateTimeImmutable` everywhere:
```php
$now = new \DateTimeImmutable();
```

---

### 3.4 Missing Type Safety
**Severity:** 🟡 **MEDIUM**

**Issues:**
1. **Array returns:** Repositories return `array` without type hints
2. **Mixed types:** `getPrixSimple()` returns `?float` but stores as `string`
3. **No return type hints:** Some methods lack return types

**Example:**
```php
// ❌ BAD: No return type
public function searchEvents(string $query): array
{
    // Returns array of Evenement, but type is lost
}

// ✅ GOOD: Use generics or DTOs
/**
 * @return array<Evenement>
 */
public function searchEvents(string $query): array
```

---

### 3.5 Dead Code / Unused Routes
**Severity:** 🟡 **MEDIUM**

**Found:**
- `EvenementController::showRedirect()` - Debug route that should be removed
- `TestController` - Entire controller should be removed or protected

---

## 4. 🔵 LOW PRIORITY ISSUES

### 4.1 Code Organization
**Severity:** 🔵 **LOW**

**Issues:**
- No DTOs directory
- No Value Objects
- Services mixed with controllers in some areas

**Recommendation:**
```
src/
├── Controller/
├── Entity/
├── Repository/
├── Service/
│   ├── Payment/
│   ├── Purchase/
│   └── FileUpload/
├── DTO/
├── ValueObject/
└── Event/ (for domain events)
```

---

### 4.2 Missing Documentation
**Severity:** 🔵 **LOW**

**Issues:**
- No PHPDoc for complex methods
- No API documentation
- Missing README for setup

---

### 4.3 UI/UX Inconsistencies
**Severity:** 🔵 **LOW**

**Issues:**
1. **Inconsistent spacing:** Mix of Tailwind and custom CSS
2. **No loading states:** AJAX operations lack feedback
3. **Accessibility:** Missing ARIA labels in some places

**Example:**
```html
<!-- ❌ Missing aria-label -->
<button class="public-action" for="public-menu">☰</button>

<!-- ✅ Good -->
<button class="public-action" for="public-menu" aria-label="Ouvrir le menu">☰</button>
```

---

## 5. 📊 CODE QUALITY METRICS

### 5.1 Complexity Analysis

**High Complexity Methods:**
1. `AchatController::paiement()` - 95 lines, multiple responsibilities
2. `OrganisateurEvenementController::edit()` - 120+ lines, file handling + business logic
3. `ValidationController::scanBillet()` - 150+ lines, multiple validation checks

**Recommendation:** Extract methods, use Strategy pattern for validation.

---

### 5.2 Code Duplication

**Found Duplications:**
1. File upload logic: 3 locations
2. Event array transformation: 2 locations
3. Cart calculation: 2 locations

**Recommendation:** Extract to services/helpers.

---

## 6. 🎯 RECOMMENDED REFACTORING PRIORITIES

### Phase 1: Critical Security (Week 1)
1. ✅ Fix `APP_SECRET` configuration
2. ✅ Remove all debug code
3. ✅ Add transaction management to payment processing
4. ✅ Secure file uploads
5. ✅ Remove/protect TestController

### Phase 2: Architecture Improvements (Week 2-3)
1. ✅ Extract business logic to services
2. ✅ Implement DTOs for data transfer
3. ✅ Add proper error handling
4. ✅ Standardize validation

### Phase 3: Code Quality (Week 4)
1. ✅ Fix naming inconsistencies
2. ✅ Extract duplicate code
3. ✅ Add type hints
4. ✅ Improve documentation

---

## 7. 🔒 SECURITY CHECKLIST

Before production deployment, ensure:

- [ ] `APP_SECRET` is set and secure
- [ ] All debug code removed
- [ ] CSRF protection on all state-changing routes
- [ ] File uploads validated and sanitized
- [ ] SQL injection prevention (use parameterized queries) ✅ (Doctrine handles this)
- [ ] XSS prevention (Twig auto-escapes) ✅
- [ ] Rate limiting on sensitive endpoints
- [ ] Input validation on all user inputs ✅ (Symfony Validator)
- [ ] Error messages don't leak sensitive info
- [ ] Logging configured (no sensitive data)
- [ ] HTTPS enforced
- [ ] Session security configured
- [ ] Password hashing secure ✅ (Symfony handles this)

---

## 8. 📈 PERFORMANCE CONCERNS

### 8.1 N+1 Query Problems
**Location:** Multiple controllers

**Example:**
```php
// ❌ BAD: N+1 queries
foreach ($panier as $id => $quantite) {
    $evenement = $this->evenementRepository->find($id); // Query per iteration
}
```

**Fix:** Use `findBy(['id' => array_keys($panier)])` or join queries.

### 8.2 Missing Caching
- No query result caching
- No HTTP caching headers
- No CDN for static assets

---

## 9. ✅ POSITIVE ASPECTS

1. ✅ **Good Symfony practices:** Proper use of repositories, forms, security
2. ✅ **Clean entity design:** Well-structured entities with proper relationships
3. ✅ **Separation of concerns:** Repositories properly abstracted
4. ✅ **Form validation:** Good use of Symfony Validator
5. ✅ **Security bundle:** Proper use of Symfony Security

---

## 10. 🚀 FINAL RECOMMENDATIONS

### Immediate Actions (Before Production):
1. **Fix all CRITICAL security issues** (Section 1)
2. **Add transaction management** to payment processing
3. **Remove debug code**
4. **Secure file uploads**

### Short-term Improvements (First Month):
1. Extract business logic to services
2. Implement proper error handling
3. Add DTOs for data transfer
4. Fix naming inconsistencies

### Long-term Improvements (Quarter 1):
1. Implement CQRS pattern for complex operations
2. Add event sourcing for audit trail
3. Implement caching strategy
4. Add comprehensive test coverage

---

## Conclusion

The codebase shows good understanding of Symfony but requires **significant security hardening** and **architectural improvements** before production. Focus on fixing critical security issues first, then gradually improve architecture.

**Estimated effort to production-ready:** 3-4 weeks with dedicated focus.

---

**Review completed:** 2026-02-13  
**Next review recommended:** After Phase 1 completion
