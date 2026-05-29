<?php
session_start();
require "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = "";
$error   = "";

/* déjà vendeur */
if (($_SESSION['role'] ?? 0) >= 1) {
    header("Location: home.php?menu=Espace Vendeurs");
    exit();
}

/* demande déjà en attente ou approuvée */
$chk = $conn->prepare("SELECT id, status FROM seller_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
$chk->bind_param("i", $user_id);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    $shop_name   = trim($_POST['shop_name'] ?? '');
    $activity    = trim($_POST['activity_description'] ?? '');
    $prod_types  = trim($_POST['product_types'] ?? '');
    $experience  = trim($_POST['experience'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $motivation  = trim($_POST['motivation'] ?? '');

    if (!$shop_name || !$activity || !$prod_types) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        $ins = $conn->prepare("
            INSERT INTO seller_requests
                (user_id, shop_name, activity_description, product_types, experience, phone, motivation)
            VALUES (?,?,?,?,?,?,?)
        ");
        $ins->bind_param("issssss", $user_id, $shop_name, $activity, $prod_types, $experience, $phone, $motivation);
        $ins->execute();

        /* notifier les admins (privilege >= 2) */
        $admins = $conn->query("SELECT id FROM users WHERE privilege >= 2");
        while ($adm = $admins->fetch_assoc()) {
            create_notification($conn, $adm['id'], 'info',
                "📋 Nouvelle demande vendeur de " . htmlspecialchars($_SESSION['username']) . " — boutique : $shop_name",
                "panneau_moderation.php?tab=seller_requests"
            );
        }

        $success = "Votre demande a bien été envoyée ! Un administrateur la traitera dans les plus brefs délais.";

        /* refresh pour afficher l'état en attente */
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Devenir vendeur — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container" style="margin-top:30px;">
    <a href="home.php" class="back-link">← Retour</a>
    <div class="section-title">🏪 Devenir Vendeur</div>
    <p class="section-sub">Rejoignez notre communauté de vendeurs et commencez à vendre vos articles gaming.</p>

    <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($existing): ?>
        <?php if ($existing['status'] === 'pending'): ?>
        <div class="neon-card" style="text-align:center;padding:50px;">
            <div style="font-size:52px;margin-bottom:16px;">⏳</div>
            <h2 style="font-family:'Orbitron',sans-serif;color:var(--neon-yellow);">Demande en cours d'examen</h2>
            <p style="color:var(--text-soft);margin-top:12px;">Votre demande a été transmise à l'équipe d'administration.<br>Vous recevrez une notification dès qu'elle sera traitée.</p>
        </div>
        <?php elseif ($existing['status'] === 'refused'): ?>
        <div class="neon-card" style="text-align:center;padding:50px;">
            <div style="font-size:52px;margin-bottom:16px;">❌</div>
            <h2 style="font-family:'Orbitron',sans-serif;color:#ff6b8a;">Demande refusée</h2>
            <p style="color:var(--text-soft);margin-top:12px;">Votre précédente demande a été refusée. Vous pouvez en soumettre une nouvelle.</p>
        </div>
        <?php endif; ?>
    <?php else: ?>

    <!-- Avantages -->
    <div class="stats-grid" style="margin-bottom:30px;">
        <div class="stat-card">
            <div class="stat-value">🛒</div>
            <div class="stat-label">Vente directe</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">⚡</div>
            <div class="stat-label">Enchères</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">🤝</div>
            <div class="stat-label">Négociations</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">📊</div>
            <div class="stat-label">Tableau de bord</div>
        </div>
    </div>

    <!-- Formulaire -->
    <form method="POST">
        <div class="neon-card" style="margin-bottom:20px;">
            <h2>Informations sur votre boutique</h2>

            <div class="form-group">
                <label class="form-label">Nom de la boutique *</label>
                <input class="form-input" type="text" name="shop_name" placeholder="Ex: RetroGaming Store" maxlength="255" required>
            </div>

            <div class="form-group">
                <label class="form-label">Description de votre activité *</label>
                <textarea class="form-input form-textarea" name="activity_description" placeholder="Décrivez ce que vous vendez, votre spécialité gaming..." required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Types de produits vendus *</label>
                <input class="form-input" type="text" name="product_types" placeholder="Ex: Consoles rétro, jeux PS4/PS5, accessoires..." maxlength="255" required>
            </div>
        </div>

        <div class="neon-card" style="margin-bottom:20px;">
            <h2>Votre profil de vendeur</h2>

            <div class="form-group">
                <label class="form-label">Expérience en vente</label>
                <textarea class="form-input form-textarea" name="experience" placeholder="Avez-vous déjà vendu en ligne ? Sur quelles plateformes ? (optionnel)"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Téléphone / Contact</label>
                <input class="form-input" type="tel" name="phone" placeholder="06 00 00 00 00 (optionnel)" maxlength="50">
            </div>

            <div class="form-group">
                <label class="form-label">Motivation</label>
                <textarea class="form-input form-textarea" name="motivation" placeholder="Pourquoi souhaitez-vous rejoindre Mercato Nova en tant que vendeur ? (optionnel)"></textarea>
            </div>
        </div>

        <div class="neon-card" style="margin-bottom:20px;background:rgba(139,92,246,.06);border-color:rgba(139,92,246,.2);">
            <p style="color:var(--text-soft);font-size:13px;line-height:1.7;">
                🛡 Votre demande sera examinée par notre équipe d'administration. En soumettant ce formulaire, vous acceptez de respecter les conditions d'utilisation de Mercato Nova et de ne vendre que des produits légaux et conformes à nos règles.
            </p>
        </div>

        <button type="submit" class="neon-btn">🚀 Envoyer ma demande</button>
    </form>

    <?php endif; ?>
</div>

<?php include "partials/footer.php"; ?>
</body>
</html>
