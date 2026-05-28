<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if (($_SESSION['privilege'] ?? 0) < 1)  { header("Location: ../home.php"); exit(); }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: ../panneau_moderation.php"); exit(); }

$myPrivilege = (int)($_SESSION['privilege'] ?? 0);

/* prevent deleting a higher-privilege user or yourself */
$stmt = $conn->prepare("SELECT id, privilege FROM users WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();

if (!$target || $target['privilege'] >= $myPrivilege || $target['id'] == $_SESSION['user_id']) {
    header("Location: ../panneau_moderation.php?error=Interdit");
    exit();
}

$stmt = $conn->prepare("DELETE FROM users WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: ../panneau_moderation.php");
exit();
