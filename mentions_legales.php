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
<title>Mentions légales — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>
<div class="page-container" style="margin-top:30px;">
    <a href="home.php" class="back-link">← Retour</a>
    <div class="section-title">📄 Mentions légales</div>
    <div class="neon-card" style="margin-top:20px;line-height:1.8;color:var(--text-soft);">
        <h2 style="margin-bottom:16px;">Éditeur</h2>
        <p>Mercato Nova est un projet pédagogique réalisé dans le cadre d'un cours de développement web.</p>
        <h2 style="margin:24px 0 16px;">Hébergement</h2>
        <p>Ce site est hébergé localement via XAMPP à des fins de démonstration.</p>
        <h2 style="margin:24px 0 16px;">Propriété intellectuelle</h2>
        <p>Tous les contenus présents sur ce site sont fictifs et créés à des fins éducatives uniquement.</p>
        <h2 style="margin:24px 0 16px;">Données personnelles</h2>
        <p>Les données saisies sont stockées localement et ne sont pas transmises à des tiers.</p>
    </div>
</div>
<?php include "partials/footer.php"; ?>
</body>
</html>

