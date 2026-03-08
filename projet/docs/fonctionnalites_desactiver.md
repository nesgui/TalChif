# Fonctionnalités désactivées / non implémentées — TalChif

> Document de suivi des fonctionnalités qui ne sont pas encore connectées aux données réelles ou qui restent à implémenter.

---

## Pages admin avec données factices (placeholder)

| Page | Route | État | Description |
|------|-------|------|-------------|
| **Admin Finance** | `/admin/finance` | Données factices hardcodées | Le tableau de transactions affiche des données fictives (TX-1001 à TX-1008). Aucune requête Doctrine. Le bouton "Modifier" la commission pointe vers `#`. |
| **Admin Organisateurs** | `/admin/organisateurs` | Données factices hardcodées | Liste d'organisateurs fictifs (Alpha Events, Beta Productions, etc.). Les boutons Voir/Modifier/Bloquer pointent vers `#`. |
| **Admin Sécurité** | `/admin/securite` | Données factices hardcodées | Tableau de contrôles et logs fictifs. Les logs affichent des exemples statiques, pas les vrais `LogSecurite` de la BDD. |

---

## Fonctionnalités non implémentées

| Fonctionnalité | Fichier concerné | Détail |
|----------------|------------------|--------|
| **Vérification email** | `AuthController.php:80` | `$user->setIsVerified(false)` — le flag est posé mais aucun email de vérification n'est envoyé. Pas de route `/verify-email`. |
| **Paiement réel Mobile Money** | `StubPaymentService.php` | Le service de paiement est un stub de développement qui simule les transactions. À remplacer par `MomoPaymentService` ou équivalent en production. |
| **Carte bancaire** | `StubPaymentService.php` | Mentionnée dans le stub mais aucune intégration réelle. |
| **Commission variable par organisateur** | `admin_organisateur/index.html.twig` | Le template affiche des commissions variables (7%, 8%, 10%, 12%, 15%) mais le système ne gère qu'une commission fixe de 10% (config `app.yaml`). |
| **Mode hors ligne validation** | `admin_securite/index.html.twig` | Affiché comme "À cadrer" dans le tableau des contrôles. Non implémenté. |

---

## Pages / liens cassés ou pointant vers `#`

- `admin_finance/index.html.twig` : bouton "Modifier" la commission → `href="#"`
- `admin_organisateur/index.html.twig` : tous les boutons d'action (Voir, Modifier, Bloquer/Activer) → `href="#"`

---

## Notes

- Les pages admin Finance, Organisateurs et Sécurité ont été créées pour le prototypage UI uniquement. Elles devront être reconnectées aux repositories Doctrine et aux vrais services quand les fonctionnalités seront prêtes.
- La page de validation QR (`/validation`) utilise désormais `html5-qrcode` pour l'accès caméra réel (implémenté).
- Le design de billet a été dissocié du formulaire de création d'événement et se gère via la page dédiée `/organisateur/evenement/{id}/billet-design`.
