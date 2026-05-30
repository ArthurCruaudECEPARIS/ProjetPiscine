<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['privilege'] ?? 0) < 1) {
    header("Location: home.php");
    exit();
}

$myPrivilege = (int)$_SESSION['privilege'];
$tab = $_GET['tab'] ?? 'users';
$message = "";
$error = "";

/* ── UPDATE ROLE ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_privilege'])) {
    $uid  = (int)$_POST['uid'];
    $priv = (int)$_POST['privilege'];
    if ($priv < $myPrivilege) {
        $upd = $conn->prepare("UPDATE users SET privilege=? WHERE id=? AND privilege < ?");
        $upd->bind_param("iii", $priv, $uid, $myPrivilege);
        $upd->execute();
        $message = "Rôle mis à jour.";
    } else {
        $error = "Vous ne pouvez pas donner un rôle égal ou supérieur au vôtre.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $uid  = (int)$_POST['uid'];
    $role = (int)$_POST['role'];
    $upd  = $conn->prepare("UPDATE users SET role=? WHERE id=?");
    $upd->bind_param("ii", $role, $uid);
    $upd->execute();
    $message = "Type de compte mis à jour.";
}

/* ── HIDE/SHOW PRODUCT ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_product'])) {
    $pid = (int)$_POST['pid'];
    $tp = $conn->prepare("UPDATE products SET status=IF(status='hidden','available','hidden') WHERE id=?");
    $tp->bind_param("i", $pid);
    $tp->execute();
    $message = "Produit modifié.";
}

/* ── DELETE PRODUCT ── */
if (isset($_GET['del_product'])) {
    $pid = (int)$_GET['del_product'];
    $dpi = $conn->prepare("DELETE FROM product_images WHERE product_id=?");
    $dpi->bind_param("i", $pid);
    $dpi->execute();
    $dp = $conn->prepare("DELETE FROM products WHERE id=?");
    $dp->bind_param("i", $pid);
    $dp->execute();
    $message = "Produit supprimé.";
}

/* ── BAN EMAIL ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user'])) {
    $uid = (int)$_POST['ban_uid'];
    $ustmt = $conn->prepare("SELECT email FROM users WHERE id=? AND privilege < ?");
    $ustmt->bind_param("ii", $uid, $myPrivilege);
    $ustmt->execute();
    $urow = $ustmt->get_result()->fetch_assoc();
    if ($urow) {
        $be = $conn->prepare("INSERT IGNORE INTO banned_emails (email) VALUES (?)");
        $be->bind_param("s", $urow['email']);
        $be->execute();
        $du = $conn->prepare("DELETE FROM users WHERE id=? AND privilege < ?");
        $du->bind_param("ii", $uid, $myPrivilege);
        $du->execute();
        $message = "Utilisateur banni et supprimé.";
    }
}

/* ── APPROVE SELLER REQUEST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request']) && $myPrivilege >= 2) {
    $rid = (int)$_POST['rid'];
    $sr  = $conn->prepare("SELECT sr.*, u.username FROM seller_requests sr JOIN users u ON sr.user_id=u.id WHERE sr.id=? AND sr.status='pending'");
    $sr->bind_param("i", $rid);
    $sr->execute();
    $req = $sr->get_result()->fetch_assoc();
    if ($req) {
        $upU = $conn->prepare("UPDATE users SET role=1 WHERE id=?");
        $upU->bind_param("i", $req['user_id']);
        $upU->execute();

        $upR = $conn->prepare("UPDATE seller_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
        $upR->bind_param("ii", $_SESSION['user_id'], $rid);
        $upR->execute();

        create_notification($conn, $req['user_id'], 'info',
            "🎉 Félicitations ! Votre demande vendeur a été approuvée. Vous pouvez maintenant vendre sur Mercato Nova.",
            "home.php?menu=Espace Vendeurs"
        );
        $message = "Demande approuvée — {$req['username']} est maintenant vendeur.";
    }
}

/* ── REFUSE SELLER REQUEST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refuse_request']) && $myPrivilege >= 2) {
    $rid  = (int)$_POST['rid'];
    $note = trim($_POST['admin_note'] ?? '');
    $sr   = $conn->prepare("SELECT sr.*, u.username FROM seller_requests sr JOIN users u ON sr.user_id=u.id WHERE sr.id=? AND sr.status='pending'");
    $sr->bind_param("i", $rid);
    $sr->execute();
    $req = $sr->get_result()->fetch_assoc();
    if ($req) {
        $upR = $conn->prepare("UPDATE seller_requests SET status='refused', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
        $upR->bind_param("sii", $note, $_SESSION['user_id'], $rid);
        $upR->execute();

        $notifMsg = "❌ Votre demande vendeur a été refusée." . ($note ? " Motif : $note" : '');
        create_notification($conn, $req['user_id'], 'info', $notifMsg, "devenir_vendeur.php");
        $message = "Demande refusée — {$req['username']}.";
    }
}

/* ── FETCH DATA ── */
$stmt = $conn->prepare("SELECT id, username, email, role, privilege, created_at FROM users WHERE privilege < ? ORDER BY privilege DESC, id ASC");
$stmt->bind_param("i", $myPrivilege);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$products = $conn->query("SELECT p.*, u.username as seller_name FROM products p JOIN users u ON p.seller_id=u.id ORDER BY p.created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

/* seller requests (only for admins privilege >= 2) */
$sellerRequests = [];
$pendingCount   = 0;
if ($myPrivilege >= 2) {
    $sellerRequests = $conn->query("
        SELECT sr.*, u.username, u.email
        FROM seller_requests sr
        JOIN users u ON sr.user_id = u.id
        ORDER BY FIELD(sr.status,'pending','refused','approved'), sr.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
    $pendingCount = count(array_filter($sellerRequests, fn($r) => $r['status'] === 'pending'));
}

$privLabels  = ["Utilisateur","Modérateur","Administrateur","Super Admin"];
$roleLabels  = ["Client","Vendeur"];
$statusColors = ['available'=>'var(--neon-green)','sold'=>'var(--text-soft)','hidden'=>'var(--neon-pink)'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administration — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container-wide" style="margin-top:30px;">
    <div class="section-title">🛡 Administration</div>
    <p class="section-sub">Panneau de modération — Niveau : <?= $privLabels[$myPrivilege] ?? 'Inconnu' ?></p>

    <?php if($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-value"><?= count($users) ?></div>
            <div class="stat-label">Utilisateurs gérés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--neon-yellow);"><?= count($products) ?></div>
            <div class="stat-label">Produits publiés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--neon-pink);"><?= count(array_filter($products, fn($p) => $p['status'] === 'hidden')) ?></div>
            <div class="stat-label">Produits masqués</div>
        </div>
        <?php if ($myPrivilege >= 2): ?>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--neon-yellow);"><?= $pendingCount ?></div>
            <div class="stat-label">Demandes vendeur en attente</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="admin-tabs">
        <a href="?tab=users" class="admin-tab <?= $tab==='users'?'active':'' ?>">👥 Utilisateurs (<?= count($users) ?>)</a>
        <a href="?tab=products" class="admin-tab <?= $tab==='products'?'active':'' ?>">📦 Produits (<?= count($products) ?>)</a>
        <a href="?tab=vendors" class="admin-tab <?= $tab==='vendors'?'active':'' ?>">🏪 Vendeurs</a>
        <?php if ($myPrivilege >= 2): ?>
        <a href="?tab=seller_requests" class="admin-tab <?= $tab==='seller_requests'?'active':'' ?>" style="<?= $pendingCount > 0 ? 'color:var(--neon-yellow);' : '' ?>">
            📋 Demandes vendeur<?= $pendingCount > 0 ? " ($pendingCount)" : '' ?>
        </a>
        <?php endif; ?>
    </div>

    <!-- USERS TAB -->
    <?php if ($tab === 'users' || $tab === 'vendors'): ?>
    <?php
    $displayUsers = $tab === 'vendors' ? array_filter($users, fn($u) => $u['role'] >= 1) : $users;
    ?>
    <div class="neon-card">
        <h2><?= $tab === 'vendors' ? 'Gestion des vendeurs' : 'Gestion des utilisateurs' ?></h2>
        <div style="overflow-x:auto;">
        <table class="table-dark">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Rôle</th>
                    <th>Inscrit le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($displayUsers as $u): ?>
            <tr>
                <td style="color:var(--text-soft);">#<?= $u['id'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($u['username']) ?></td>
                <td style="color:var(--text-soft);"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                        <input type="hidden" name="update_role" value="1">
                        <select name="role" class="form-select" style="padding:5px 10px;font-size:12px;width:auto;" onchange="this.form.submit()">
                            <?php foreach ($roleLabels as $i => $rl): ?>
                            <option value="<?= $i ?>" <?= $u['role']==$i?'selected':'' ?>><?= $rl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td>
                    <form method="POST" style="display:inline-flex;gap:6px;align-items:center;">
                        <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                        <select name="privilege" class="form-select" style="padding:5px 10px;font-size:12px;width:auto;">
                            <?php for ($i = 0; $i < $myPrivilege; $i++): ?>
                            <option value="<?= $i ?>" <?= $u['privilege']==$i?'selected':'' ?>><?= $privLabels[$i] ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" name="update_privilege" class="btn-ghost" style="padding:5px 10px;font-size:12px;">✓</button>
                    </form>
                </td>
                <td style="color:var(--text-soft);font-size:12px;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bannir et supprimer cet utilisateur ?')">
                        <input type="hidden" name="ban_uid" value="<?= $u['id'] ?>">
                        <button type="submit" name="ban_user" class="btn-danger" style="font-size:12px;padding:5px 10px;">🚫 Bannir</button>
                    </form>
                    <a href="actions/delete_user.php?id=<?= $u['id'] ?>" class="btn-danger" style="font-size:12px;padding:5px 10px;text-decoration:none;" onclick="return confirm('Supprimer ?')">🗑</a>
                    <?php else: ?>
                    <span style="color:var(--text-soft);font-size:12px;">(vous)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- PRODUCTS TAB -->
    <?php if ($tab === 'products'): ?>
    <div class="neon-card">
        <h2>Modération des produits</h2>
        <div style="overflow-x:auto;">
        <table class="table-dark">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Produit</th>
                    <th>Vendeur</th>
                    <th>Prix</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td style="color:var(--text-soft);">#<?= $p['id'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($p['title']) ?></td>
                <td style="color:var(--neon-blue);"><?= htmlspecialchars($p['seller_name']) ?></td>
                <td style="color:var(--neon-yellow);"><?= number_format($p['price'],2,',',' ') ?> €</td>
                <td><span class="category-badge badge-gaming" style="font-size:10px;"><?= $p['sale_type'] ?></span></td>
                <td>
                    <span style="display:inline-flex;align-items:center;gap:6px;">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= $statusColors[$p['status']] ?? 'var(--text-soft)' ?>"></span>
                        <?= ucfirst($p['status']) ?>
                    </span>
                </td>
                <td style="display:flex;gap:6px;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                        <button type="submit" name="toggle_product" class="btn-ghost" style="font-size:12px;padding:5px 10px;">
                            <?= $p['status']==='hidden' ? '👁 Afficher' : '🚫 Masquer' ?>
                        </button>
                    </form>
                    <a href="?tab=products&del_product=<?= $p['id'] ?>" class="btn-danger" style="font-size:12px;padding:5px 10px;text-decoration:none;" onclick="return confirm('Supprimer définitivement ?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- SELLER REQUESTS TAB -->
    <?php if ($tab === 'seller_requests' && $myPrivilege >= 2): ?>
    <div class="neon-card">
        <h2>Demandes d'accès vendeur</h2>

        <?php if (empty($sellerRequests)): ?>
        <p style="color:var(--text-soft);text-align:center;padding:30px 0;">Aucune demande pour le moment.</p>
        <?php else: ?>
        <?php foreach ($sellerRequests as $req):
            $statusStyle = match($req['status']) {
                'pending'  => ['⏳ En attente', 'var(--neon-yellow)'],
                'approved' => ['✅ Approuvée',  'var(--neon-green)'],
                'refused'  => ['❌ Refusée',    '#ff6b8a'],
                default    => ['?', 'var(--text-soft)']
            };
        ?>
        <div style="border:1px solid var(--border);border-radius:16px;padding:20px;margin-bottom:16px;background:rgba(255,255,255,.02);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                        <span style="font-weight:700;color:white;font-size:16px;"><?= htmlspecialchars($req['username']) ?></span>
                        <span style="color:var(--text-soft);font-size:13px;"><?= htmlspecialchars($req['email']) ?></span>
                        <span style="color:<?= $statusStyle[1] ?>;font-weight:600;font-size:13px;"><?= $statusStyle[0] ?></span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                        <div>
                            <p style="color:var(--text-soft);font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Boutique</p>
                            <p style="color:var(--neon-blue);font-weight:600;"><?= htmlspecialchars($req['shop_name']) ?></p>
                        </div>
                        <div>
                            <p style="color:var(--text-soft);font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Types de produits</p>
                            <p style="color:white;"><?= htmlspecialchars($req['product_types']) ?></p>
                        </div>
                    </div>

                    <div style="margin-bottom:10px;">
                        <p style="color:var(--text-soft);font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Activité</p>
                        <p style="color:var(--text-soft);font-size:13px;line-height:1.6;"><?= nl2br(htmlspecialchars($req['activity_description'])) ?></p>
                    </div>

                    <?php if ($req['experience']): ?>
                    <div style="margin-bottom:10px;">
                        <p style="color:var(--text-soft);font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Expérience</p>
                        <p style="color:var(--text-soft);font-size:13px;"><?= nl2br(htmlspecialchars($req['experience'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($req['motivation']): ?>
                    <div style="margin-bottom:10px;">
                        <p style="color:var(--text-soft);font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Motivation</p>
                        <p style="color:var(--text-soft);font-size:13px;"><?= nl2br(htmlspecialchars($req['motivation'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($req['phone']): ?>
                    <p style="color:var(--text-soft);font-size:13px;">📞 <?= htmlspecialchars($req['phone']) ?></p>
                    <?php endif; ?>

                    <?php if ($req['admin_note']): ?>
                    <p style="color:#ff6b8a;font-size:13px;margin-top:8px;">Note admin : <?= htmlspecialchars($req['admin_note']) ?></p>
                    <?php endif; ?>

                    <p style="color:var(--text-soft);font-size:12px;margin-top:10px;">Soumise le <?= date('d/m/Y à H:i', strtotime($req['created_at'])) ?></p>
                </div>

                <?php if ($req['status'] === 'pending'): ?>
                <div style="display:flex;flex-direction:column;gap:8px;min-width:180px;">
                    <form method="POST">
                        <input type="hidden" name="rid" value="<?= $req['id'] ?>">
                        <button type="submit" name="approve_request" class="neon-btn" style="margin:0;width:100%;background:linear-gradient(135deg,var(--neon-green),#00aa55);color:#111;padding:10px;">
                            ✅ Approuver
                        </button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Refuser cette demande ?')">
                        <input type="hidden" name="rid" value="<?= $req['id'] ?>">
                        <div class="form-group" style="margin-bottom:6px;">
                            <input class="form-input" type="text" name="admin_note" placeholder="Motif du refus (optionnel)" style="font-size:12px;padding:8px;">
                        </div>
                        <button type="submit" name="refuse_request" class="btn-danger" style="width:100%;padding:10px;">
                            ❌ Refuser
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php include "partials/footer.php"; ?>
</body>
</html>
