<?php
session_start();
require_once "../config/database.php";

if (isset($_SESSION['user_id'])) {
    header("Location: ../home.php");
    exit();
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usernameInput = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $userPassword = trim($_POST["password"] ?? "");
    $confirmPassword = trim($_POST["confirm_password"] ?? "");
    $role = 0; /* toujours acheteur à l'inscription — passer vendeur via demande */

    if (empty($usernameInput) || empty($email) || empty($userPassword) || empty($confirmPassword)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif ($userPassword !== $confirmPassword) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($userPassword) < 6) {
        $error = "Le mot de passe doit faire au moins 6 caractères.";
    } else {
        /* check banned */
        $chk = $conn->prepare("SELECT id FROM banned_emails WHERE email=?");
        $chk->bind_param("s", $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "Cet email est banni.";
        } else {
            /* check duplicate */
            $chk2 = $conn->prepare("SELECT id FROM users WHERE email=?");
            $chk2->bind_param("s", $email);
            $chk2->execute();
            if ($chk2->get_result()->num_rows > 0) {
                $error = "Cet email est déjà utilisé.";
            } else {
                $hashedPassword = password_hash($userPassword, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users(username, email, password, role) VALUES(?,?,?,?)");
                $stmt->bind_param("sssi", $usernameInput, $email, $hashedPassword, $role);
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    foreach (["uploads/$user_id", "uploads/$user_id/profil"] as $dir) {
                        if (!is_dir("../$dir")) mkdir("../$dir", 0755, true);
                    }
                    $message = "Compte créé ! Vous pouvez maintenant vous connecter.";
                } else {
                    $error = "Erreur lors de la création du compte.";
                }
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
<title>Inscription — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-page">
<div class="auth-card">

    <div class="auth-logo">
        <img src="../assets/logo.png" alt="Mercato Nova" style="height:52px;width:auto;object-fit:contain;display:block;margin:0 auto 8px;">
    </div>
    <p class="auth-sub">Créer votre compte</p>

    <?php if($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Nom d'utilisateur</label>
            <input class="form-input" type="text" name="username" placeholder="Votre pseudo" required>
        </div>
        <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="email" placeholder="votre@email.com" required>
        </div>
        <div class="form-group">
            <label class="form-label">Mot de passe</label>
            <input class="form-input" type="password" name="password" placeholder="Min. 6 caractères" required>
        </div>
        <div class="form-group">
            <label class="form-label">Confirmer le mot de passe</label>
            <input class="form-input" type="password" name="confirm_password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="neon-btn">Créer mon compte</button>
    </form>

    <p class="auth-footer">
        Déjà inscrit ? <a href="login.php">Se connecter</a>
    </p>

</div>
</div>
</body>
</html>
