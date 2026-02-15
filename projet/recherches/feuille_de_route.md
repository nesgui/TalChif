# Feuille de route – TalChif (Application de gestion des événements nationaux)

Structure stratégique des étapes et objectifs par tâches. Cocher à chaque évolution validée.

---

## 1. Partie Client

| # | Tâche | Objectif | Statut |
|---|--------|----------|--------|
| 1.1 | Navigation libre | Accès liste événements sans compte | ☑ |
| 1.2 | Détail événement | Affiches, date, lieu, types billets, prix, description | ☑ |
| 1.3 | Création de compte / Connexion | Inscription (nom, email, mot de passe) + connexion | ☑ |
| 1.4 | Achat de billets | Sélection événement, type (SIMPLE/VIP), quantité | ☑ |
| 1.5 | Paiement | Mobile Money / Carte bancaire (intégration réelle) | ☑ * |
| 1.6 | Billet électronique | QR Code unique, email + espace personnel | ☑ |
| 1.7 | Portefeuille client | Voir tous les billets achetés, QR Code par billet | ☑ |
| 1.8 | Notifications (optionnel) | Alerte nouvel événement publié | ☐ |

* 1.5 : Stub en place (StubPaymentService) ; intégration API réelle à brancher.

**Workflow client :** Ouverture → Liste événements → Détail → Achat (connexion/inscription) → Paiement → Billet avec QR → Jour J : présentation QR à l’entrée.

---

## 2. Partie Organisateur

| # | Tâche | Objectif | Statut |
|---|--------|----------|--------|
| 2.1 | Création événement | Formulaire complet (affiche, autres affiches, image billet, nom, date, lieu, types billets, places, description) | ☑ |
| 2.2 | Publication directe | Pas de validation admin préalable, publication immédiate | ☑ |
| 2.3 | Dashboard public événement | Vitrine avec flyers, infos (lieu, prix, types billets) | ☑ |
| 2.4 | Dashboard privé organisateur | Ventes temps réel, billets vendus par type, revenus (brut / après commission), places restantes | ☑ |
| 2.5 | Export participants | Liste (noms, emails, type billet) | ☑ |
| 2.6 | QR Codes | Téléchargement / visualisation des QR par billet vendu | ☑ |
| 2.7 | Statistiques | Performance de l’événement | ☐ |
| 2.8 | Contrôle d’entrée | App/interface pour scanner QR à l’entrée, validation temps réel ou hors ligne sécurisée | ☑ |

---

## 3. Partie Administrateur

| # | Tâche | Objectif | Statut |
|---|--------|----------|--------|
| 3.1 | Tableau de bord global | Événements actifs/passés, billets vendus, revenus, commission, classement événements | ☐ |
| 3.2 | Graphiques et stats | Barres, courbes, camemberts | ☐ |
| 3.3 | Gestion organisateurs | Liste, valider / bloquer / supprimer, historique événements et revenus, commission par organisateur | ☐ |
| 3.4 | Gestion événements | Voir tous les événements et stats, suspendre si fraude / contenu inapproprié, classement | ☐ |
| 3.5 | Gestion financière | Taux commission, suivi transactions, reversement organisateurs, rapports Excel/PDF | ☐ |
| 3.6 | Sécurité & anti-fraude | Détection doublons billets, anomalies, logs, blocage client/billet suspect | ☐ |
| 3.7 | Support & communication | Notifications aux organisateurs, messagerie interne, support client | ☐ |

---

## 4. Technique & Qualité

| # | Tâche | Objectif | Statut |
|---|--------|----------|--------|
| 4.1 | Entités & base de données | User, Evenement, Billet, migrations | ☑ |
| 4.2 | Authentification & rôles | CLIENT, ORGANISATEUR, ADMIN, sécurisation des routes | ☑ |
| 4.3 | Abstraction paiement | Interface + stub pour Mobile Money / Carte (préparation intégration réelle) | ☑ |
| 4.4 | Validation QR & anti-fraude | Vérification organisateur/événement, billet valide, fenêtre horaire, journalisation | ☑ |
| 4.5 | Système d’avis (optionnel) | Notes et avis sur les événements | ☐ |
| 4.6 | Vérifications automatisées | Vérifications fonctionnelles (liste événements, auth, achat) | ☑ * |
| 4.7 | Documentation | readme_fr.md à jour à chaque modification validée | ☑ |

* 4.6 : Vérifications EvenementController et StubPaymentService en place.
* 2.7 (Statistiques) : validé — cocher ☑ manuellement si l’affichage indique encore ☐.

---

## Notes de suivi

- **Validation des tâches :** À chaque tâche remplie et validée par l’équipe, cocher le statut (☐ → ☑) et faire une mise au point avant de passer à la suivante.
- **Enregistrement des mises à jour :** Après validation, demander si la mise à jour doit être enregistrée sous le pseudo **DIEUDONNÉ** ou **VITAL** avant de mettre à jour le `readme_fr.md`.
