<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }
if (($_SESSION['role'] ?? 0) < 1) {
    header("Location: home.php?error=Accès réservé aux vendeurs");
    exit();
}

$seller_id = $_SESSION['user_id'];

/* products */
$stmt = $conn->prepare("
    SELECT p.*, pi.image_path as img
    FROM products p
    LEFT JOIN (
        SELECT product_id, MIN(id) as min_id
        FROM product_images
        GROUP BY product_id
    ) pi_ids ON pi_ids.product_id = p.id
    LEFT JOIN product_images pi ON pi.id = pi_ids.min_id
    WHERE p.seller_id = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* stats */
$stmt = $conn->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM transactions WHERE seller_id=?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM negotiations WHERE seller_id=? AND status='open'");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$openNegos = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM auctions WHERE seller_id=? AND status='active'");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$activeAuctions = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Espace Vendeur — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/header.php"; ?>

<div class="page-container-wide" style="margin-top:30px;">
    <div class="section-title">📦 Espace Vendeur</div>
    <p class="section-sub">Gérez vos produits et suivez vos ventes.</p>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($products) ?></div>
            <div class="stat-label">Produits publiés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--neon-green);"><?= number_format($stats['total'],2,',',' ') ?> €</div>
            <div class="stat-label">Revenus totaux</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--neon-yellow);"><?= $stats['cnt'] ?></div>
            <div class="stat-label">Ventes réalisées</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--neon-pink);"><?= $openNegos ?></div>
            <div class="stat-label">Négociations en cours</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--neon-blue);"><?= $activeAuctions ?></div>
            <div class="stat-label">Enchères actives</div>
        </div>
    </div>

    <!-- Add product button -->
    <div style="margin-bottom:20px;display:flex;gap:12px;">
        <a href="product_add.php" class="neon-btn" style="display:inline-block;width:auto;padding:12px 24px;text-decoration:none;">+ Ajouter un produit</a>
        <a href="home.php?menu=Négociations" class="btn-ghost">🤝 Voir mes négociations (<?= $openNegos ?>)</a>
        <a href="home.php?menu=Enchères" class="btn-ghost">⚡ Voir mes enchères (<?= $activeAuctions ?>)</a>
    </div>

    <!-- Products grid -->
    <?php if (empty($products)): ?>
    <div class="neon-card" style="text-align:center;padding:50px;">
        <div style="font-size:48px;margin-bottom:16px;">📦</div>
        <p style="color:var(--text-soft);">Aucun produit publié. Commencez à vendre !</p>
    </div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $p):
            $pid = (int)$p['id'];
            $imgPath = $p['img'] ? "uploads/$seller_id/$pid/{$p['img']}" : "assets/default_image.png";
            $saleLabels = ['direct'=>'🛒 Direct','auction'=>'⚡ Enchère','negotiation'=>'🤝 Négo'];
            $statusColors = ['available'=>'var(--neon-green)','sold'=>'var(--text-soft)','hidden'=>'var(--neon-pink)'];
        ?>
        <div class="product-card" style="position:relative;">
            <div style="position:absolute;top:10px;left:10px;z-index:2;">
                <span class="category-badge badge-gaming" style="font-size:10px;"><?= $saleLabels[$p['sale_type']] ?? '🛒' ?></span>
            </div>
            <div class="card-img-wrap">
                <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
            </div>
            <div class="card-body">
                <div class="card-name"><?= htmlspecialchars($p['title']) ?></div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?= $statusColors[$p['status']] ?? 'var(--text-soft)' ?>;display:inline-block;"></span>
                    <span style="color:var(--text-soft);font-size:12px;"><?= ucfirst($p['status']) ?></span>
                </div>
                <div class="card-footer">
                    <span class="card-price"><?= number_format($p['price'],2,',',' ') ?> €</span>
                    <span style="color:var(--text-soft);font-size:12px;">Stock: <?= (int)$p['stock'] ?></span>
                </div>
                <div style="display:flex;gap:8px;margin-top:12px;">
                    <a href="product_edit.php?id=<?= $pid ?>" class="btn-ghost" style="flex:1;text-align:center;padding:8px;">✏️ Modifier</a>
                    <a href="actions/product_delete.php?id=<?= $pid ?>" class="btn-danger" style="flex:1;text-align:center;padding:8px;text-decoration:none;"
                       onclick="return confirm('Supprimer ce produit ?')">🗑 Suppr.</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
</body>
</html>

