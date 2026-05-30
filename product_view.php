<?php
session_start();
require "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

if (!isset($_GET["id"])) {
    header("Location: home.php");
    exit();
}

$id = (int)$_GET["id"];
$stmt = $conn->prepare("SELECT p.*, u.username as seller_name FROM products p LEFT JOIN users u ON p.seller_id=u.id WHERE p.id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: home.php");
    exit();
}

/* image */
$stmt2 = $conn->prepare("SELECT image_path FROM product_images WHERE product_id=? LIMIT 1");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$imgRow = $stmt2->get_result()->fetch_assoc();
$imgPath = $imgRow ? "uploads/{$product['seller_id']}/$id/{$imgRow['image_path']}" : "assets/default_image.png";

/* auction data if relevant */
$auction = null;
if ($product['sale_type'] === 'auction') {
    $sa = $conn->prepare("SELECT a.*, u.username as winner_name FROM auctions a LEFT JOIN users u ON a.current_winner_id=u.id WHERE a.product_id=? AND a.status='active' LIMIT 1");
    $sa->bind_param("i", $id);
    $sa->execute();
    $auction = $sa->get_result()->fetch_assoc();
}

/* negotiation check */
$myNego = null;
if ($product['sale_type'] === 'negotiation') {
    $sn = $conn->prepare("SELECT id,status FROM negotiations WHERE product_id=? AND buyer_id=? AND status='open' LIMIT 1");
    $sn->bind_param("ii", $id, $_SESSION['user_id']);
    $sn->execute();
    $myNego = $sn->get_result()->fetch_assoc();
}

$success = $_GET['success'] ?? null;
$error   = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($product['title']) ?> — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container-wide" style="margin-top:30px;">
    <a href="javascript:history.back()" class="back-link">← Retour</a>

    <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start;">

        <!-- Image -->
        <div class="neon-card" style="padding:10px;">
            <img src="<?= htmlspecialchars($imgPath) ?>" style="width:100%;border-radius:16px;max-height:450px;object-fit:cover;" alt="<?= htmlspecialchars($product['title']) ?>">
        </div>

        <!-- Info -->
        <div>
            <?php
            $badgeMap = ['direct'=>'badge-direct 🛒 Achat direct','auction'=>'badge-auction ⚡ Enchère','negotiation'=>'badge-negotiation 🤝 Négociation'];
            $b = explode(' ', $badgeMap[$product['sale_type']] ?? 'badge-gaming 🎮 Gaming', 2);
            ?>
            <span class="category-badge <?= $b[0] ?>"><?= $b[1] ?? '' ?></span>

            <h1 style="font-family:'Orbitron',sans-serif;font-size:28px;color:white;text-shadow:0 0 10px var(--neon-blue);margin:12px 0;"><?= htmlspecialchars($product['title']) ?></h1>

            <p style="color:var(--text-soft);margin-bottom:6px;">Vendu par : <span style="color:var(--neon-blue);"><?= htmlspecialchars($product['seller_name'] ?? 'Vendeur') ?></span></p>

            <?php if ($product['sale_type'] === 'direct'): ?>
            <!-- DIRECT BUY -->
            <div style="font-size:38px;font-weight:700;color:var(--neon-yellow);text-shadow:0 0 12px rgba(255,216,77,.6);margin:20px 0;">
                <?= number_format($product['price'],2,',',' ') ?> €
            </div>
            <p style="color:var(--text-soft);margin-bottom:20px;">Stock disponible : <strong style="color:white;"><?= (int)$product['stock'] ?></strong></p>

            <?php if ($product['stock'] > 0 && $product['seller_id'] != $_SESSION['user_id']): ?>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
                <input type="number" id="qtyInput" min="1" max="<?= (int)$product['stock'] ?>" value="1" class="form-input" style="width:80px;">
                <button onclick="addToCart(<?= $id ?>, document.getElementById('qtyInput').value)" class="neon-btn" style="margin:0;flex:1;">🛒 Ajouter au panier</button>
            </div>
            <form action="checkout.php" method="POST">
                <input type="hidden" name="product_id" value="<?= $id ?>">
                <button type="submit" class="neon-btn-pink" style="margin:0;">⚡ Acheter maintenant</button>
            </form>
            <?php elseif($product['seller_id'] == $_SESSION['user_id']): ?>
            <p class="alert alert-info">C'est votre produit.</p>
            <?php else: ?>
            <p class="alert alert-error">Produit indisponible.</p>
            <?php endif; ?>

            <?php elseif ($product['sale_type'] === 'auction' && $auction): ?>
            <!-- AUCTION -->
            <div style="margin:20px 0;">
                <p style="color:var(--text-soft);">Prix de départ : <?= number_format($auction['starting_price'],2,',',' ') ?> €</p>
                <div style="font-size:34px;font-weight:700;color:var(--neon-green);text-shadow:0 0 10px rgba(0,255,136,.5);margin:10px 0;">
                    <?= number_format($auction['current_price'],2,',',' ') ?> €
                    <span style="font-size:14px;color:var(--text-soft);">offre actuelle</span>
                </div>
                <p class="auction-countdown" id="countdown">Fin : <?= date('d/m/Y H:i', strtotime($auction['end_date'])) ?></p>
                <?php if ($auction['current_winner_id']): ?>
                <p style="color:var(--neon-blue);font-size:13px;">Meilleur enchérisseur : <?= htmlspecialchars($auction['winner_name']) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($product['seller_id'] != $_SESSION['user_id']): ?>
            <a href="home.php?menu=Enchères&auction_id=<?= $auction['id'] ?>" class="neon-btn" style="display:block;text-align:center;text-decoration:none;padding:13px;">⚡ Participer à l'enchère</a>
            <?php endif; ?>

            <?php elseif ($product['sale_type'] === 'negotiation'): ?>
            <!-- NEGOTIATION -->
            <div style="font-size:32px;font-weight:700;color:var(--neon-pink);margin:20px 0;">
                <?= number_format($product['price'],2,',',' ') ?> €
                <span style="font-size:14px;color:var(--text-soft);">prix demandé</span>
            </div>
            <?php if ($product['seller_id'] != $_SESSION['user_id']): ?>
                <?php if ($myNego): ?>
                <a href="negociation_detail.php?id=<?= $myNego['id'] ?>" class="neon-btn-pink" style="display:block;text-align:center;text-decoration:none;padding:13px;">Voir ma négociation en cours →</a>
                <?php else: ?>
                <form action="actions/negotiation_create.php" method="POST">
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <div class="form-group">
                        <label class="form-label">Votre offre (€)</label>
                        <input class="form-input" type="number" name="amount" step="0.01" min="1" placeholder="Entrez votre offre" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message (optionnel)</label>
                        <textarea class="form-input form-textarea" name="message" placeholder="Présentez votre offre..."></textarea>
                    </div>
                    <button type="submit" class="neon-btn-pink">🤝 Envoyer une offre</button>
                </form>
                <?php endif; ?>
            <?php else: ?>
            <p class="alert alert-info">C'est votre produit.</p>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Description -->
            <div class="neon-card" style="margin-top:24px;">
                <h2 style="font-size:16px;color:var(--neon-blue);margin-bottom:12px;">Description</h2>
                <p style="color:var(--text-soft);line-height:1.7;"><?= nl2br(htmlspecialchars($product['description'] ?? 'Aucune description.')) ?></p>
            </div>
        </div>
    </div>
</div>

<?php include "partials/footer.php"; ?>

<div id="toast" style="position:fixed;bottom:30px;right:30px;background:linear-gradient(135deg,var(--neon-blue),var(--neon-purple));color:white;padding:14px 22px;border-radius:14px;display:none;font-weight:600;z-index:9999;box-shadow:0 4px 24px rgba(0,207,255,.3);"></div>

<script>
function addToCart(productId, qty) {
    const form = new FormData();
    form.append("product_id", productId);
    form.append("qty", qty || 1);
    fetch("actions/add_to_cart.php", { method: "POST", body: form })
        .then(r => r.text())
        .then(msg => showToast(msg.includes("ajouté") ? "✅ " + msg : "⚠️ " + msg))
        .catch(() => showToast("⚠️ Erreur réseau"));
}

function showToast(msg) {
    const t = document.getElementById("toast");
    t.textContent = msg;
    t.style.display = "block";
    setTimeout(() => t.style.display = "none", 2800);
}
</script>
</body>
</html>
