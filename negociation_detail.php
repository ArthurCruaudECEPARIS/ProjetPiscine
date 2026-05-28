<?php
session_start();
require "config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$nego_id = (int)($_GET['id'] ?? 0);

/* fetch nego */
$sn = $conn->prepare("SELECT n.*, p.title as product_title, p.price as product_price, p.id as product_id, b.username as buyer_name, s.username as seller_name FROM negotiations n JOIN products p ON n.product_id=p.id JOIN users b ON n.buyer_id=b.id JOIN users s ON n.seller_id=s.id WHERE n.id=?");
$sn->bind_param("i", $nego_id);
$sn->execute();
$nego = $sn->get_result()->fetch_assoc();

if (!$nego || ($nego['buyer_id'] != $user_id && $nego['seller_id'] != $user_id)) {
    header("Location: home.php?menu=Négociations");
    exit();
}

$isBuyer  = ($nego['buyer_id'] == $user_id);
$isSeller = ($nego['seller_id'] == $user_id);

const MAX_OFFERS = 10;

/* fetch offers */
$so = $conn->prepare("SELECT no.*, u.username as sender_name FROM negotiation_offers no JOIN users u ON no.sender_id=u.id WHERE no.negotiation_id=? ORDER BY no.created_at ASC");
$so->bind_param("i", $nego_id);
$so->execute();
$offers = $so->get_result()->fetch_all(MYSQLI_ASSOC);

$offerCount = count($offers);
$lastOffer  = !empty($offers) ? $offers[$offerCount - 1] : null;
$canAct     = $nego['status'] === 'open' && $offerCount < MAX_OFFERS;

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $nego['status'] === 'open') {

    $action = $_POST['action'] ?? '';

    if ($action === 'send_offer' && $isBuyer) {
        $amount  = floatval($_POST['amount'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if ($amount <= 0) {
            $error = "Montant invalide.";
        } elseif ($offerCount >= MAX_OFFERS) {
            $error = "Nombre maximum d'offres atteint.";
        } else {
            /* mark last offer as countered */
            if ($lastOffer && $lastOffer['status'] === 'pending') {
                $uo = $conn->prepare("UPDATE negotiation_offers SET status='countered' WHERE id=?");
                $uo->bind_param("i", $lastOffer['id']);
                $uo->execute();
            }
            $io = $conn->prepare("INSERT INTO negotiation_offers (negotiation_id, sender_id, amount, message) VALUES (?,?,?,?)");
            $io->bind_param("iids", $nego_id, $user_id, $amount, $message);
            $io->execute();
            create_notification($conn, $nego['seller_id'], 'negotiation', "🤝 Nouvelle offre de " . htmlspecialchars($nego['buyer_name']) . " : " . number_format($amount,2,',',' ') . " €", "negociation_detail.php?id=$nego_id");
            $success = "Offre envoyée !";

            /* auto-conclude if max offers reached */
            if ($offerCount + 1 >= MAX_OFFERS) {
                $uc = $conn->prepare("UPDATE negotiations SET status='concluded' WHERE id=?");
                $uc->bind_param("i", $nego_id);
                $uc->execute();
                $success = "Limite d'offres atteinte. La négociation est conclue sans accord.";
                create_notification($conn, $nego['seller_id'], 'negotiation', "🏁 Négociation conclue sans accord (limite atteinte).", "home.php?menu=Négociations");
                create_notification($conn, $nego['buyer_id'], 'negotiation', "🏁 Négociation conclue sans accord (limite atteinte).", "home.php?menu=Négociations");
            }
        }
    }

    elseif ($action === 'accept' && $isSeller && $lastOffer) {
        /* accept last offer */
        $agreedAmount = $lastOffer['amount'];
        $buyer_id = $nego['buyer_id'];
        $seller_id = $nego['seller_id'];

        $b = $conn->prepare("SELECT solde FROM users WHERE id=?");
        $b->bind_param("i", $buyer_id);
        $b->execute();
        $buyerSolde = $b->get_result()->fetch_assoc()['solde'] ?? 0;

        if ($buyerSolde < $agreedAmount) {
            $error = "L'acheteur n'a pas assez de solde pour payer.";
        } else {
            $conn->begin_transaction();
            try {
                $uo = $conn->prepare("UPDATE negotiation_offers SET status='accepted' WHERE id=?");
                $uo->bind_param("i", $lastOffer['id']);
                $uo->execute();

                $un = $conn->prepare("UPDATE negotiations SET status='accepted' WHERE id=?");
                $un->bind_param("i", $nego_id);
                $un->execute();

                $up = $conn->prepare("UPDATE products SET status='sold' WHERE id=?");
                $up->bind_param("i", $nego['product_id']);
                $up->execute();

                $db = $conn->prepare("UPDATE users SET solde=solde-? WHERE id=?");
                $db->bind_param("di", $agreedAmount, $buyer_id);
                $db->execute();

                $cr = $conn->prepare("UPDATE users SET solde=solde+? WHERE id=?");
                $cr->bind_param("di", $agreedAmount, $seller_id);
                $cr->execute();

                $tr = $conn->prepare("INSERT INTO transactions (buyer_id, seller_id, product_id, amount, type) VALUES (?,?,?,?,'negotiation')");
                $tr->bind_param("iiid", $buyer_id, $seller_id, $nego['product_id'], $agreedAmount);
                $tr->execute();

                $conn->commit();

                create_notification($conn, $buyer_id, 'negotiation', "✅ Votre offre de " . number_format($agreedAmount,2,',',' ') . " € a été acceptée ! Montant débité.", "porte_monnaie_view.php");
                create_notification($conn, $seller_id, 'negotiation', "💰 Négociation conclue ! " . number_format($agreedAmount,2,',',' ') . " € reçus.", "porte_monnaie_view.php");

                $success = "Offre acceptée ! Transaction de " . number_format($agreedAmount,2,',',' ') . " € réalisée.";
            } catch(Exception $e) {
                $conn->rollback();
                $error = "Erreur lors de la transaction.";
            }
        }
    }

    elseif ($action === 'refuse' && $isSeller) {
        $uo = $conn->prepare("UPDATE negotiation_offers SET status='refused' WHERE id=?");
        $uo->bind_param("i", $lastOffer['id']);
        $uo->execute();

        $un = $conn->prepare("UPDATE negotiations SET status='refused' WHERE id=?");
        $un->bind_param("i", $nego_id);
        $un->execute();

        create_notification($conn, $nego['buyer_id'], 'negotiation', "❌ Votre offre de négociation a été refusée.", "home.php?menu=Négociations");
        $success = "Offre refusée. La négociation est terminée.";
    }

    elseif ($action === 'counter' && $isSeller) {
        $amount  = floatval($_POST['counter_amount'] ?? 0);
        $message = trim($_POST['counter_message'] ?? '');
        if ($amount <= 0) {
            $error = "Montant invalide.";
        } elseif ($offerCount >= MAX_OFFERS) {
            $error = "Nombre maximum d'offres atteint.";
        } else {
            if ($lastOffer) {
                $uo = $conn->prepare("UPDATE negotiation_offers SET status='countered' WHERE id=?");
                $uo->bind_param("i", $lastOffer['id']);
                $uo->execute();
            }
            $io = $conn->prepare("INSERT INTO negotiation_offers (negotiation_id, sender_id, amount, message) VALUES (?,?,?,?)");
            $io->bind_param("iids", $nego_id, $user_id, $amount, $message);
            $io->execute();
            create_notification($conn, $nego['buyer_id'], 'negotiation', "↩️ Contre-offre du vendeur : " . number_format($amount,2,',',' ') . " €", "negociation_detail.php?id=$nego_id");
            $success = "Contre-offre envoyée !";

            if ($offerCount + 1 >= MAX_OFFERS) {
                $uc = $conn->prepare("UPDATE negotiations SET status='concluded' WHERE id=?");
                $uc->bind_param("i", $nego_id);
                $uc->execute();
                $success .= " Limite atteinte, négociation conclue sans accord.";
            }
        }
    }

    /* refresh */
    $so->execute();
    $offers = $so->get_result()->fetch_all(MYSQLI_ASSOC);
    $offerCount = count($offers);
    $lastOffer  = !empty($offers) ? $offers[$offerCount - 1] : null;
    $sn->execute();
    $nego = $sn->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Négociation — <?= htmlspecialchars($nego['product_title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container" style="margin-top:30px;">
    <a href="home.php?menu=Négociations" class="back-link">← Toutes les négociations</a>

    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Header info -->
    <div class="neon-card" style="margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h2 style="font-family:'Orbitron',sans-serif;font-size:18px;color:white;"><?= htmlspecialchars($nego['product_title']) ?></h2>
                <p style="color:var(--text-soft);font-size:13px;margin-top:4px;">
                    Prix initial : <?= number_format($nego['product_price'],2,',',' ') ?> €
                    · Acheteur : <?= htmlspecialchars($nego['buyer_name']) ?>
                    · Vendeur : <?= htmlspecialchars($nego['seller_name']) ?>
                </p>
            </div>
            <div>
                <?php
                $sl = ['open'=>['🟡 En cours','var(--neon-yellow)'],'accepted'=>['✅ Acceptée','var(--neon-green)'],'refused'=>['❌ Refusée','#ff6b8a'],'concluded'=>['🏁 Conclue','var(--text-soft)']];
                $s = $sl[$nego['status']] ?? ['?','var(--text-soft)'];
                ?>
                <span style="color:<?= $s[1] ?>;font-weight:700;font-size:16px;"><?= $s[0] ?></span>
                <p style="color:var(--text-soft);font-size:12px;margin-top:4px;"><?= $offerCount ?>/<?= MAX_OFFERS ?> offres</p>
            </div>
        </div>
        <!-- progress bar -->
        <div style="margin-top:12px;background:rgba(255,255,255,.06);border-radius:10px;height:6px;">
            <div style="width:<?= min(100, ($offerCount/MAX_OFFERS)*100) ?>%;height:100%;border-radius:10px;background:linear-gradient(90deg,var(--neon-blue),var(--neon-pink));transition:.3s;"></div>
        </div>
    </div>

    <!-- Offer thread -->
    <div class="neon-card" style="margin-bottom:20px;">
        <h2>Historique des offres</h2>
        <?php if (empty($offers)): ?>
        <p style="color:var(--text-soft);padding:20px 0;">Aucune offre envoyée.</p>
        <?php else: ?>
        <?php foreach ($offers as $o):
            $isMe = $o['sender_id'] == $user_id;
        ?>
        <div style="margin-bottom:14px;display:flex;<?= $isMe ? 'flex-direction:row-reverse;' : '' ?>gap:12px;align-items:flex-start;">
            <div style="width:36px;height:36px;border-radius:50%;background:<?= $isMe ? 'linear-gradient(135deg,var(--neon-blue),var(--neon-purple))' : 'rgba(255,255,255,.08)' ?>;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                <?= $isMe ? '👤' : '🏪' ?>
            </div>
            <div style="flex:1;max-width:75%;">
                <div style="background:<?= $isMe ? 'rgba(0,207,255,.08)' : 'rgba(255,255,255,.04)' ?>;border:1px solid <?= $isMe ? 'rgba(0,207,255,.2)' : 'var(--border)' ?>;border-radius:14px;padding:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-weight:700;font-size:13px;color:<?= $isMe ? 'var(--neon-blue)' : 'white' ?>;"><?= htmlspecialchars($o['sender_name']) ?></span>
                        <span style="font-size:11px;color:var(--text-soft);"><?= date('d/m H:i', strtotime($o['created_at'])) ?></span>
                    </div>
                    <div style="font-size:22px;font-weight:700;color:var(--neon-yellow);"><?= number_format($o['amount'],2,',',' ') ?> €</div>
                    <?php if ($o['message']): ?>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:6px;"><?= nl2br(htmlspecialchars($o['message'])) ?></p>
                    <?php endif; ?>
                    <?php
                    $stl = ['pending'=>'','accepted'=>'✅','refused'=>'❌','countered'=>'↩️'];
                    if ($o['status'] !== 'pending'):
                    ?>
                    <p style="color:var(--text-soft);font-size:11px;margin-top:6px;"><?= $stl[$o['status']] ?? '' ?> <?= ucfirst($o['status']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <?php if ($nego['status'] === 'open'): ?>

    <?php if ($isBuyer && (!$lastOffer || $lastOffer['sender_id'] != $user_id)): ?>
    <div class="neon-card">
        <h2>Faire une offre</h2>
        <form method="POST">
            <input type="hidden" name="action" value="send_offer">
            <div class="form-group">
                <label class="form-label">Votre offre (€)</label>
                <input class="form-input" type="number" name="amount" step="0.01" min="0.01" placeholder="Ex: <?= number_format($nego['product_price'] * 0.8,2,'.') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Message (optionnel)</label>
                <textarea class="form-input form-textarea" name="message" placeholder="Expliquez votre offre..."></textarea>
            </div>
            <button type="submit" class="neon-btn-pink">📤 Envoyer l'offre</button>
        </form>
    </div>
    <?php elseif ($isBuyer): ?>
    <div class="alert alert-info">En attente de la réponse du vendeur...</div>
    <?php endif; ?>

    <?php if ($isSeller && $lastOffer && $lastOffer['sender_id'] != $user_id && $lastOffer['status'] === 'pending'): ?>
    <div class="neon-card">
        <h2>Répondre à l'offre de <?= number_format($lastOffer['amount'],2,',',' ') ?> €</h2>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="accept">
                <button type="submit" class="neon-btn" style="margin:0;width:auto;padding:12px 24px;background:linear-gradient(135deg,var(--neon-green),#00aa55);">✅ Accepter (<?= number_format($lastOffer['amount'],2,',',' ') ?> €)</button>
            </form>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="refuse">
                <button type="submit" class="btn-danger" onclick="return confirm('Refuser et terminer la négociation ?')">❌ Refuser</button>
            </form>
        </div>
        <?php if ($offerCount < MAX_OFFERS): ?>
        <form method="POST">
            <input type="hidden" name="action" value="counter">
            <div class="form-group">
                <label class="form-label">Contre-offre (€)</label>
                <input class="form-input" type="number" name="counter_amount" step="0.01" min="0.01" placeholder="Votre contre-proposition" required>
            </div>
            <div class="form-group">
                <label class="form-label">Message</label>
                <textarea class="form-input form-textarea" name="counter_message" placeholder="Expliquez votre contre-offre..."></textarea>
            </div>
            <button type="submit" class="btn-ghost">↩️ Envoyer une contre-offre</button>
        </form>
        <?php endif; ?>
    </div>
    <?php elseif ($isSeller): ?>
    <div class="alert alert-info">En attente d'une offre de l'acheteur...</div>
    <?php endif; ?>

    <?php endif; /* nego open */ ?>
</div>

<?php include "partials/footer.php"; ?>
</body>
</html>
