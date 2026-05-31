<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }

/* conclude expired auctions */
$exp = $conn->prepare("SELECT a.*, p.seller_id, u.username as winner_name FROM auctions a JOIN products p ON a.product_id=p.id LEFT JOIN users u ON a.current_winner_id=u.id WHERE a.status='active' AND a.end_date < NOW()");
$exp->execute();
$expRes = $exp->get_result();
$expired = $expRes->fetch_all(MYSQLI_ASSOC);
$expRes->free();
$exp->close();

foreach ($expired as $ea) {
    $ua = $conn->prepare("UPDATE auctions SET status='ended' WHERE id=?");
    $ua->bind_param("i", $ea['id']);
    $ua->execute();
    $ua->close();

    $up = $conn->prepare("UPDATE products SET status='sold' WHERE id=?");
    $up->bind_param("i", $ea['product_id']);
    $up->execute();
    $up->close();

    if ($ea['current_winner_id']) {
        $buyer_id  = $ea['current_winner_id'];
        $seller_id = $ea['seller_id'];
        $price     = $ea['current_price'];

        $conn->begin_transaction();
        try {
            $b = $conn->prepare("SELECT solde FROM users WHERE id=?");
            $b->bind_param("i", $buyer_id);
            $b->execute();
            $bRes = $b->get_result();
            $bData = $bRes->fetch_assoc();
            $bRes->free();
            $b->close();

            if ($bData['solde'] >= $price) {
                $db = $conn->prepare("UPDATE users SET solde=solde-? WHERE id=?");
                $db->bind_param("di", $price, $buyer_id);
                $db->execute();
                $db->close();

                $cr = $conn->prepare("UPDATE users SET solde=solde+? WHERE id=?");
                $cr->bind_param("di", $price, $seller_id);
                $cr->execute();
                $cr->close();

                $tr = $conn->prepare("INSERT INTO transactions (buyer_id, seller_id, product_id, amount, type) VALUES (?,?,?,?,'auction')");
                $tr->bind_param("iiid", $buyer_id, $seller_id, $ea['product_id'], $price);
                $tr->execute();
                $tr->close();

                create_notification($conn, $buyer_id, 'auction', "🏆 Vous avez remporté l'enchère ! Montant débité : " . number_format($price,2,',',' ') . " €", "porte_monnaie_view.php");
                create_notification($conn, $seller_id, 'auction', "💰 Enchère conclue ! " . number_format($price,2,',',' ') . " € reçus.", "porte_monnaie_view.php");
            } else {
                create_notification($conn, $buyer_id, 'auction', "❌ Enchère perdue : solde insuffisant pour payer " . number_format($price,2,',',' ') . " €", "porte_monnaie_view.php");
            }
            $conn->commit();
        } catch(Exception $e) { $conn->rollback(); }
    } else {
        create_notification($conn, $ea['seller_id'], 'auction', "⏰ Enchère terminée sans enchérisseur.", "home.php?menu=Enchères");
    }
}

/* ── VIEW SINGLE AUCTION ── */
$auction_id = (int)($_GET['auction_id'] ?? 0);
$singleAuction = null;
$bidError = "";
$bidSuccess = "";

if ($auction_id) {
    $sa = $conn->prepare("SELECT a.*, p.title, p.description, p.seller_id, p.sale_type, u.username as seller_name, w.username as winner_name FROM auctions a JOIN products p ON a.product_id=p.id JOIN users u ON a.seller_id=u.id LEFT JOIN users w ON a.current_winner_id=w.id WHERE a.id=?");
    $sa->bind_param("i", $auction_id);
    $sa->execute();
    $saRes = $sa->get_result();
    $singleAuction = $saRes->fetch_assoc();
    $saRes->free();
    $sa->close();

    if ($singleAuction) {
        $pid_img = (int)$singleAuction['product_id'];
        $imgRes = $conn->query("SELECT image_path FROM product_images WHERE product_id=$pid_img LIMIT 1");
        $imgRow = $imgRes ? $imgRes->fetch_assoc() : null;
        if ($imgRes) $imgRes->free();
        $singleAuction['img'] = $imgRow ? "uploads/{$singleAuction['seller_id']}/{$singleAuction['product_id']}/{$imgRow['image_path']}" : "assets/default_image.png";

        $bh = $conn->prepare("SELECT ab.*, u.username FROM auction_bids ab JOIN users u ON ab.bidder_id=u.id WHERE ab.auction_id=? ORDER BY ab.amount DESC LIMIT 10");
        $bh->bind_param("i", $auction_id);
        $bh->execute();
        $bhRes = $bh->get_result();
        $bidHistory = $bhRes->fetch_all(MYSQLI_ASSOC);
        $bhRes->free();
        $bh->close();
    }

    /* PLACE BID */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bid_amount']) && $singleAuction) {
        $bidAmount = floatval($_POST['bid_amount']);
        $buyer_id  = $_SESSION['user_id'];

        if ($singleAuction['seller_id'] == $buyer_id) {
            $bidError = "Vous ne pouvez pas enchérir sur votre propre produit.";
        } elseif ($singleAuction['status'] !== 'active') {
            $bidError = "Cette enchère est terminée.";
        } elseif ($bidAmount <= $singleAuction['current_price']) {
            $bidError = "Votre offre doit être supérieure à " . number_format($singleAuction['current_price'],2,',',' ') . " €.";
        } else {
            $b = $conn->prepare("SELECT solde FROM users WHERE id=?");
            $b->bind_param("i", $buyer_id);
            $b->execute();
            $bRes2 = $b->get_result();
            $userSolde = $bRes2->fetch_assoc()['solde'] ?? 0;
            $bRes2->free();
            $b->close();

            if ($userSolde < $bidAmount) {
                $bidError = "Solde insuffisant. Votre solde : " . number_format($userSolde,2,',',' ') . " €.";
            } else {
                $prevWinner = $singleAuction['current_winner_id'];

                $ib = $conn->prepare("INSERT INTO auction_bids (auction_id, bidder_id, amount) VALUES (?,?,?)");
                $ib->bind_param("iid", $auction_id, $buyer_id, $bidAmount);
                $ib->execute();
                $ib->close();

                $ua = $conn->prepare("UPDATE auctions SET current_price=?, current_winner_id=? WHERE id=?");
                $ua->bind_param("dii", $bidAmount, $buyer_id, $auction_id);
                $ua->execute();
                $ua->close();

                if ($prevWinner && $prevWinner != $buyer_id) {
                    create_notification($conn, $prevWinner, 'auction', "⚡ Vous avez été surenchéri ! Nouvelle offre : " . number_format($bidAmount,2,',',' ') . " €", "home.php?menu=Enchères&auction_id=$auction_id");
                }
                create_notification($conn, $singleAuction['seller_id'], 'auction', "💰 Nouvelle enchère sur votre produit : " . number_format($bidAmount,2,',',' ') . " €", "home.php?menu=Enchères&auction_id=$auction_id");

                $bidSuccess = "Enchère placée avec succès : " . number_format($bidAmount,2,',',' ') . " €";

                /* refresh auction data in memory — no re-query needed, update fields directly */
                $singleAuction['current_price'] = $bidAmount;
                $singleAuction['current_winner_id'] = $buyer_id;
                $singleAuction['winner_name'] = $_SESSION['username'];
            }
        }
    }
}

/* ── LIST ALL AUCTIONS ── */
$auctions = [];
if (!$singleAuction) {
    $la = $conn->query("SELECT a.*, p.title, p.description, p.seller_id, u.username as seller_name, w.username as winner_name FROM auctions a JOIN products p ON a.product_id=p.id JOIN users u ON a.seller_id=u.id LEFT JOIN users w ON a.current_winner_id=w.id WHERE a.status='active' ORDER BY a.end_date ASC");
    $auctions = $la->fetch_all(MYSQLI_ASSOC);
    $la->free();
    foreach ($auctions as &$au) {
        $pid_a = (int)$au['product_id'];
        $imgR = $conn->query("SELECT image_path FROM product_images WHERE product_id=$pid_a LIMIT 1");
        $imgRow = $imgR ? $imgR->fetch_assoc() : null;
        if ($imgR) $imgR->free();
        $au['img'] = $imgRow ? "uploads/{$au['seller_id']}/{$au['product_id']}/{$imgRow['image_path']}" : "assets/default_image.png";
    }
    unset($au);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enchères — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/header.php"; ?>

<?php if ($singleAuction): ?>
<!-- ── SINGLE AUCTION VIEW ── -->
<div class="page-container-wide" style="margin-top:30px;">
    <a href="home.php?menu=Enchères" class="back-link">← Toutes les enchères</a>

    <?php if($bidError): ?><div class="alert alert-error"><?= htmlspecialchars($bidError) ?></div><?php endif; ?>
    <?php if($bidSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($bidSuccess) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;align-items:start;">
        <div class="neon-card" style="padding:10px;">
            <img src="<?= htmlspecialchars($singleAuction['img']) ?>" style="width:100%;border-radius:16px;max-height:420px;object-fit:cover;">
        </div>

        <div>
            <span class="category-badge badge-auction">⚡ Enchère en cours</span>
            <h1 style="font-family:'Rajdhani',sans-serif;font-size:24px;color:white;text-shadow:0 0 10px var(--neon-yellow);margin:12px 0;"><?= htmlspecialchars($singleAuction['title']) ?></h1>
            <p style="color:var(--text-soft);margin-bottom:16px;">Vendeur : <span style="color:var(--neon-blue);"><?= htmlspecialchars($singleAuction['seller_name']) ?></span></p>

            <div class="neon-card" style="background:rgba(255,216,77,.05);border-color:rgba(255,216,77,.2);margin-bottom:16px;">
                <p style="color:var(--text-soft);font-size:13px;">Offre actuelle</p>
                <div style="font-size:42px;font-weight:700;color:var(--neon-yellow);"><?= number_format($singleAuction['current_price'],2,',',' ') ?> €</div>
                <p style="color:var(--text-soft);font-size:13px;margin-top:6px;">
                    <?= $singleAuction['winner_name'] ? "Meilleur enchérisseur : <strong style='color:var(--neon-blue);'>" . htmlspecialchars($singleAuction['winner_name']) . "</strong>" : "Aucune enchère" ?>
                </p>
                <p style="color:var(--neon-pink);font-size:13px;font-weight:600;margin-top:8px;" id="countdown" data-end="<?= $singleAuction['end_date'] ?>">
                    Chargement...
                </p>
            </div>

            <?php if ($singleAuction['status'] === 'active' && $singleAuction['seller_id'] != $_SESSION['user_id']): ?>
            <form method="POST">
                <input type="hidden" name="auction_id" value="<?= $auction_id ?>">
                <div class="form-group">
                    <label class="form-label">Votre enchère (€) — minimum : <?= number_format($singleAuction['current_price'] + 0.01,2,',',' ') ?> €</label>
                    <input class="form-input" type="number" name="bid_amount" step="0.01" min="<?= $singleAuction['current_price'] + 0.01 ?>" placeholder="Entrez votre offre" required>
                </div>
                <button type="submit" class="neon-btn" style="background:linear-gradient(135deg,var(--neon-yellow),#ff9500);color:#111;">⚡ Placer mon enchère</button>
            </form>
            <?php elseif ($singleAuction['seller_id'] == $_SESSION['user_id']): ?>
            <p class="alert alert-info">C'est votre enchère.</p>
            <?php else: ?>
            <p class="alert alert-error">Enchère terminée.</p>
            <?php endif; ?>

            <div class="neon-card" style="margin-top:16px;">
                <p style="color:var(--text-soft);line-height:1.7;"><?= nl2br(htmlspecialchars($singleAuction['description'] ?? '')) ?></p>
            </div>
        </div>
    </div>

    <!-- Bid history -->
    <div class="neon-card" style="margin-top:24px;">
        <h2>Historique des enchères</h2>
        <?php if (empty($bidHistory)): ?>
        <p style="color:var(--text-soft);padding:20px 0;">Aucune enchère placée pour le moment.</p>
        <?php else: ?>
        <table class="table-dark">
            <thead><tr><th>Enchérisseur</th><th>Montant</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($bidHistory as $bid): ?>
            <tr>
                <td><?= htmlspecialchars($bid['username']) ?></td>
                <td style="color:var(--neon-yellow);font-weight:700;"><?= number_format($bid['amount'],2,',',' ') ?> €</td>
                <td style="color:var(--text-soft);font-size:13px;"><?= date('d/m/Y H:i', strtotime($bid['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ── AUCTION LIST ── -->
<div class="page-container-wide" style="margin-top:30px;">
    <div class="section-title">⚡ Enchères en cours</div>
    <p class="section-sub"><?= count($auctions) ?> enchère<?= count($auctions) > 1 ? 's' : '' ?> active<?= count($auctions) > 1 ? 's' : '' ?></p>

    <?php if (empty($auctions)): ?>
    <div class="neon-card" style="text-align:center;padding:60px;">
        <div style="font-size:48px;margin-bottom:16px;">⚡</div>
        <h2 style="font-family:'Rajdhani',sans-serif;color:var(--neon-yellow);">Aucune enchère active</h2>
        <p style="color:var(--text-soft);margin-top:12px;">Revenez bientôt ou publiez une enchère.</p>
        <?php if (($_SESSION['role'] ?? 0) >= 1): ?>
        <a href="product_add.php" class="neon-btn" style="display:inline-block;width:auto;padding:12px 24px;text-decoration:none;margin-top:20px;">+ Créer une enchère</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($auctions as $a): ?>
        <a href="home.php?menu=Enchères&auction_id=<?= $a['id'] ?>" class="auction-card">
            <div class="card-img-wrap">
                <img src="<?= htmlspecialchars($a['img']) ?>" alt="<?= htmlspecialchars($a['title']) ?>">
            </div>
            <div class="card-body">
                <span class="category-badge badge-auction">⚡ Enchère</span>
                <div class="card-name"><?= htmlspecialchars($a['title']) ?></div>
                <div class="card-seller">Vendeur : <?= htmlspecialchars($a['seller_name']) ?></div>
                <div style="margin-top:12px;">
                    <div style="color:var(--text-soft);font-size:12px;">Offre actuelle</div>
                    <div class="auction-current-bid"><?= number_format($a['current_price'],2,',',' ') ?> €</div>
                </div>
                <div class="auction-countdown" style="margin-top:8px;" data-end="<?= $a['end_date'] ?>">
                    Fin : <?= date('d/m/Y H:i', strtotime($a['end_date'])) ?>
                </div>
                <div style="margin-top:12px;padding:10px;background:linear-gradient(135deg,rgba(255,216,77,.1),rgba(255,149,0,.1));border-radius:10px;text-align:center;color:var(--neon-yellow);font-weight:700;font-size:13px;">
                    ⚡ Enchérir maintenant →
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . "/../partials/footer.php"; ?>
<script>
function updateCountdowns() {
    document.querySelectorAll('[data-end]').forEach(el => {
        const end = new Date(el.dataset.end.replace(' ', 'T'));
        const now = new Date();
        const diff = end - now;
        if (diff <= 0) {
            el.textContent = '⏰ Terminée';
            el.style.color = 'var(--text-soft)';
            return;
        }
        const d = Math.floor(diff/86400000);
        const h = Math.floor((diff%86400000)/3600000);
        const m = Math.floor((diff%3600000)/60000);
        const s = Math.floor((diff%60000)/1000);
        el.textContent = d > 0 ? `⏳ ${d}j ${h}h ${m}m restant${d>1?'s':''}` : `⏳ ${h}h ${m}m ${s}s`;
    });
}
updateCountdowns();
setInterval(updateCountdowns, 1000);
</script>
</body>
</html>

