OSEA.td application web centralisée sur le modèle SaaS (Software as a Service)


```
DÉBUT DE NOTE IMPORTANTE À IGNORER PAR L'IA, DESTINÉE UNIQUEMENT EN LECTURE AUX ÉDITEURS DE CODE CURSOR OU WINDSURF :

Ce travail est collaboratif !

Langue du projet :
Nous préférons que vous rédigiez en français : le code source, les commentaires, les noms de fichiers non critiques, les noms de classes, les noms de fonctions, les noms de pages, etc.

Structure du projet : 
À la racine, en dehors du répertoire projet/, tu trouvera le fichier readme.md dans lequel se trouve toute la présentation et la structure du projet à développer.
Dans le répertoire recherches (./projet/recherches/), vous trouverez un fichier readme_fr.md.

Règles de mise à jour du fichier readme_fr.md : 
À chaque mise à jour du code, ajout de fonctionnalités, amélioration du code ou toute autre modification, vous mettrez à jour ce fichier en y insérant ces modifications.

La règle stricte à suivre est : 
À chaque modification terminée, testée et validée par nous, tu demanderas une confirmation pour savoir si cette mise à jour doit être enregistrée sous le pseudo de << DIEUDONNÉ >> ou << VITAL >> avant de mettre à jour le readme_fr.

Procédure de mise à jour : 
Lire l'existant sur le readme_fr
Faire une comparaison avec la nouvelle mise à jour
Si ce n'est pas une mise à jour déjà existante, insérer en créant une nouvelle ligne avec un titre comme ceci :

Exemple 1 :
________________ DIEUDONNÉ [date + Heure] ________________ 
Description générale : .......

Fonctionnalités ajoutées : .....
Ou modifications apportées : ....
etc.
etc.

Exemple 2 :
________________ VITAL 12-02-2026 13h45 ________________ 
Description générale : "Améliorations et ajouts des fonctionnalités de l'étape 3 du cahier des charges, concernant la page d'authentification user"

Fonctionnalités ajoutées : .....
Ou modifications apportées : ....
etc.
etc.

FIN DE NOTE IMPORTANTE À IGNORER PAR L'IA, DESTINÉE UNIQUEMENT EN LECTURE AUX ÉDITEURS DE CODE CURSOR OU WINDSURF :
 ```
.


📑 Spécification Fonctionnelle – Application Web de Gestion des Événements Nationaux

________________________________________

🎯 Objectif du projet  
Créer une application web nationale permettant :

    • la gestion et la promotion des événements (soirées, fêtes, concerts, matchs de foot, anniversaires, etc.),

    • la vente de billets électroniques intégrant un QR Code unique pour chaque client,

    • une relation tripartite entre :
        o Clients (participants),  
        o Organisateurs d’événements,  
        o Administrateur de la plateforme.



________________________________________

📌 Documentation – Partie Client (mise à jour)

🎭 Acteur : Client
1.	Navigation libre

    o	Accès direct à la liste des événements disponibles sans obligation de créer un compte.

    o	Possibilité de consulter les détails d’un événement (affiches, date, lieu, types de billets, prix, description).


2.	Création de compte / Connexion (uniquement au moment de l’achat)

    o	Lorsqu’il veut acheter un ticket, il doit se créer un compte.

    o	Formulaire d’inscription :
        - Nom complet
        - Email
        - Mot de passe
        - Confirmation du mot de passe

    o   Connexion via :

        -Email

        -Mot de passe.


3.	Achat du ticket

    o	Sélection de 
        - L’événement,
        - le type de billet (SIMPLE / VIP),
        - le nombre de billets.

    o	Paiement via :
        - Mobile Money,
        - Carte bancaire.

    o	Génération immédiate d’un billet électronique avec QR Code unique, reçu :
    ­	- par email,
    ­	- et accessible dans l’espace personnel du client.


4.	Portefeuille client

    o	Accède à son espace personnel après connexion.

    o	Peut voir tous ses billets achetés.
    
    o	Chaque billet contient un QR Code à présenter à l’entrée de l’événement.

5.	Notifications

    o	Peut être notifié lorsqu’un nouvel événement est publié (optionnel).



________________________________________
🔄 Workflow côté client (simplifié)

1.	Ouverture de l’appli → accès direct à la liste des événements.
2.	Consultation → des détails d’un événement (description, lieu, date, prix).
3.	Achat → création de compte ou connexion.
4.	Paiement → génération d’un billet électronique avec QR Code.
5.	Jour J → présentation du QR Code pour validation à l’entrée.



________________________________________
 
📌 Documentation – Partie Organisateur (mise à jour)

🎭 Acteur : Organisateur

1.	Création & gestion des événements


    o	L’organisateur dispose d’un formulaire complet pour créer un événement.


    o	Champs du formulaire :

        -	Affiche principale → image mise en avant sur l’application.

        -	Autres affiches (upload multiples) → accessibles quand un client clique sur l’événement.

        -	Image du billet d’accès → visuel du ticket (exemple : simple design du billet).

        -	Nom de l’événement.

        -	Date (jour/heure de l’événement).

        -	Lieu (ville + adresse précise).

        -	Types de billets → possibilité d’ajouter différents types (ex : SIMPLE et/ou VIP).

        -	Nombre de places disponibles (par type de billet).

        -	Petite description de l’événement.


👉 Une fois soumis, ces informations alimentent automatiquement une page publique de l’événement, qui sert de vitrine (dashboard public).

    o Sur ce dashboard public, les affiches défilent ou sont consultables en galerie, mais elles restent reliées au même jeu d’instructions (prix, types de billets, lieu, etc.).


⚠️ Important : La création d’événement ne nécessite pas de validation préalable de l’administrateur. L’organisateur publie directement son événement.



________________________________________
2.	Dashboard public (événement)

    •	Page vitrine de l’événement accessible aux clients.

    •	Affiches(flyer) visibles en galerie.

    •	Infos pratiques reliées (lieu, prix, types de billets, description).



________________________________________
3.	Dashboard spécifique (privé) de l’organisateur
    o	Permet de suivre les ventes en temps réel :

        -	Nombre de billets vendus (par type).

        -	Revenus générés (brut et après commission admin).

        -	Places restantes.


    o	Outils disponibles :

        -	Exportation de la liste des participants (noms, emails, type de billet).

        -	Téléchargement/visualisation des QR Codes pour chaque billet vendu.

        -	Statistiques de performance de l’événement



________________________________________
4.	Contrôle d’accès le jour de l’événement
    o	L’organisateur (ou son équipe) utilise une application mobile dédiée pour scanner les QR Codes des billets à l’entrée.


    o	Chaque QR Code est unique, non réutilisable, et validé en temps réel ou hors ligne uniquement après export sécurisé des données chiffrées vers l’application de contrôle. Une fois exportés, ces QR Codes sont désactivés sur la plateforme et utilisables exclusivement sur l’appareil de vérification.
    
    Le tableau de bord peut afficher :

        -	Nombre de participants déjà entrés.

        -	Nombre de billets restants à valider.



________________________________________
👉 En résumé :

    •	Deux espaces :

        o	Un dashboard public de l’événement → vitrine avec flyer + infos.

        o	Un dashboard privé de l’organisateur → suivi des ventes + contrôle d’accès.


    •	Pas de validation par l’admin avant publication d’un événement.
 

📌 Documentation – Partie Administrateur
🎭 Acteur : Administrateur (superviseur global)

L’Admin a pour rôle principal de superviser, sécuriser et garantir le bon fonctionnement de la plateforme. Contrairement à l’organisateur, il n’intervient pas directement sur la création d’événements, mais il :

    •	supervise toutes les ventes,

    •	gère les utilisateurs,

    •	contrôle la monétisation (commissions),

    •	et garantit la sécurité anti-fraude.



________________________________________
⚙️ Fonctions principales de l’Admin

1.	Tableau de bord global (Back-office moderne)

    o	Vue en temps réel sur :

        -	Nombre total d’événements actifs et passés.

        -	Nombre total de billets vendus (par période).

        -	Revenus générés par la plateforme.

        -	Commission totale perçue par l’admin.

        -	Classement des événements les plus populaires (top ventes, top affluence).


    o	Graphiques et statistiques dynamiques (ex. barres, courbes, camemberts).



________________________________________
2.	Gestion des organisateurs

    o	Liste complète des organisateurs inscrits.


    o	Possibilité de :

    -	Valider / bloquer / supprimer un compte organisateur en cas de fraude ou inactivité.

    -	Consulter l’historique de leurs événements et leurs revenus.

    -	Fixer une commission spécifique à un organisateur (ex. gros partenaire vs petit organisateur).



________________________________________
3.	Gestion des événements
    o	Visualiser tous les événements publiés (avec leurs stats).

    o	Suspendre un événement en cas de fraude, de contenu inapproprié ou de demande légale.

    o	Classer les événements (par popularité, date, type).



________________________________________
4.	Gestion financière & paiements

    o	Définir le taux de commission standard de la plateforme (ex. 10% sur chaque billet vendu).

    o	Suivi des transactions : chaque paiement est enregistré et consultable.

    o	Reversement automatique ou manuel des revenus aux organisateurs après déduction de la commission.

    o	Génération de rapports financiers (export Excel/PDF).



________________________________________
5.	Sécurité & contrôle anti-fraude

    o	Système de détection de doublons de billets.

    o	Contrôle d’anomalies (ex. trop de billets générés pour un nombre de places limité).

    o	Historique complet de toutes les opérations (logs).

    o	Possibilité de bloquer un client ou un billet suspect.



________________________________________
6.	Support & communication

    o	Envoi de notifications officielles aux organisateurs (nouveautés, mises à jour, règles).

    o	Outil de messagerie interne ou centre d’aide.

    o	Accès à un système de support client (tickets, réclamations).

👉 En résumé, l’Admin n’est pas un simple superviseur : il devient l’épine dorsale de la plateforme, avec un back-office moderne orienté :

    •	Business (revenus/commissions)

    •	Sécurité (anti-fraude)

    •	Supervision (organisateurs & événements)

    •	Expérience utilisateur (clients & organisateurs)



________________________________________
3️⃣ Failles potentielles du projet (à anticiper)

⚠️ Failles techniques

    - Fraude aux billets :

        duplication de QR Codes via capture d’écran si la validation est mal synchronisée.

    - Mode hors ligne mal contrôlé :

        risque de double validation si plusieurs appareils utilisent les mêmes QR Codes.

    - Surcharge lors des pics :

        ouverture des ventes,

        événements très populaires.

    - Dépendance aux paiements :

        panne Mobile Money / API bancaire.


⚠️ Failles fonctionnelles

    Publication sans validation admin :

        risque de faux événements ou de contenu inapproprié.

    Litiges organisateur ↔ client :

        annulations,

        remboursements,

        événements non conformes.

    Gestion des remboursements :

        juridiquement et techniquement complexe.


⚠️ Failles business

    Confiance initiale :

        les organisateurs peuvent hésiter au lancement.

    Commission mal calibrée :

        trop élevée → fuite des organisateurs,

        trop basse → faible rentabilité.

    Concurrence :

        plateformes internationales déjà établies.


4️⃣ Améliorations majeures possibles

    🚀 Améliorations fonctionnelles

        Validation automatique des organisateurs (KYC léger).

        Système d’avis et de notation des événements.

        Gestion des remboursements partiels ou automatiques.

        Billets nominatifs avec contrôle d’identité.

        Invitations privées / événements à accès restreint.
    
    🔐 Améliorations sécurité

        QR Codes dynamiques avec signature cryptographique.

        Rotation des clés de chiffrement.

        Limitation stricte du mode hors ligne (quota, durée).

        Journalisation complète des scans.