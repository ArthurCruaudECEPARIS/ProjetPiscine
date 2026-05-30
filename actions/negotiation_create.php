<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }

$buyer_id   = $_SESSION['user_id'];
$product_id = (int)($_POST['product_id'] ?? 0);
$amount     = floatval($_POST['amount'] ?? 0);
$message    = trim($_POST['message'] ?? '');

if (!$product_id || $amount <= 0) {
    header("Location: ../home.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM products WHERE id=? AND sale_type='negotiation' AND status='available'");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product || $product['seller_id'] == $buyer_id) {
    header("Location: ../product_view.php?id=$product_id&error=Accès+refusé");
    exit();
}

/* check existing open nego */
$chk = $conn->prepare("SELECT id FROM negotiations WHERE product_id=? AND buyer_id=? AND status='open'");
$chk->bind_param("ii", $product_id, $buyer_id);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    header("Location: ../product_view.php?id=$product_id&error=Vous+avez+déjà+une+négociation+en+cours");
    exit();
}

/* create negotiation */
$in = $conn->prepare("INSERT INTO negotiations (product_id, buyer_id, seller_id) VALUES (?,?,?)");
$in->bind_param("iii", $product_id, $buyer_id, $product['seller_id']);
$in->execute();
$nego_id = $conn->insert_id;

/* first offer */
$io = $conn->prepare("INSERT INTO negotiation_offers (negotiation_id, sender_id, amount, message) VALUES (?,?,?,?)");
$io->bind_param("iids", $nego_id, $buyer_id, $amount, $message);
$io->execute();

/* notification to seller */
create_notification($conn, $product['seller_id'], 'negotiation', "🤝 Nouvelle négociation sur \"" . $product['title'] . "\" — Offre : " . number_format($amount,2,',',' ') . " €", "negociation_detail.php?id=$nego_id");

header("Location: ../negociation_detail.php?id=$nego_id");
exit();
