<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM products WHERE id=? AND seller_id=?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: ../home.php?menu=Espace Vendeurs");
    exit();
}

/* delete image records */
$d1 = $conn->prepare("DELETE FROM product_images WHERE product_id=?");
$d1->bind_param("i", $id);
$d1->execute();

/* delete product */
$d2 = $conn->prepare("DELETE FROM products WHERE id=? AND seller_id=?");
$d2->bind_param("ii", $id, $user_id);
$d2->execute();

header("Location: ../home.php?menu=Espace Vendeurs");
exit();
