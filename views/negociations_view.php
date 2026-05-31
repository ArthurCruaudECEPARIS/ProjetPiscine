<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }

$user_id = $_SESSION['user_id'];

/* ── Catalogue : produits disponibles à la négociation ── */
$la = $conn->query("
    SELECT p.*, u.username as seller_name
    FROM products p
    JOIN users u ON p.seller_id = u.id
    WHERE p.sale_type = 'negotiation' AND p.status = 'available'
    ORDER BY p.created_at DESC
");
$nego_products = $la->fetch_all(MYSQLI_ASSOC);
$la->free();

foreach ($nego_products as &$np) {
    $pid = (int)$np['id'];
    $sid = (int)$np['seller_id'];
    $imgR = $conn->query("SELECT image_path FROM product_images WHERE product_id=$pid LIMIT 1");
    $imgRow = $imgR ? $imgR->fetch_assoc() : null;
    if ($imgR) $imgR->free();
    $np['img'] = $imgRow
        ? "uploads/$sid/$pid/{$imgRow['image_path']}"
        : "assets/default_image.png";

    /* check si l'utilisateur a déjà une négo ouverte pour ce produit */
    $chkR = $conn->query("SELECT id FROM negotiations WHERE product_id=$pid AND buyer_id=$user_id AND status='open' LIMIT 1");
    $existing = $chkR ? $chkR->fetch_assoc() : null;
    if ($chkR) $chkR->free();
    $np['my_nego_id'] = $existing ? (int)$existing['id'] : null;
}
unset($np);

/* ── Mes négociations en tant qu'acheteur ── */
$sb = $conn->prepare("
    SELECT n.*, p.title as product_title, p.price as product_price, u.username as seller_name,
           (SELECT COUNT(*) FROM negotiation_offers WHERE negotiation_id=n.id) as offer_count,
           (SELECT amount FROM negotiation_offers WHERE negotiation_id=n.id ORDER BY created_at DESC LIMIT 1) as last_amount,
           (SELECT status FROM negotiation_offers WHERE negotiation_id=n.id ORDER BY created_at DESC LIMIT 1) as last_offer_status
    FROM negotiations n
    JOIN products p ON n.product_id = p.id
    JOIN users u ON n.seller_id = u.id
    WHERE n.buyer_id = ?
    ORDER BY n.created_at DESC
");
$sb->bind_param("i", $user_id);
$sb->execute();
$sbRes = $sb->get_result();
$asbuyer = $sbRes->fetch_all(MYSQLI_ASSOC);
$sbRes->free();
$sb->close();

/* ── Mes négociations en tant que vendeur ── */
$ss = $conn->prepare("
    SELECT n.*, p.title as product_title, p.price as product_price, u.username as buyer_name,
           (SELECT COUNT(*) FROM negotiation_offers WHERE negotiation_id=n.id) as offer_count,
           (SELECT amount FROM negotiation_offers WHERE negotiation_id=n.id ORDER BY created_at DESC LIMIT 1) as last_amount
    FROM negotiations n
    JOIN products p ON n.product_id = p.id
    JOIN users u ON n.buyer_id = u.id
    WHERE n.seller_id = ?
    ORDER BY n.created_at DESC
");
$ss->bind_param("i", $user_id);
$ss->execute();
$ssRes = $ss->get_result();
$asseller = $ssRes->fetch_all(MYSQLI_ASSOC);
$ssRes->free();
$ss->close();

$statusLabels = [
    'open'      => ['En cours',  'var(--neon-yellow)'],
    'accepted'  => ['Acceptée',  'var(--neon-green)'],
    'refused'   => ['Refusée',   '#ff6b8a'],
    'concluded' => ['Conclue',   'var(--text-soft)'],
];

$isSeller = ($_SESSION['role'] ?? 0) >= 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Négociations — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/header.php"; ?>

<div class="page-container-wide" style="margin-top:30px;">

    <div class="section-title">🤝 Négociations</div>
    <p class="section-sub">Parcourez les produits disponibles ou gérez vos négociations en cours.</p>

    <!-- Tabs -->
    <div class="admin-tabs" style="margin-bottom:28px;">
        <button class="admin-tab active" onclick="showTab('catalogue',this)">
            Catalogue (<?= count($nego_products) ?>)
        </button>
        <button class="admin-tab" onclick="showTab('buyer',this)">
            Mes offres (<?= count($asbuyer) ?>)
        </button>
        <?php if ($isSeller): ?>
        <button class="admin-tab" onclick="showTab('seller',this)">
            Mes ventes (<?= count($asseller) ?>)
        </button>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB : CATALOGUE
    ══════════════════════════════════════════════════════ -->
    <div id="tab-catalogue">
        <?php if (empty($nego_products)): ?>
        <div class="neon-card" style="text-align:center;padding:60px;">
            <div style="font-size:48px;margin-bottom:16px;">🤝</div>
            <h2 style="font-family:'Rajdhani',sans-serif;color:var(--neon-blue);">Aucun produit en négociation</h2>
            <p style="color:var(--text-soft);margin-top:12px;">Aucun vendeur n'a mis de produit en négociation pour l'instant.</p>
        </div>
        <?php else: ?>
        <div class="product-grid">
            <?php foreach ($nego_products as $np):
                $isOwn = ($np['seller_id'] == $user_id);
            ?>
            <div class="nego-product-card">
                <a href="product_view.php?id=<?= $np['id'] ?>" style="text-decoration:none;">
                    <div class="card-img-wrap">
                        <img src="<?= htmlspecialchars($np['img']) ?>" alt="<?= htmlspecialchars($np['title']) ?>">
                    </div>
                    <div class="card-body">
                        <span class="category-badge badge-negotiation">🤝 Négociation</span>
                        <div class="card-name"><?= htmlspecialchars($np['title']) ?></div>
                        <div class="card-seller">Vendeur : <?= htmlspecialchars($np['seller_name']) ?></div>
                        <div class="card-price" style="margin-top:10px;">
                            <?= number_format($np['price'], 2, ',', ' ') ?> €
                            <span style="font-size:12px;color:var(--text-soft);font-weight:400;"> prix de base</span>
                        </div>
                    </div>
                </a>
                <div style="padding:0 16px 16px;">
                    <?php if ($isOwn): ?>
                    <div style="text-align:center;color:var(--text-soft);font-size:13px;padding:10px;border:1px solid var(--border);border-radius:10px;">
                        C'est votre produit
                    </div>
                    <?php elseif ($np['my_nego_id']): ?>
                    <a href="negociation_detail.php?id=<?= $np['my_nego_id'] ?>"
                       style="display:block;text-align:center;padding:11px;background:rgba(255,107,175,.12);border:1px solid rgba(255,107,175,.3);border-radius:10px;color:var(--neon-pink);font-weight:700;font-size:13px;text-decoration:none;">
                        Voir ma négociation en cours →
                    </a>
                    <?php else: ?>
                    <a href="product_view.php?id=<?= $np['id'] ?>"
                       style="display:block;text-align:center;padding:11px;background:linear-gradient(135deg,rgba(0,229,255,.12),rgba(255,107,175,.08));border:1px solid rgba(0,229,255,.25);border-radius:10px;color:var(--neon-blue);font-weight:700;font-size:13px;text-decoration:none;">
                        Faire une offre →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB : MES OFFRES (acheteur)
    ══════════════════════════════════════════════════════ -->
    <div id="tab-buyer" style="display:none;">
        <?php if (empty($asbuyer)): ?>
        <div class="neon-card" style="text-align:center;padding:50px;">
            <div style="font-size:48px;margin-bottom:16px;">🤝</div>
            <p style="color:var(--text-soft);">
                Vous n'avez encore fait aucune offre.<br>
                Parcourez le catalogue ci-dessus pour en démarrer une.
            </p>
        </div>
        <?php else: ?>
        <?php foreach ($asbuyer as $n):
            $sl = $statusLabels[$n['status']] ?? ['?', 'var(--text-soft)'];
        ?>
        <div class="nego-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div>
                    <h3 style="color:white;font-weight:700;"><?= htmlspecialchars($n['product_title']) ?></h3>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:4px;">
                        Vendeur : <?= htmlspecialchars($n['seller_name']) ?>
                        · <?= $n['offer_count'] ?> offre<?= $n['offer_count'] > 1 ? 's' : '' ?>
                    </p>
                    <p style="color:var(--text-soft);font-size:12px;margin-top:2px;">
                        <?= date('d/m/Y', strtotime($n['created_at'])) ?>
                    </p>
                    <?php if ($n['last_amount']): ?>
                    <p style="color:var(--neon-yellow);font-weight:700;margin-top:6px;">
                        Dernière offre : <?= number_format($n['last_amount'], 2, ',', ' ') ?> €
                    </p>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <span style="color:<?= $sl[1] ?>;font-weight:600;"><?= $sl[0] ?></span><br>
                    <span style="color:var(--text-soft);font-size:12px;">
                        Prix de base : <?= number_format($n['product_price'], 2, ',', ' ') ?> €
                    </span>
                </div>
            </div>
            <div style="margin-top:12px;">
                <a href="negociation_detail.php?id=<?= $n['id'] ?>"
                   class="<?= $n['status'] === 'open' ? 'neon-btn-pink' : 'btn-ghost' ?>"
                   style="display:inline-block;width:auto;padding:10px 20px;text-decoration:none;font-size:13px;">
                    <?= $n['status'] === 'open' ? 'Continuer la négociation →' : 'Voir le détail →' ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB : MES VENTES (vendeur)
    ══════════════════════════════════════════════════════ -->
    <?php if ($isSeller): ?>
    <div id="tab-seller" style="display:none;">
        <?php if (empty($asseller)): ?>
        <div class="neon-card" style="text-align:center;padding:50px;">
            <p style="color:var(--text-soft);">Aucune négociation reçue pour l'instant.</p>
        </div>
        <?php else: ?>
        <?php foreach ($asseller as $n):
            $sl = $statusLabels[$n['status']] ?? ['?', 'var(--text-soft)'];
        ?>
        <div class="nego-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div>
                    <h3 style="color:white;font-weight:700;"><?= htmlspecialchars($n['product_title']) ?></h3>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:4px;">
                        Acheteur : <strong style="color:var(--neon-blue);"><?= htmlspecialchars($n['buyer_name']) ?></strong>
                        · <?= $n['offer_count'] ?> offre<?= $n['offer_count'] > 1 ? 's' : '' ?>
                    </p>
                    <?php if ($n['last_amount']): ?>
                    <p style="color:var(--neon-green);font-weight:700;margin-top:6px;">
                        Dernière offre : <?= number_format($n['last_amount'], 2, ',', ' ') ?> €
                    </p>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <span style="color:<?= $sl[1] ?>;font-weight:600;"><?= $sl[0] ?></span><br>
                    <span style="color:var(--text-soft);font-size:12px;">
                        Prix : <?= number_format($n['product_price'], 2, ',', ' ') ?> €
                    </span>
                </div>
            </div>
            <div style="margin-top:12px;">
                <a href="negociation_detail.php?id=<?= $n['id'] ?>"
                   class="<?= $n['status'] === 'open' ? 'neon-btn' : 'btn-ghost' ?>"
                   style="display:inline-block;width:auto;padding:10px 20px;text-decoration:none;font-size:13px;">
                    <?= $n['status'] === 'open' ? 'Répondre →' : 'Voir le détail →' ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>

<script>
function showTab(name, btn) {
    document.querySelectorAll('[id^="tab-"]').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.admin-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).style.display = 'block';
    btn.classList.add('active');
}
</script>
</body>
</html>
