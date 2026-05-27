<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }

$user_id = $_SESSION['user_id'];

/* as buyer */
$sb = $conn->prepare("
    SELECT n.*, p.title as product_title, p.price as product_price, u.username as seller_name,
           (SELECT COUNT(*) FROM negotiation_offers WHERE negotiation_id=n.id) as offer_count,
           (SELECT status FROM negotiation_offers WHERE negotiation_id=n.id ORDER BY created_at DESC LIMIT 1) as last_offer_status
    FROM negotiations n
    JOIN products p ON n.product_id=p.id
    JOIN users u ON n.seller_id=u.id
    WHERE n.buyer_id=?
    ORDER BY n.created_at DESC
");
$sb->bind_param("i", $user_id);
$sb->execute();
$asbuyer = $sb->get_result()->fetch_all(MYSQLI_ASSOC);

/* as seller */
$ss = $conn->prepare("
    SELECT n.*, p.title as product_title, p.price as product_price, u.username as buyer_name,
           (SELECT COUNT(*) FROM negotiation_offers WHERE negotiation_id=n.id) as offer_count,
           (SELECT amount FROM negotiation_offers WHERE negotiation_id=n.id ORDER BY created_at DESC LIMIT 1) as last_amount
    FROM negotiations n
    JOIN products p ON n.product_id=p.id
    JOIN users u ON n.buyer_id=u.id
    WHERE n.seller_id=?
    ORDER BY n.created_at DESC
");
$ss->bind_param("i", $user_id);
$ss->execute();
$asseller = $ss->get_result()->fetch_all(MYSQLI_ASSOC);

$statusLabels = [
    'open'      => ['🟡 En cours', 'var(--neon-yellow)'],
    'accepted'  => ['✅ Acceptée', 'var(--neon-green)'],
    'refused'   => ['❌ Refusée', '#ff6b8a'],
    'concluded' => ['🏁 Conclue', 'var(--text-soft)'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Négociations — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/header.php"; ?>

<div class="page-container-wide" style="margin-top:30px;">
    <div class="section-title">🤝 Négociations</div>
    <p class="section-sub">Gérez toutes vos négociations en cours et passées.</p>

    <!-- Tabs -->
    <div class="admin-tabs" style="margin-bottom:24px;">
        <button class="admin-tab active" onclick="showTab('buyer')">En tant qu'acheteur (<?= count($asbuyer) ?>)</button>
        <?php if (($_SESSION['role'] ?? 0) >= 1): ?>
        <button class="admin-tab" onclick="showTab('seller')">En tant que vendeur (<?= count($asseller) ?>)</button>
        <?php endif; ?>
    </div>

    <!-- Buyer negociations -->
    <div id="tab-buyer">
        <?php if (empty($asbuyer)): ?>
        <div class="neon-card" style="text-align:center;padding:50px;">
            <div style="font-size:48px;margin-bottom:16px;">🤝</div>
            <p style="color:var(--text-soft);">Vous n'avez aucune négociation en cours.<br>Parcourez les produits avec l'option "Négociation" pour faire une offre.</p>
            <a href="home.php" class="neon-btn" style="display:inline-block;width:auto;padding:12px 24px;text-decoration:none;margin-top:20px;">Voir le catalogue</a>
        </div>
        <?php else: ?>
        <?php foreach ($asbuyer as $n):
            $sl = $statusLabels[$n['status']] ?? ['?','var(--text-soft)'];
        ?>
        <div class="nego-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <h3 style="color:white;font-weight:700;"><?= htmlspecialchars($n['product_title']) ?></h3>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:4px;">Vendeur : <?= htmlspecialchars($n['seller_name']) ?> · <?= $n['offer_count'] ?> offre<?= $n['offer_count']>1?'s':'' ?></p>
                    <p style="color:var(--text-soft);font-size:12px;margin-top:2px;"><?= date('d/m/Y', strtotime($n['created_at'])) ?></p>
                </div>
                <div style="text-align:right;">
                    <span style="color:<?= $sl[1] ?>;font-weight:600;"><?= $sl[0] ?></span><br>
                    <span style="color:var(--text-soft);font-size:12px;">Prix de base : <?= number_format($n['product_price'],2,',',' ') ?> €</span>
                </div>
            </div>
            <?php if ($n['status'] === 'open'): ?>
            <div style="margin-top:12px;">
                <a href="negociation_detail.php?id=<?= $n['id'] ?>" class="neon-btn-pink" style="display:inline-block;width:auto;padding:10px 20px;text-decoration:none;font-size:13px;">Voir la négociation →</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Seller negociations -->
    <div id="tab-seller" style="display:none;">
        <?php if (empty($asseller)): ?>
        <div class="neon-card" style="text-align:center;padding:50px;">
            <p style="color:var(--text-soft);">Aucune négociation reçue pour l'instant.</p>
        </div>
        <?php else: ?>
        <?php foreach ($asseller as $n):
            $sl = $statusLabels[$n['status']] ?? ['?','var(--text-soft)'];
        ?>
        <div class="nego-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <h3 style="color:white;font-weight:700;"><?= htmlspecialchars($n['product_title']) ?></h3>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:4px;">Acheteur : <strong style="color:var(--neon-blue);"><?= htmlspecialchars($n['buyer_name']) ?></strong> · <?= $n['offer_count'] ?> offre<?= $n['offer_count']>1?'s':'' ?></p>
                    <?php if ($n['last_amount']): ?>
                    <p style="color:var(--neon-green);font-weight:700;margin-top:4px;">Dernière offre : <?= number_format($n['last_amount'],2,',',' ') ?> €</p>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;">
                    <span style="color:<?= $sl[1] ?>;font-weight:600;"><?= $sl[0] ?></span><br>
                    <span style="color:var(--text-soft);font-size:12px;">Prix : <?= number_format($n['product_price'],2,',',' ') ?> €</span>
                </div>
            </div>
            <?php if ($n['status'] === 'open'): ?>
            <div style="margin-top:12px;">
                <a href="negociation_detail.php?id=<?= $n['id'] ?>" class="neon-btn" style="display:inline-block;width:auto;padding:10px 20px;text-decoration:none;font-size:13px;">Répondre →</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
<script>
function showTab(tab) {
    document.querySelectorAll('[id^="tab-"]').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.admin-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    event.target.classList.add('active');
}
</script>
</body>
</html>
