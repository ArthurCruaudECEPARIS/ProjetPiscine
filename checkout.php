<?php
session_start();
require "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$error = "";
$success = "";

/* Get buyer balance */
$stmt = $conn->prepare("SELECT solde, username FROM users WHERE id=?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$buyer = $stmt->get_result()->fetch_assoc();

/* Single product buy (from product_view) */
$single_product = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $pid = (int)$_POST['product_id'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE id=? AND status='available'");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $single_product = $stmt->get_result()->fetch_assoc();
}

/* Cart checkout */
$cart = $_SESSION['cart'] ?? [];
$cartItems = [];
$cartTotal = 0;

if (empty($single_product)) {
    foreach ($cart as $pid => $qty) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE id=? AND status='available'");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        if ($p) {
            $cartItems[] = ['product' => $p, 'qty' => $qty, 'subtotal' => $p['price'] * $qty];
            $cartTotal += $p['price'] * $qty;
        }
    }
}

$orderTotal = $single_product ? $single_product['price'] : $cartTotal;

/* Process payment on confirm */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $method = $_POST['payment_method'] ?? 'wallet';

    if ($method === 'wallet') {
        if ($buyer['solde'] < $orderTotal) {
            $error = "Solde insuffisant. Rechargez votre porte-monnaie.";
        } else {
            $conn->begin_transaction();
            try {
                /* debit buyer */
                $upd = $conn->prepare("UPDATE users SET solde=solde-? WHERE id=?");
                $upd->bind_param("di", $orderTotal, $buyer_id);
                $upd->execute();

                $items_to_buy = $single_product
                    ? [['product' => $single_product, 'qty' => 1, 'subtotal' => $single_product['price']]]
                    : $cartItems;

                foreach ($items_to_buy as $item) {
                    $p = $item['product'];
                    $qty = $item['qty'];

                    /* credit seller */
                    $cr = $conn->prepare("UPDATE users SET solde=solde+? WHERE id=?");
                    $amount = $p['price'] * $qty;
                    $cr->bind_param("di", $amount, $p['seller_id']);
                    $cr->execute();

                    /* decrease stock */
                    $st = $conn->prepare("UPDATE products SET stock=stock-? WHERE id=?");
                    $st->bind_param("ii", $qty, $p['id']);
                    $st->execute();

                    /* mark sold if stock=0 */
                    $ms = $conn->prepare("UPDATE products SET status='sold' WHERE id=? AND stock<=0");
                    $ms->bind_param("i", $p['id']);
                    $ms->execute();

                    /* transaction record */
                    $tr = $conn->prepare("INSERT INTO transactions (buyer_id, seller_id, product_id, amount, type) VALUES (?,?,?,?,'purchase')");
                    $tr->bind_param("iiid", $buyer_id, $p['seller_id'], $p['id'], $amount);
                    $tr->execute();

                    /* notifications */
                    create_notification($conn, $buyer_id, 'purchase', "✅ Achat confirmé : {$p['title']} ({$amount} €)", "porte_monnaie_view.php");
                    create_notification($conn, $p['seller_id'], 'sale', "💰 Vente réalisée : {$p['title']} (+{$amount} €)", "porte_monnaie_view.php");
                }

                $conn->commit();

                /* clear cart */
                if (!$single_product) $_SESSION['cart'] = [];

                $success = "Paiement confirmé ! Votre commande a été traitée avec succès.";
                $orderTotal = 0;
                $cartItems = [];
                $single_product = null;

            } catch (Exception $e) {
                $conn->rollback();
                $error = "Erreur lors du paiement : " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Paiement — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container">
    <a href="javascript:history.back()" class="back-link" style="margin-top:30px;display:inline-flex;">← Retour</a>
    <div class="section-title">💳 Paiement</div>

    <?php if($success): ?>
    <div class="neon-card" style="text-align:center;padding:50px;">
        <div style="font-size:60px;margin-bottom:16px;">🎉</div>
        <h2 style="font-family:'Rajdhani',sans-serif;color:var(--neon-green);">Paiement réussi !</h2>
        <p style="color:var(--text-soft);margin:12px 0 24px;"><?= htmlspecialchars($success) ?></p>
        <a href="home.php" class="neon-btn" style="display:inline-block;width:auto;padding:13px 30px;text-decoration:none;">Retour au catalogue</a>
        <a href="porte_monnaie_view.php" class="btn-ghost" style="display:inline-block;margin-left:12px;padding:13px 30px;">Voir mes transactions</a>
    </div>
    <?php else: ?>

    <?php if($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php if($single_product): ?>
        <input type="hidden" name="product_id" value="<?= $single_product['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="confirm_payment" value="1">

        <!-- Order summary -->
        <div class="neon-card" style="margin-bottom:20px;">
            <h2>Récapitulatif de commande</h2>
            <?php
            $displayItems = $single_product
                ? [['product'=>$single_product,'qty'=>1,'subtotal'=>$single_product['price']]]
                : $cartItems;
            foreach ($displayItems as $item):
            ?>
            <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);">
                <div>
                    <div style="color:white;font-weight:600;"><?= htmlspecialchars($item['product']['title']) ?></div>
                    <div style="color:var(--text-soft);font-size:13px;">Qté : <?= $item['qty'] ?></div>
                </div>
                <div style="color:var(--neon-yellow);font-weight:700;"><?= number_format($item['subtotal'],2,',',' ') ?> €</div>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:space-between;margin-top:16px;">
                <span style="font-size:18px;font-weight:700;color:white;">Total</span>
                <span style="font-size:24px;font-weight:700;color:var(--neon-yellow);"><?= number_format($orderTotal,2,',',' ') ?> €</span>
            </div>
        </div>

        <!-- Payment method -->
        <div class="neon-card" style="margin-bottom:20px;">
            <h2>Méthode de paiement</h2>

            <label class="payment-method selected" id="pm-wallet">
                <input type="radio" name="payment_method" value="wallet" checked style="accent-color:var(--neon-blue);">
                <div>
                    <div style="font-weight:700;color:white;">💰 Porte-monnaie Mercato Nova</div>
                    <div style="color:var(--text-soft);font-size:13px;">Solde disponible : <?= number_format($buyer['solde'],2,',',' ') ?> €</div>
                </div>
            </label>

            <label class="payment-method" id="pm-card">
                <input type="radio" name="payment_method" value="card" style="accent-color:var(--neon-blue);">
                <div>
                    <div style="font-weight:700;color:white;">💳 Carte bancaire (simulé)</div>
                    <div style="color:var(--text-soft);font-size:13px;">Paiement simulé — aucune vraie transaction</div>
                </div>
            </label>

            <label class="payment-method" id="pm-paypal">
                <input type="radio" name="payment_method" value="paypal" style="accent-color:var(--neon-blue);">
                <div>
                    <div style="font-weight:700;color:white;">🔵 PayPal (simulé)</div>
                    <div style="color:var(--text-soft);font-size:13px;">Paiement simulé — aucune vraie transaction</div>
                </div>
            </label>
        </div>

        <!-- Confirm -->
        <div class="neon-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p style="color:var(--text-soft);">Montant à payer</p>
                    <p style="font-size:28px;font-weight:700;color:var(--neon-yellow);"><?= number_format($orderTotal,2,',',' ') ?> €</p>
                </div>
                <button type="submit" class="neon-btn-pink" style="width:auto;margin:0;padding:14px 32px;font-size:16px;">
                    ✅ Confirmer le paiement
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php include "partials/footer.php"; ?>
<script>
document.querySelectorAll('.payment-method').forEach(el => {
    el.addEventListener('click', function(){
        document.querySelectorAll('.payment-method').forEach(e => e.classList.remove('selected'));
        this.classList.add('selected');
    });
});
</script>
</body>
</html>

