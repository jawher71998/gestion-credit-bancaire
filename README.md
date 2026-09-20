# 💳 Gestion des Demandes de Crédit — Banque Zitouna

Plateforme web de gestion des demandes de crédit bancaire, développée dans
le cadre d'un Projet de Fin d'Études (BTS Informatique de Gestion).

Le projet s'inspire du fonctionnement réel d'une agence bancaire
tunisienne : simulation de crédit, dépôt de dossier en ligne, workflow de
validation à deux niveaux (agent → chef d'agence), et génération
automatique de l'échéancier de remboursement.

## ✨ Fonctionnalités

- **Simulateur de crédit** en temps réel (JavaScript) sur la page d'accueil
- **4 espaces utilisateurs** : client, agent, chef d'agence, administrateur
- **Dépôt de demande** avec upload de pièces justificatives (CIN, fiche de paie...)
- **Workflow de validation à 2 niveaux** : validation directe par l'agent
  si le montant est ≤ 20 000 DT, validation finale du chef d'agence au-delà
- **Calcul automatique** de la mensualité et du taux d'endettement (alerte si > 40 %)
- **Génération automatique de l'échéancier** de remboursement à la validation
- **Notifications** en temps réel pour le client
- **Modification / annulation** d'une demande tant qu'elle n'est pas traitée
- **Tableau de bord admin** avec statistiques et graphiques (Chart.js)
- 3 produits de financement : crédit consommation, crédit immobilier
  Mourabaha et crédit auto Ijara (finance islamique)

## 🛠️ Stack technique

| Couche | Technologie |
|---|---|
| Frontend | HTML5, CSS3 (design maison), JavaScript vanilla |
| Backend | PHP natif (sans framework) |
| Base de données | MySQL (PDO, requêtes préparées) |
| Graphiques | Chart.js |

## 🔒 Sécurité

- Mots de passe hashés avec `password_hash()` (bcrypt)
- Protection CSRF sur tous les formulaires sensibles
- Requêtes préparées PDO (protection contre l'injection SQL)
- Dossier d'upload protégé contre l'exécution de scripts (`.htaccess`)

## 📂 Structure du projet

```
site-credit/
├── includes/       → connexion BDD, authentification, logique métier
├── admin/          → espace administrateur
├── agent/          → espace agent / chef d'agence
├── assets/         → CSS, JS, images
├── uploads/        → documents clients (non versionné)
└── *.php           → pages publiques et espace client
```

## 🚀 Installation locale

1. Installer un environnement local (WAMP, XAMPP ou équivalent)
2. Placer le dossier `site-credit` dans `www/` ou `htdocs/`
3. Importer `schema.sql` dans MySQL (via phpMyAdmin) — cela crée la base
   `gestion_credit` avec ses 7 tables et quelques données de départ
4. Adapter les identifiants de connexion à la base si besoin dans
   `includes/db.php`
5. Créer un premier compte administrateur manuellement (table `utilisateurs`,
   rôle `admin`), avec un mot de passe généré par `password_hash()` en PHP
6. Ouvrir `http://localhost/site-credit/`

## 📐 Règles métier principales

- **Seuil de validation** : 20 000 DT sépare une validation simple (agent
  seul) d'une double validation (agent puis chef d'agence)
- **Taux d'endettement** : `(mensualité ÷ revenu mensuel) × 100`, alerte
  visuelle au-delà de 40 %
- **Mensualité** : formule d'amortissement classique à taux constant

## 📸 Aperçu

*(ajoutez ici 2-3 captures d'écran : page d'accueil avec simulateur,
tableau de bord admin, dossier en cours de traitement)*

## 👤 Auteur

Projet réalisé par Jawher Sbabti — www.linkedin.com/in/jawher-sbabti


---

*Projet académique à but pédagogique — n'est pas un produit bancaire réel.*
