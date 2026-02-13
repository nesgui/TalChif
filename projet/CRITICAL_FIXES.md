# 🚨 CRITICAL FIXES - Action Items

**Priority:** Fix these BEFORE any production deployment

---

## 1. APP_SECRET Configuration (5 minutes)

**File:** `projet/.env.local` (create if doesn't exist)

```bash
# Generate secret
php -r "echo bin2hex(random_bytes(32));"

# Add to .env.local
APP_SECRET=<paste_generated_secret_here>
```

**Verify:** Check `config/packages/framework.yaml` uses `'%env(APP_SECRET)%'` ✅

---

## 2. Remove Debug Code (30 minutes)

**Files to fix:**

### `projet/src/Controller/EvenementController.php`
- **Lines 121-137:** Remove `showRedirect()` method entirely (dead code)
- **Lines 121-137:** Remove all `error_log()` calls

### `projet/src/Controller/AccueilController.php`
- **Lines 24-26:** Remove debug logging

### `projet/src/Controller/OrganisateurEvenementController.php`
- **Lines 63-67:** Remove debug logging
- **Lines 184-272:** Remove all `error_log()` calls

**Search command:**
```bash
grep -r "error_log" projet/src/Controller/
```

---

## 3. Add Transaction to Payment Processing (1 hour)

**File:** `projet/src/Controller/AchatController.php`

**Current code (lines 151-202):**
```php
// ❌ NO TRANSACTION
$result = $this->paymentService->payer(...);
foreach ($lignes as $ligne) {
    // Create billets
    $this->entityManager->persist($billet);
}
$this->entityManager->flush();
```

**Replace with:**
```php
use Doctrine\DBAL\LockMode;

$this->entityManager->beginTransaction();
try {
    // Lock events for update to prevent race conditions
    $lockedEvents = [];
    foreach ($panier as $id => $quantite) {
        $evenement = $this->evenementRepository->find($id, LockMode::PESSIMISTIC_WRITE);
        if (!$evenement || !$evenement->isIsActive()) {
            throw new \RuntimeException("Event {$id} not available");
        }
        if ($quantite > $evenement->getPlacesRestantes()) {
            throw new \RuntimeException("Insufficient places for event {$id}");
        }
        $lockedEvents[$id] = ['evenement' => $evenement, 'quantite' => $quantite];
        $total += $evenement->getPrixSimple() * $quantite;
    }

    if ($total <= 0) {
        throw new \RuntimeException('Invalid cart');
    }

    // Process payment
    $result = $this->paymentService->payer($total, $methodePaiement, [
        'telephone' => $telephone,
        'email' => $user->getUserIdentifier(),
    ]);

    if (!$result->isSuccess()) {
        throw new \RuntimeException('Payment failed: ' . $result->getMessage());
    }

    $transactionId = $result->getTransactionId();

    // Create tickets
    foreach ($lockedEvents as $ligne) {
        $evenement = $ligne['evenement'];
        $quantite = $ligne['quantite'];

        for ($i = 0; $i < $quantite; $i++) {
            $billet = new Billet();
            $billet->setQrCode($this->generateQrCode());
            $billet->setType('SIMPLE');
            $billet->setPrix($evenement->getPrixSimple());
            $billet->setEvenement($evenement);
            $billet->setClient($user);
            $billet->setOrganisateur($evenement->getOrganisateur());
            $billet->setTransactionId($transactionId);
            $billet->setStatutPaiement('PAYE');
            $billet->validerPaiement();
            $this->entityManager->persist($billet);
        }

        $evenement->setPlacesVendues($evenement->getPlacesVendues() + $quantite);
    }

    $this->entityManager->flush();
    $this->entityManager->commit();

    $this->addFlash('success', "📱 SMS de confirmation envoyé au {$telephone}");
    $this->addFlash('success', "💳 {$result->getMessage()}");
    $this->addFlash('info', '🎫 Vos billets ont été générés avec succès.');
    $session->remove('panier');

    return $this->redirectToRoute('achat.confirmation', [
        'transactionId' => $transactionId,
    ]);
} catch (\Exception $e) {
    $this->entityManager->rollback();
    $this->addFlash('error', 'Erreur lors du paiement : ' . $e->getMessage());
    return $this->redirectToRoute('achat.index');
}
```

---

## 4. Secure File Uploads (2 hours)

**File:** `projet/src/Controller/OrganisateurEvenementController.php`

**Create new service:** `projet/src/Service/FileUploadService.php`

```php
<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        private SluggerInterface $slugger,
        private string $projectDir
    ) {
    }

    public function uploadEventImage(UploadedFile $file, string $subdirectory = 'evenements'): string
    {
        // Validate MIME type server-side
        $mimeType = mime_content_type($file->getPathname());
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new FileException('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
        }

        // Validate file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new FileException('File size exceeds 5MB limit.');
        }

        // Generate secure filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename)->toString();
        $extension = $file->guessExtension();
        $newFilename = $safeFilename . '-' . bin2hex(random_bytes(16)) . '.' . $extension;

        // Ensure directory exists
        $uploadDir = $this->projectDir . '/public/images/' . $subdirectory;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move file
        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            throw new FileException('Failed to upload file: ' . $e->getMessage());
        }

        return '/images/' . $subdirectory . '/' . $newFilename;
    }
}
```

**Update controller to use service:**

```php
use App\Service\FileUploadService;

public function __construct(
    private EvenementRepository $evenementRepository,
    private FileUploadService $fileUploadService
) {
}

// In create() method, replace file upload code:
$affichePrincipaleFile = $form->get('affichePrincipale')->getData();
if ($affichePrincipaleFile instanceof UploadedFile) {
    try {
        $path = $this->fileUploadService->uploadEventImage($affichePrincipaleFile);
        $evenement->setAffichePrincipale($path);
    } catch (FileException $e) {
        $this->addFlash('error', $e->getMessage());
        return $this->render('organisateur_evenement/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
```

**Register service:** `projet/config/services.yaml`
```yaml
services:
    App\Service\FileUploadService:
        arguments:
            $projectDir: '%kernel.project_dir%'
```

---

## 5. Protect/Remove TestController (10 minutes)

**Option A: Remove entirely**
```bash
rm projet/src/Controller/TestController.php
rm -rf projet/templates/test/
```

**Option B: Protect with environment check**
```php
#[Route('/test', name: 'test.')]
final class TestController extends AbstractController
{
    public function __construct(
        private string $kernelEnvironment
    ) {
        if ($this->kernelEnvironment !== 'dev') {
            throw $this->createAccessDeniedException('Test routes only available in dev');
        }
    }
    
    // ... rest of controller
}
```

---

## 6. Add CSRF to PanierController::supprimer() (5 minutes)

**File:** `projet/src/Controller/PanierController.php`

**Current (line 127):**
```php
#[Route('/panier/supprimer/{id}', name: 'panier.supprimer', methods: ['POST'])]
public function supprimer(int $id, SessionInterface $session): RedirectResponse
{
    // No CSRF check!
```

**Replace with:**
```php
#[Route('/panier/supprimer/{id}', name: 'panier.supprimer', methods: ['POST'])]
public function supprimer(int $id, Request $request, SessionInterface $session): RedirectResponse
{
    if (!$this->isCsrfTokenValid('panier_delete_' . $id, $request->request->get('_token'))) {
        throw $this->createAccessDeniedException('Invalid CSRF token');
    }

    $panier = $session->get('panier', []);
    // ... rest of method
```

**Update template:** Add CSRF token to delete form
```twig
<form method="post" action="{{ path('panier.supprimer', {id: ligne.id}) }}">
    <input type="hidden" name="_token" value="{{ csrf_token('panier_delete_' ~ ligne.id) }}">
    <button type="submit">Supprimer</button>
</form>
```

---

## Verification Checklist

After implementing fixes:

- [ ] `APP_SECRET` is set and not empty
- [ ] No `error_log()` calls in production code
- [ ] Payment processing uses transactions
- [ ] File uploads validate MIME types server-side
- [ ] TestController removed or protected
- [ ] All POST routes have CSRF protection
- [ ] Run tests: `php bin/phpunit`
- [ ] Check logs: `tail -f var/log/dev.log` (should be clean)

---

## Testing After Fixes

1. **Test payment flow:**
   - Add items to cart
   - Complete purchase
   - Verify tickets created
   - Verify inventory updated correctly

2. **Test concurrent purchases:**
   - Open two browsers
   - Both try to buy last ticket
   - Only one should succeed

3. **Test file upload:**
   - Upload valid image ✅
   - Try uploading PHP file ❌ (should fail)
   - Try uploading large file ❌ (should fail)

4. **Test CSRF:**
   - Try deleting cart item without token ❌ (should fail)

---

**Estimated total time:** 4-5 hours

**Priority order:**
1. APP_SECRET (5 min) - **DO THIS FIRST**
2. Remove debug code (30 min)
3. Add transactions (1 hour)
4. Secure uploads (2 hours)
5. Protect TestController (10 min)
6. Add CSRF (5 min)
