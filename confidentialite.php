<?php
session_start();
require "config/database.php";
if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confidentialité — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container" style="margin-top:30px;">
    <a href="home.php" class="back-link">← Retour</a>
    <div class="section-title">🔒 Politique de confidentialité</div>
    <p class="section-sub">Dernière mise à jour : <?= date('d/m/Y') ?></p>

    <div style="display:flex;flex-direction:column;gap:16px;">

        <div class="neon-card">
            <h2 style="color:var(--neon-blue);margin-bottom:12px;">1. Données collectées</h2>
            <p style="color:var(--text-soft);line-height:1.8;">
                Lors de votre inscription et utilisation de Mercato Nova, nous collectons les informations suivantes :
            </p>
            <ul style="color:var(--text-soft);line-height:2;margin-top:10px;padding-left:20px;">
                <li><strong style="color:white;">Données d'identification</strong> — nom d'utilisateur, adresse e-mail, mot de passe (chiffré)</li>
                <li><strong style="color:white;">Données de profil</strong> — photo de profil, biographie</li>
                <li><strong style="color:white;">Données transactionnelles</strong> — historique des achats, ventes, enchères et négociations</li>
                <li><strong style="color:white;">Données financières simulées</strong> — solde du porte-monnaie virtuel (aucune donnée bancaire réelle)</li>
            </ul>
        </div>

        <div class="neon-card">
            <h2 style="color:var(--neon-blue);margin-bottom:12px;">2. Utilisation des données</h2>
            <p style="color:var(--text-soft);line-height:1.8;">
                Vos données sont utilisées exclusivement pour :
            </p>
            <ul style="color:var(--text-soft);line-height:2;margin-top:10px;padding-left:20px;">
                <li>Assurer le fonctionnement de la plateforme et de vos transactions</li>
                <li>Vous envoyer des notifications liées à votre activité (enchères, négociations, achats)</li>
                <li>Permettre la modération du contenu par les administrateurs</li>
                <li>Améliorer l'expérience utilisateur de la plateforme</li>
            </ul>
            <p style="color:var(--text-soft);line-height:1.8;margin-top:12px;">
                Vos données ne sont <strong style="color:var(--neon-green);">jamais vendues ni transmises à des tiers</strong>.
            </p>
        </div>

        <div class="neon-card">
            <h2 style="color:var(--neon-blue);margin-bottom:12px;">3. Stockage et sécurité</h2>
            <p style="color:var(--text-soft);line-height:1.8;">
                Toutes les données sont stockées localement sur un serveur XAMPP à des fins pédagogiques. Les mots de passe sont chiffrés via <code style="color:var(--neon-yellow);background:rgba(255,216,77,.1);padding:2px 6px;border-radius:4px;">password_hash()</code> (bcrypt). Aucune donnée bancaire réelle n'est collectée — les paiements sont entièrement simulés.
            </p>
        </div>

        <div class="neon-card">
            <h2 style="color:var(--neon-blue);margin-bottom:12px;">4. Cookies et sessions</h2>
            <p style="color:var(--text-soft);line-height:1.8;">
                Mercato Nova utilise uniquement des <strong style="color:white;">cookies de session</strong> nécessaires au fonctionnement de l'authentification. Aucun cookie de tracking ou publicitaire n'est utilisé. Les sessions sont régénérées à chaque connexion pour votre sécurité.
            </p>
        </div>

        <div class="neon-card">
            <h2 style="color:var(--neon-blue);margin-bottom:12px;">5. Vos droits</h2>
            <p style="color:var(--text-soft);line-height:1.8;">
                Conformément au RGPD, vous disposez des droits suivants :
            </p>
            <ul style="color:var(--text-soft);line-height:2;margin-top:10px;padding-left:20px;">
                <li><strong style="color:white;">Droit d'accès</strong> — consulter vos données via votre profil</li>
                <li><strong style="color:white;">Droit de rectification</strong> — modifier vos informations depuis la page profil</li>
                <li><strong style="color:white;">Droit à l'effacement</strong> — contacter un administrateur pour supprimer votre compte</li>
                <li><strong style="color:white;">Droit à la portabilité</strong> — demander une copie de vos données via le support</li>
            </ul>
            <div style="margin-top:16px;">
                <a href="support.php" class="neon-btn" style="display:inline-block;width:auto;padding:11px 24px;text-decoration:none;font-size:14px;">Contacter le support →</a>
            </div>
        </div>

        <div class="neon-card" style="background:rgba(139,92,246,.05);border-color:rgba(139,92,246,.2);">
            <p style="color:var(--text-soft);font-size:13px;line-height:1.7;text-align:center;">
                🎓 Mercato Nova est un projet pédagogique. Cette politique de confidentialité est rédigée à titre éducatif et ne constitue pas un document légal opposable.
            </p>
        </div>

    </div>
</div>

<?php include "partials/footer.php"; ?>
</body>
</html>
