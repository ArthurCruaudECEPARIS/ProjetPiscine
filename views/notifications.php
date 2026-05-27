<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }

$user_id = $_SESSION['user_id'];

/* mark all as read */
if (isset($_POST['mark_all'])) {
    $ma = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    $ma->bind_param("i", $user_id);
    $ma->execute();
}

/* mark single as read */
if (isset($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    $mr = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $mr->bind_param("ii", $nid, $user_id);
    $mr->execute();
    /* redirect si un lien cible est fourni */
    if (!empty($_GET['redirect'])) {
        header("Location: " . $_GET['redirect']);
        exit();
    }
}

/* delete one */
if (isset($_GET['delete'])) {
    $nid = (int)$_GET['delete'];
    $dn = $conn->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
    $dn->bind_param("ii", $nid, $user_id);
    $dn->execute();
}

/* fetch */
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));

$typeIcons = [
    'purchase'    => '🛒',
    'sale'        => '💰',
    'auction'     => '⚡',
    'negotiation' => '🤝',
    'wallet'      => '💳',
    'info'        => 'ℹ️',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/header.php"; ?>

<div class="page-container" style="margin-top:30px;">
    <div class="section-title">🔔 Notifications</div>
    <p class="section-sub"><?= $unreadCount > 0 ? "$unreadCount non lue" . ($unreadCount > 1 ? 's' : '') : 'Tout est lu !' ?></p>

    <?php if ($unreadCount > 0): ?>
    <form method="POST" style="margin-bottom:16px;">
        <button name="mark_all" class="btn-ghost">✅ Tout marquer comme lu</button>
    </form>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
    <div class="neon-card" style="text-align:center;padding:60px;">
        <div style="font-size:48px;margin-bottom:16px;">🔔</div>
        <h2 style="font-family:'Orbitron',sans-serif;color:var(--neon-blue);">Aucune notification</h2>
        <p style="color:var(--text-soft);margin-top:12px;">Vos notifications apparaîtront ici.</p>
    </div>
    <?php else: ?>

    <?php foreach ($notifications as $n):
        $icon = $typeIcons[$n['type']] ?? 'ℹ️';
    ?>
    <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
        <div style="font-size:24px;margin-top:2px;"><?= $icon ?></div>
        <div class="notif-dot <?= $n['is_read'] ? 'read' : '' ?>"></div>
        <div style="flex:1;">
            <p style="color:<?= $n['is_read'] ? 'var(--text-soft)' : 'white' ?>;line-height:1.5;"><?= htmlspecialchars($n['message']) ?></p>
            <p style="color:var(--text-soft);font-size:12px;margin-top:4px;"><?= date('d/m/Y à H:i', strtotime($n['created_at'])) ?></p>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0;">
            <?php if ($n['link']): ?>
            <a href="?menu=Notifications&mark_read=<?= $n['id'] ?>&redirect=<?= urlencode($n['link']) ?>" class="btn-ghost" style="padding:6px 12px;font-size:12px;">→</a>
            <?php endif; ?>
            <?php if (!$n['is_read']): ?>
            <a href="?menu=Notifications&mark_read=<?= $n['id'] ?>" class="btn-ghost" style="padding:6px 12px;font-size:12px;">✓</a>
            <?php endif; ?>
            <a href="?menu=Notifications&delete=<?= $n['id'] ?>" class="btn-danger" style="padding:6px 10px;font-size:12px;text-decoration:none;" onclick="return confirm('Supprimer ?')">✕</a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
</body>
</html>
