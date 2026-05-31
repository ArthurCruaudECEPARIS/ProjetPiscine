<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

/* handle actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove'])) {
        $pid = (int)$_POST['remove'];
        unset($_SESSION['cart'][$pid]);
    }
    if (isset($_POST['update_qty'])) {
        $pid = (int)$_POST['update_pid'];
        $qty = (int)$_POST['update_qty'];
        if ($qty > 0) $_SESSION['cart'][$pid] = $qty;
        else unset($_SESSION['cart'][$pid]);
    }
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
    }
}

$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0;

foreach ($cart as $product_id => $qty) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    if (!$p) continue;
    $subtotal = $p['price'] * $qty;
    $total += $subtotal;
    $items[] = ['product' => $p, 'qty' => $qty, 'subtotal' => $subtotal];
}

/* get user balance */
$stmt = $conn->prepare("SELECT solde FROM users WHERE id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$userSolde = $stmt->get_result()->fetch_assoc()['solde'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon panier — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/header.php"; ?>

<div class="page-container-wide">
    <div class="section-title" style="margin-top:30px;">🛒 Mon Panier</div>
    <p class="section-sub"><?= count($items) ?> article<?= count($items) > 1 ? 's' : '' ?></p>

    <?php if (empty($items)): ?>
    <div class="neon-card" style="text-align:center;padding:60px;">
        <div style="font-size:48px;margin-bottom:16px;">🛒</div>
        <h2 style="font-family:'Rajdhani',sans-serif;color:var(--neon-blue);">Panier vide</h2>
        <p style="color:var(--text-soft);margin:12px 0 24px;">Aucun produit dans votre panier.</p>
        <a href="home.php" class="neon-btn" style="display:inline-block;width:auto;padding:13px 30px;text-decoration:none;">Continuer mes achats</a>
    </div>

    <?php else: ?>
    <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

        <!-- Items -->
        <div class="neon-card">
            <?php foreach ($items as $item):
                $p = $item['product'];
                $pid = $p['id'];
                $stmt2 = $conn->prepare("SELECT image_path FROM product_images WHERE product_id=? LIMIT 1");
                $stmt2->bind_param("i", $pid);
                $stmt2->execute();
                $imgRow = $stmt2->get_result()->fetch_assoc();
                $imgPath = $imgRow ? "uploads/{$p['seller_id']}/$pid/{$imgRow['image_path']}" : "assets/default_image.png";
            ?>
            <div style="display:flex;gap:16px;align-items:center;padding:16px 0;border-bottom:1px solid var(--border);">
                <img src="<?= htmlspecialchars($imgPath) ?>" style="width:80px;height:80px;border-radius:12px;object-fit:cover;">
                <div style="flex:1;">
                    <div style="font-weight:700;color:white;margin-bottom:4px;"><?= htmlspecialchars($p['title']) ?></div>
                    <div style="color:var(--neon-yellow);font-weight:700;"><?= number_format($p['price'],2,',',' ') ?> € / unité</div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <form method="POST" style="display:flex;align-items:center;gap:6px;">
                        <input type="hidden" name="update_pid" value="<?= $pid ?>">
                        <input type="number" name="update_qty" class="qty-input" value="<?= $item['qty'] ?>" min="1" max="<?= (int)$p['stock'] ?>">
                        <button type="submit" class="btn-ghost" style="padding:6px 10px;font-size:12px;">↻</button>
                    </form>
                    <div style="color:var(--neon-green);font-weight:700;min-width:80px;text-align:right;"><?= number_format($item['subtotal'],2,',',' ') ?> €</div>
                    <form method="POST">
                        <input type="hidden" name="remove" value="<?= $pid ?>">
                        <button type="submit" class="btn-danger" style="padding:6px 12px;">✕</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <form method="POST" style="margin-top:16px;">
                <button name="clear_cart" value="1" class="btn-danger" style="font-size:13px;">🗑 Vider le panier</button>
            </form>
        </div>

        <!-- Summary -->
        <div class="neon-card">
            <h2 style="font-family:'Rajdhani',sans-serif;font-size:18px;color:var(--neon-blue);margin-bottom:20px;">Récapitulatif</h2>
            <div style="display:flex;justify-content:space-between;margin-bottom:12px;color:var(--text-soft);">
                <span>Sous-total</span><span style="color:white;"><?= number_format($total,2,',',' ') ?> €</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:20px;color:var(--text-soft);">
                <span>Livraison</span><span style="color:var(--neon-green);">Gratuite</span>
            </div>
            <div style="border-top:1px solid var(--border);padding-top:16px;display:flex;justify-content:space-between;margin-bottom:20px;">
                <span style="font-size:18px;font-weight:700;color:white;">Total</span>
                <span style="font-size:22px;font-weight:700;color:var(--neon-yellow);"><?= number_format($total,2,',',' ') ?> €</span>
            </div>
            <div style="background:rgba(0,255,136,.06);border:1px solid rgba(0,255,136,.2);border-radius:12px;padding:12px;margin-bottom:16px;">
                <p style="color:var(--text-soft);font-size:13px;">Votre solde</p>
                <p style="color:var(--neon-green);font-weight:700;font-size:18px;"><?= number_format($userSolde,2,',',' ') ?> €</p>
            </div>
            <?php if ($userSolde >= $total): ?>
            <a href="checkout.php" class="neon-btn-pink" style="display:block;text-align:center;text-decoration:none;padding:13px;">⚡ Commander</a>
            <?php else: ?>
            <p class="alert alert-warning" style="margin-bottom:12px;font-size:13px;">Solde insuffisant. Il vous manque <?= number_format($total - $userSolde,2,',',' ') ?> €</p>
            <a href="porte_monnaie_view.php" class="neon-btn" style="display:block;text-align:center;text-decoration:none;padding:13px;">💰 Recharger mon solde</a>
            <?php endif; ?>
            <a href="home.php" class="btn-ghost" style="display:block;text-align:center;margin-top:10px;">Continuer les achats</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
</body>
</html>

