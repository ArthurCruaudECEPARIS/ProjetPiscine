<?php
session_start();
require "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

/* ADD MONEY */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_amount'])) {
    $amount = (float)$_POST['add_amount'];
    if ($amount <= 0 || $amount > 10000) {
        $error = "Montant invalide (entre 1 € et 10 000 €).";
    } else {
        $stmt = $conn->prepare("UPDATE users SET solde=solde+? WHERE id=?");
        $stmt->bind_param("di", $amount, $user_id);
        $stmt->execute();
        create_notification($conn, $user_id, 'wallet', "💰 Dépôt de " . number_format($amount,2,',',' ') . " € sur votre compte.", "porte_monnaie_view.php");
        $message = "+" . number_format($amount,2,',',' ') . " € ajoutés à votre solde !";
    }
}

/* GET BALANCE */
$stmt = $conn->prepare("SELECT solde, username FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$balance = $user['solde'];

/* TRANSACTION HISTORY */
$stmt = $conn->prepare("
    SELECT t.*,
           p.title as product_title,
           b.username as buyer_name,
           s.username as seller_name
    FROM transactions t
    LEFT JOIN products p ON t.product_id = p.id
    LEFT JOIN users b ON t.buyer_id = b.id
    LEFT JOIN users s ON t.seller_id = s.id
    WHERE t.buyer_id=? OR t.seller_id=?
    ORDER BY t.created_at DESC
    LIMIT 30
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon Porte-monnaie — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container" style="margin-top:30px;">
    <a href="home.php" class="back-link">← Retour</a>
    <div class="section-title">💰 Mon Porte-monnaie</div>

    <?php if($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Balance -->
    <div class="balance-box">
        <p style="color:var(--text-soft);margin-bottom:8px;">Solde disponible</p>
        <div class="balance-amount"><?= number_format($balance,2,',',' ') ?> €</div>
        <p style="color:var(--text-soft);font-size:13px;margin-top:8px;">Compte de <?= htmlspecialchars($user['username']) ?></p>
    </div>

    <!-- Add money -->
    <div class="neon-card" style="margin-bottom:24px;">
        <h2>Recharger mon solde</h2>
        <p style="color:var(--text-soft);font-size:13px;margin-bottom:16px;">Paiement simulé — aucune vraie transaction bancaire.</p>
        <form method="POST" style="display:flex;gap:12px;align-items:flex-end;">
            <div class="form-group" style="flex:1;margin-bottom:0;">
                <label class="form-label">Montant à ajouter (€)</label>
                <input class="form-input" type="number" name="add_amount" min="1" max="10000" step="0.01" placeholder="Ex: 50.00" required>
            </div>
            <button type="submit" class="neon-btn" style="margin:0;width:auto;padding:13px 24px;">➕ Recharger</button>
        </form>
        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
            <?php foreach ([10,25,50,100,200] as $preset): ?>
            <button type="button" class="btn-ghost" onclick="document.querySelector('[name=add_amount]').value=<?= $preset ?>">+<?= $preset ?> €</button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Transactions -->
    <div class="neon-card">
        <h2>Historique des transactions</h2>
        <?php if (empty($transactions)): ?>
        <p style="color:var(--text-soft);text-align:center;padding:30px 0;">Aucune transaction pour le moment.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="table-dark" style="width:100%;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Produit</th>
                    <th>Type</th>
                    <th>Contrepartie</th>
                    <th style="text-align:right;">Montant</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $t):
                $isBuyer = $t['buyer_id'] == $user_id;
                $sign = $isBuyer ? '-' : '+';
                $color = $isBuyer ? '#ff6b8a' : 'var(--neon-green)';
                $typeLabels = ['purchase'=>'Achat direct','auction'=>'Enchère','negotiation'=>'Négociation'];
            ?>
            <tr>
                <td style="color:var(--text-soft);font-size:13px;"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                <td><?= htmlspecialchars($t['product_title'] ?? 'N/A') ?></td>
                <td><span class="category-badge badge-gaming"><?= htmlspecialchars($typeLabels[$t['type']] ?? $t['type']) ?></span></td>
                <td style="color:var(--text-soft);"><?= htmlspecialchars($isBuyer ? ($t['seller_name'] ?? 'Vendeur') : ($t['buyer_name'] ?? 'Acheteur')) ?></td>
                <td style="text-align:right;font-weight:700;color:<?= $color ?>;"><?= $sign ?><?= number_format($t['amount'],2,',',' ') ?> €</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include "partials/footer.php"; ?>
</body>
</html>

