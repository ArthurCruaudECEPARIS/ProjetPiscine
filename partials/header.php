<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

/* notifications count */
$notif_count = 0;
if (isset($conn)) {
    $ns = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id=? AND is_read=0");
    $ns->bind_param("i", $_SESSION['user_id']);
    $ns->execute();
    $notif_count = $ns->get_result()->fetch_assoc()['c'] ?? 0;
}

$currentMenu = $_GET['menu'] ?? 'Transactions';
?>

<header>
<div class="header-top">

    <a href="home.php" class="logo">
        <img src="assets/logo.png" alt="Mercato Nova" style="height:48px;width:auto;object-fit:contain;">
    </a>

    <div class="header-actions">

        <a href="home.php?menu=Panier" class="action-btn" style="position:relative;">
            🛒 Panier
            <span class="cart-badge"><?= array_sum($_SESSION['cart'] ?? []) ?></span>
        </a>

        <a href="home.php?menu=Notifications" class="action-btn" style="position:relative;">
            🔔 Notifs
            <?php if ($notif_count > 0): ?>
            <span class="notif-badge"><?= $notif_count ?></span>
            <?php endif; ?>
        </a>

        <div class="account-dropdown">
            <button class="action-btn" id="accountBtn">
                👤 <?= htmlspecialchars($_SESSION['username']) ?> ▾
            </button>
            <div class="dropdown-menu" id="accountMenu">
                <a href="profil_view.php">👤 Mon profil</a>
                <a href="porte_monnaie_view.php">💰 Porte-monnaie</a>
                <a href="home.php?menu=Notifications">🔔 Notifications<?= $notif_count > 0 ? " ($notif_count)" : '' ?></a>
                <?php if (($_SESSION['privilege'] ?? 0) >= 1): ?>
                <div class="dropdown-divider"></div>
                <a href="panneau_moderation.php">🛡 Administration</a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="auth/logout.php" class="logout">🚪 Se déconnecter</a>
            </div>
        </div>

    </div>
</div>

<nav>
<div class="nav-inner">
<div class="nav-links">
    <a href="home.php?menu=Transactions" class="<?= $currentMenu === 'Transactions' ? 'active' : '' ?>">🛒 Transactions</a>
    <a href="home.php?menu=Enchères" class="<?= $currentMenu === 'Enchères' ? 'active' : '' ?>">⚡ Enchères</a>
    <a href="home.php?menu=Négociations" class="<?= $currentMenu === 'Négociations' ? 'active' : '' ?>">🤝 Négociations</a>
    <?php if (($_SESSION['role'] ?? 0) >= 1): ?>
    <a href="home.php?menu=Espace Vendeurs" class="<?= $currentMenu === 'Espace Vendeurs' ? 'active' : '' ?>">📦 Espace Vendeurs</a>
    <?php endif; ?>
</div>
</div>
</nav>
</header>

<script>
const btn = document.getElementById("accountBtn");
const menu = document.getElementById("accountMenu");
if(btn){
    btn.addEventListener("click", function(e){
        e.stopPropagation();
        menu.style.display = menu.style.display === "flex" ? "none" : "flex";
    });
    document.addEventListener("click", function(){ menu.style.display = "none"; });
}
</script>
