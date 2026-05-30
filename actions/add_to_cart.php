<?php
session_start();
require_once __DIR__ . "/../config/database.php";

/* endpoint AJAX uniquement — bloquer l'accès direct */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../home.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Non connecté";
    exit();
}

$product_id = (int)($_POST['product_id'] ?? 0);
$qty        = max(1, (int)($_POST['qty'] ?? 1));

if (!$product_id) {
    echo "Produit invalide";
    exit();
}

$stmt = $conn->prepare("SELECT stock, title, sale_type FROM products WHERE id=? AND status='available' AND sale_type='direct'");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo "Produit indisponible";
    exit();
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$currentQty = $_SESSION['cart'][$product_id] ?? 0;
$newQty     = $currentQty + $qty;

if ($newQty > (int)$product['stock']) {
    echo "Stock insuffisant (max : " . (int)$product['stock'] . ")";
    exit();
}

$_SESSION['cart'][$product_id] = $newQty;

echo htmlspecialchars($product['title']) . " ajouté au panier !";
