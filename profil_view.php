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

$stmt = $conn->prepare("SELECT id, username, email, role, privilege, created_at, description, profile_image, password FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$currentUser = $stmt->get_result()->fetch_assoc();

if (!$currentUser) die("Utilisateur introuvable");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    /* image upload */
    if (!empty($_FILES["profile_image"]["name"])) {
        $uploadDir = "uploads/$user_id/profil/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        if (in_array($ext, ["jpg","jpeg","png","webp"])) {
            $filename = time() . "." . $ext;
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $uploadDir . $filename)) {
                $target = $uploadDir . $filename;
                $upd = $conn->prepare("UPDATE users SET profile_image=? WHERE id=?");
                $upd->bind_param("si", $target, $user_id);
                $upd->execute();
                $currentUser["profile_image"] = $target;
            }
        } else {
            $error = "Format d'image non autorisé (jpg, png, webp).";
        }
    }

    $username    = trim($_POST["username"] ?? $currentUser["username"]);
    $email       = trim($_POST["email"] ?? $currentUser["email"]);
    $description = trim($_POST["description"] ?? "");

    $upd = $conn->prepare("UPDATE users SET username=?, email=?, description=? WHERE id=?");
    $upd->bind_param("sssi", $username, $email, $description, $user_id);
    $upd->execute();
    $_SESSION["username"] = $username;

    if (!empty($_POST["current_password"]) && !empty($_POST["new_password"])) {
        if (!password_verify($_POST["current_password"], $currentUser["password"])) {
            $error = "Mot de passe actuel incorrect.";
        } else {
            $newHash = password_hash($_POST["new_password"], PASSWORD_DEFAULT);
            $pw = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $pw->bind_param("si", $newHash, $user_id);
            $pw->execute();
            $message = "Profil et mot de passe mis à jour !";
        }
    } else {
        $message = "Profil mis à jour !";
    }

    /* refresh */
    $stmt = $conn->prepare("SELECT id, username, email, role, privilege, created_at, description, profile_image FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $currentUser = $stmt->get_result()->fetch_assoc();
}

$roleLabel = ['Client','Vendeur'][$currentUser['role']] ?? 'Client';
$privLabel = ['Utilisateur','Modérateur','Administrateur','Super Admin'][$currentUser['privilege']] ?? 'Utilisateur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon profil — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container" style="margin-top:30px;">
    <a href="home.php" class="back-link">← Retour</a>
    <div class="section-title">👤 Mon Profil</div>

    <?php if($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <!-- Avatar + infos -->
        <div class="neon-card" style="display:flex;gap:24px;align-items:center;margin-bottom:20px;">
            <div class="avatar-wrap">
                <img src="<?= !empty($currentUser['profile_image']) ? htmlspecialchars($currentUser['profile_image']) : 'assets/default_user_image.png' ?>" alt="Avatar">
            </div>
            <div>
                <h2 style="font-family:'Rajdhani',sans-serif;font-size:20px;color:white;"><?= htmlspecialchars($currentUser['username']) ?></h2>
                <p style="color:var(--neon-blue);font-size:13px;margin-top:4px;"><?= $roleLabel ?> · <?= $privLabel ?></p>
                <p style="color:var(--text-soft);font-size:12px;margin-top:4px;">Membre depuis le <?= date('d/m/Y', strtotime($currentUser['created_at'])) ?></p>
                <div style="margin-top:10px;">
                    <label class="form-label" style="font-size:12px;">Changer la photo</label>
                    <input type="file" name="profile_image" accept="image/*" style="color:var(--text-soft);font-size:13px;">
                </div>
            </div>
        </div>

        <!-- Edit fields -->
        <div class="neon-card" style="margin-bottom:20px;">
            <h2>Informations personnelles</h2>
            <div class="form-group">
                <label class="form-label">Nom d'utilisateur</label>
                <input class="form-input" type="text" name="username" value="<?= htmlspecialchars($currentUser['username']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Bio / Description</label>
                <textarea class="form-input form-textarea" name="description"><?= htmlspecialchars($currentUser['description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Password -->
        <div class="neon-card" style="margin-bottom:20px;">
            <h2>Changer le mot de passe</h2>
            <p style="color:var(--text-soft);font-size:13px;margin-bottom:16px;">Laissez vide si vous ne souhaitez pas changer.</p>
            <div class="form-group">
                <label class="form-label">Mot de passe actuel</label>
                <input class="form-input" type="password" name="current_password" placeholder="••••••••">
            </div>
            <div class="form-group">
                <label class="form-label">Nouveau mot de passe</label>
                <input class="form-input" type="password" name="new_password" placeholder="Min. 6 caractères">
            </div>
        </div>

        <button type="submit" class="neon-btn">💾 Sauvegarder les modifications</button>
    </form>
</div>

<?php include "partials/footer.php"; ?>
</body>
</html>

