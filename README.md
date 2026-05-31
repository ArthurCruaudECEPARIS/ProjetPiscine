https://github.com/ArthurCruaudECEPARIS/ProjetPiscine/tree/main

# Mercato Nova — Marketplace GameCube

Projet Piscine — Sujet 1  
Marketplace de vente/achat de jeux vidéo avec trois modes de transaction : vente directe, enchère et négociation.

---

## Stack technique

- **Back-end** : PHP 8 (pur, sans framework)
- **Base de données** : MySQL via MySQLi (XAMPP)
- **Front-end** : HTML/CSS vanilla + JavaScript vanilla (aucun React, aucun framework JS)
- **Polices** : Rajdhani (titres) + Nunito (corps) via Google Fonts
- **Serveur local** : XAMPP (Apache + MySQL)

---

## Installation

1. Cloner le dépôt dans `C:\xampp\htdocs\ProjetPiscine\`
2. Démarrer XAMPP (Apache + MySQL)
3. Ouvrir `http://localhost/ProjetPiscine/`  
  → La base de données `Game_Corner_DB` et toutes les tables sont créées automatiquement au premier chargement
4. importer la base de donnée dans phpmyadmin


---

## Comptes de démo

| Utilisateur | Mot de passe | Rôle | Niveau |
|-------------|-------------|------|--------|
| adribarr | admin2026 | Vendeur | Admin |
| TonioV | admin2026 | Vendeur | Admin |
| buloshkin | admin2026 | Vendeur | Admin |
| ModKira | modo2026 | Acheteur | Modérateur |
| ModZen | modo2026 | Acheteur | Modérateur |
| GamerX | user2026 | Acheteur | Utilisateur |
| RetroGuru | user2026 | Acheteur | Utilisateur |
| DarkSoul99 | user2026 | Acheteur | Utilisateur |
| NintendoShop | user2026 | Vendeur | Utilisateur |
| RetroStation | user2026 | Vendeur | Utilisateur |
| TechGaming | user2026 | Vendeur | Utilisateur |

---

## Niveaux de privilège

| Valeur | Niveau | Permissions |
|--------|--------|-------------|
| 0 | Utilisateur | Acheter, vendre (si vendeur approuvé), modifier son profil |
| 1 | Modérateur | + Bannir des utilisateurs, modérer le contenu |
| 2 | Admin | + Ajouter des modérateurs, bannir des modérateurs |
| 3 | Super Admin | + Tout faire, ajouter/bannir des admins |

## Rôles

| Valeur | Rôle |
|--------|------|
| 0 | Acheteur (défaut à l'inscription) |
| 1 | Vendeur + Acheteur (après validation d'une demande) |

Un utilisateur s'inscrit toujours comme **acheteur**. Pour vendre, il soumet un formulaire de demande (nom de boutique, description, types de produits…) qui doit être approuvé par un admin ou modérateur.

---

## Fonctionnalités

### Catalogue & produits
- Catalogue avec filtres par catégorie et mode de vente
- Fiche produit avec images, description, vendeur
- Trois modes de vente : **Achat direct**, **Enchère**, **Négociation**
- Un vendeur ne peut pas acheter ses propres produits

### Panier & achats directs
- Panier persistant en session
- Checkout avec débit du porte-monnaie
- Confirmation d'achat + notification

### Enchères
- Enchère en temps réel avec compte à rebours
- Mise uniquement si le solde du porte-monnaie est suffisant
- Notification au surenchéri, au gagnant et au vendeur
- Clôture automatique à expiration (lazy evaluation au chargement de la page)
- Ne pas payer une enchère remportée = avertissement/sanction

### Négociations
- Catalogue des produits disponibles à la négociation
- Fil de discussion acheteur ↔ vendeur (offres / contre-offres)
- Maximum 10 offres par négociation
- Vendeur peut : accepter, refuser, contre-offrir
- Si acceptée : transaction immédiate (débit acheteur, crédit vendeur)
- Les produits en négociation ne passent pas par le panier

### Porte-monnaie
- Solde virtuel rechargeable
- L'argent est crédité à la vente (pas au retrait)
- Historique des transactions

### Profil
- Modification des informations personnelles
- Photo de profil
- En tant qu'admin : voir les ventes d'un utilisateur depuis son profil

### Panneau de modération
- Gestion des utilisateurs (bannissement, changement de rôle)
- Validation des demandes vendeur
- Gestion des emails bannis
- Accessible aux modérateurs et admins

### Notifications
- Système de notifications en temps réel (badge dans le header)
- Types : enchère, négociation, achat, système

---

## Structure des fichiers

```
ProjetPiscine/
├── auth/               # login.php, register.php, logout.php
├── actions/            # Scripts POST (add_to_cart, negotiation_create…)
├── assets/
│   ├── css/style.css
│   └── logo.png
├── config/
│   └── database.php    # Connexion + création automatique des tables
├── partials/
│   ├── header.php
│   └── footer.php
├── views/              # Vues incluses par home.php
│   ├── cart.php
│   ├── encheres_view.php
│   ├── negociations_view.php
│   ├── espace_vendeurs_view.php
│   └── notifications.php
├── uploads/            # Images uploadées ({user_id}/profil/, {seller_id}/{product_id}/)
├── home.php            # Router principal (paramètre ?menu=)
├── product_view.php
├── product_add.php / product_edit.php
├── negociation_detail.php
├── panneau_moderation.php
├── profil_view.php
├── porte_monnaie_view.php
├── devenir_vendeur.php
└── checkout.php
```

---

## Règles métier importantes

- Les fichiers d'un utilisateur banni sont **conservés** (preuves, historique)
- Les commissions sur les ventes sont à définir dans le contrat vendeur
- Le dossier `uploads/` ne doit jamais être supprimé automatiquement lors d'un ban
- Les images de produit sont stockées dans `uploads/{seller_id}/{product_id}/`
- Les images de profil sont stockées dans `uploads/{user_id}/profil/`
