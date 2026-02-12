# État des lieux – Fonctionnalités OCEA.td

Document de référence : comparaison README / feuille de route vs implémentation réelle. Objectif : cohérence des menus et affichage de ce qui fonctionne uniquement.

---

## 1. Partie Client (README / feuille de route)

| Tâche | Statut | Détail |
|-------|--------|--------|
| 1.1 Navigation libre | ✅ Fait | Liste événements sans compte : `/`, `/evenements` |
| 1.2 Détail événement | ✅ Fait | `/evenements/{slug}-{id}` : affiches, date, lieu, types billets, prix, description |
| 1.3 Création compte / Connexion | ✅ Fait | `/inscription`, `/connexion` (nom, email, mot de passe) |
| 1.4 Achat de billets | ✅ Fait | Panier + sélection événement, type (SIMPLE/VIP), quantité |
| 1.5 Paiement | ⚠️ Stub | Mobile Money / Carte : `StubPaymentService` (simulation), à remplacer par API réelle |
| 1.6 Billet électronique | ✅ Fait | QR Code unique, email + espace personnel (`/mes-billets`, `/achat/confirmation`) |
| 1.7 Portefeuille client | ✅ Fait | `/portefeuille`, `/mes-billets` (avenir, passés) : tous les billets + QR |
| 1.8 Notifications (optionnel) | ☐ Non fait | Alerte nouvel événement : non implémenté |

---

## 2. Partie Organisateur

| Tâche | Statut | Détail |
|-------|--------|--------|
| 2.1 Création événement | ✅ Fait | Formulaire complet : affiche, autres affiches, image billet, nom, date, lieu, types billets, places, description |
| 2.2 Publication directe | ✅ Fait | Pas de validation admin, publication immédiate |
| 2.3 Dashboard public événement | ✅ Fait | Vitrine via `/evenements/{slug}-{id}` (flyers + infos) |
| 2.4 Dashboard privé organisateur | ✅ Fait | `/organisateur` : ventes temps réel, billets par type, revenus, places restantes |
| 2.5 Export participants | ✅ Fait | `/organisateur/evenement/{id}/export` (liste noms, emails, type billet) |
| 2.6 QR Codes | ✅ Fait | `/organisateur/evenement/{id}/qrcodes` : téléchargement / visualisation |
| 2.7 Statistiques | ✅ Fait | `/organisateur/evenement/{id}/stats` et `/performance` (graphiques) |
| 2.8 Contrôle d'entrée | ✅ Fait | `/validation` (scan QR), `/validation/historique` ; API scan + lookup |

---

## 3. Partie Administrateur

| Tâche | Statut | Détail |
|-------|--------|--------|
| 3.1 Tableau de bord global | ⚠️ Prototype UI | `/admin` : indicateurs et accès en dur (42 événements, 8120 billets, etc.) |
| 3.2 Graphiques et stats | ☐ Non fait | Pas de graphiques/camemberts réels en admin |
| 3.3 Gestion organisateurs | ⚠️ Prototype UI | `/admin/organisateurs` : tableau statique (données fictives) |
| 3.4 Gestion événements | ⚠️ Partiel | `/admin/evenements` : liste statique (prototype) ; `/admin/evenements/creer` existe mais le README indique que l’admin ne crée pas les événements (rôle organisateur) |
| 3.5 Gestion financière | ⚠️ Prototype UI | `/admin/finance` : commission 10 %, tableau transactions fictif |
| 3.6 Sécurité & anti-fraude | ⚠️ Prototype UI | `/admin/securite` : liens vers validation + historique (réels) + tableaux de contrôle fictifs |
| 3.7 Support & communication | ☐ Non fait | Notifications organisateurs, messagerie, support : non implémenté |

**Fonctionnel en admin :**  
- **Utilisateurs** : `/admin/utilisateurs` (liste paginée réelle, création, édition, suppression).

---

## 4. Technique & Qualité

| Tâche | Statut |
|-------|--------|
| 4.1 Entités & BDD | ✅ User, Evenement, Billet, migrations |
| 4.2 Authentification & rôles | ✅ CLIENT, ORGANISATEUR, ADMIN ; routes protégées |
| 4.3 Abstraction paiement | ✅ Interface + StubPaymentService (intégration réelle à brancher) |
| 4.4 Validation QR & anti-fraude | ✅ Vérification organisateur/événement, billet valide, historique |
| 4.5 Système d’avis (optionnel) | ☐ Non fait |
| 4.6 Tests automatisés | ✅ Partiel (EvenementController, StubPaymentService) |
| 4.7 Documentation | En cours (readme_fr, feuille_de_route) |

---

## 5. Menus et cohérence – Actions effectuées

- **Portefeuille** : pour `ROLE_ADMIN`, lien « Créer un événement » remplacé par « Back-office admin » → `/admin` (conformément au README : l’admin supervise, ne crée pas les événements).
- **Sidebar organisateur** (layout dashboard) :  
  - « Ventes effectuées » et « Vue globale » doublons → un seul lien « Vue globale » vers le tableau de bord.  
  - « Participants (prototype) » retiré du menu (les participants sont accessibles par événement depuis les cartes du dashboard).
- **Sidebar admin** :  
  - Finance : un seul lien « Finance » (suppression du doublon « Commission (prototype) »).  
  - Sécurité : un seul lien « Sécurité & anti-fraude » (suppression du doublon « Logs (prototype) »).
- **Dashboard organisateur** : suppression des cartes « Rapports » et « Paramètres » (alertes non implémentées) pour ne garder que les actions fonctionnelles (Créer un événement, Scanner QR Codes).
- **Sécurité** : règles d’accès explicites pour `/mes-billets` (IS_AUTHENTICATED_FULLY) et `/validation` (ROLE_ORGANISATEUR déjà géré dans les contrôleurs).

---

## 6. Résumé : ce qui est réellement utilisable

- **Client** : navigation, détail événement, inscription/connexion, panier, achat (paiement simulé), billets avec QR, portefeuille, mes billets (avenir / passés).
- **Organisateur** : tableau de bord, création/édition/suppression d’événements, stats par événement, participants, QR codes, export, performance (graphiques), validation des billets (scan + historique).
- **Admin** : tableau de bord (UI prototype), utilisateurs (CRUD réel), événements (liste prototype + formulaire de création à réattribuer ou restreindre), finance (prototype), sécurité (prototype + liens vers validation/historique).

Pour une version « propre » orientée production, il est recommandé de :  
- brancher les pages admin (dashboard, événements, organisateurs, finance, sécurité) sur des données réelles ou de les masquer jusqu’à implémentation ;  
- remplacer le stub de paiement par l’intégration réelle Mobile Money / Carte.
