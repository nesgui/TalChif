# Tableaux dynamiques et volume de données

## État actuel

### Tableaux avec pagination côté serveur (adaptés au volume)

| Page | Contrôleur | Comportement |
|------|------------|--------------|
| **Admin – Utilisateurs** | `AdminUserController::index()` | Pagination 100 par page (`findPaginated`), liens Précédent/Suivant. DataTables applique tri/recherche/export sur la page courante uniquement. |
| **Organisateur – Mes événements** | `OrganisateurEvenementController::index()` | Pagination 100 par page (`findPaginatedByOrganisateur`), liens Précédent/Suivant. Idem DataTables sur la page courante. |
| **Validation – Historique** | `ValidationController::historique()` | Pagination 50 par page (`findUsedTickets($limit, $offset)`), liens Précédent/Suivant. Pas de DataTables en mode "Tous" ; seulement les 50 lignes de la page. |

### Tableaux en données statiques (prototype)

- **Admin – Événements** : 2 lignes en dur, pas de données BDD.
- **Admin – Organisateurs** : 5 lignes en dur.
- **Admin – Finance** : 8 lignes en dur.

À terme, brancher ces vues sur de vrais repositories avec pagination (ou API DataTables server-side).

### Autres tableaux

- **Panier, Portefeuille, Achat confirmation, etc.** : peu de lignes (contenu utilisateur courant). Pas de pagination nécessaire.
- **Event billets / evenement show** : selon le contexte, limiter ou paginer si un événement a des milliers de billets.

---

## Modifications effectuées

1. **UserRepository**
   - `findPaginated(int $page, int $limit)` : liste paginée (ordre id DESC).
   - `countTotal()` : nombre total d’utilisateurs.
   - `remove(User, bool)` : ajout pour cohérence avec le contrôleur.

2. **EvenementRepository**
   - `findPaginatedByOrganisateur(User, int $page, int $limit)` : événements d’un organisateur paginés.

3. **AdminUserController**
   - Constante `USERS_PER_PAGE = 100`.
   - `index(Request)` : récupère `page` en query, appelle `findPaginated` et `countTotal`, passe `users`, `page`, `totalPages`, `total`, `limit` au template.

4. **OrganisateurEvenementController**
   - Constante `EVENTS_PER_PAGE = 100`.
   - `index(Request)` : idem avec `findPaginatedByOrganisateur` et `countByOrganisateur`.

5. **Templates**
   - **admin_user/index.html.twig** : texte "Affichage de X à Y sur Z", pagination Précédent / Page X/Y / Suivant.
   - **organisateur_evenement/index.html.twig** : même principe.

6. **CSS**
   - `.table-pagination`, `.pagination-inner`, `.pagination-info` pour la navigation sous les tableaux.

7. **datatables-service.js**
   - `pageLength: 25`, `lengthMenu: [10, 25, 50, 100]` (plus de "Tous" pour éviter de charger des milliers de lignes en une fois).
   - `deferRender: true` pour limiter le nombre de `<tr>` rendus au besoin.
   - Commentaire sur la pagination serveur et l’option future `serverSide: true` + AJAX.

---

## Pour aller plus loin (très gros volumes)

- **DataTables en mode server-side** : une route JSON (ex. `GET /admin/utilisateurs/datatable`) qui renvoie `{ draw, recordsTotal, recordsFiltered, data }` avec tri/filtre côté serveur. Dans le template, initialiser DataTables avec `serverSide: true`, `ajax: { url: ... }`. Permet de garder tri/recherche sur tout le jeu de données sans tout charger en mémoire.
- **Admin événements / organisateurs / finance** : idem, passer par des données réelles + pagination ou endpoint DataTables server-side.
- **Export Excel/CSV** : pour "tout exporter", prévoir un job ou une route dédiée qui génère le fichier par lots (streaming ou queue) au lieu de tout charger en PHP.
