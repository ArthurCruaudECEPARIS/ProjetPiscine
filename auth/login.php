<?php
session_start();
require_once("../config/database.php");

if (isset($_SESSION['user_id'])) {
    header("Location: ../home.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? "");
    $userPassword = trim($_POST["password"] ?? "");

    if (!empty($email) && !empty($userPassword)) {
        $stmt = $conn->prepare("SELECT id, username, password, role, privilege FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($userPassword, $user["password"])) {
                session_regenerate_id(true);
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["privilege"] = $user["privilege"];
                header("Location: ../home.php");
                exit();
            } else {
                $error = "Mot de passe incorrect.";
            }
        } else {
            $error = "Aucun compte avec cet email.";
        }
        $stmt->close();
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-page">
<div class="auth-card">

    <div class="auth-logo">
        <img src="../assets/logo.png" alt="Mercato Nova" style="height:52px;width:auto;object-fit:contain;display:block;margin:0 auto 8px;">
    </div>
    <p class="auth-sub">Connectez-vous à votre compte</p>

    <?php if($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="email" placeholder="votre@email.com" required>
        </div>
        <div class="form-group">
            <label class="form-label">Mot de passe</label>
            <input class="form-input" type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="neon-btn">Se connecter</button>
    </form>

    <p class="auth-footer">
        Pas encore de compte ? <a href="register.php">Créer un compte</a>
    </p>

</div>
</div>
</body>
</html>
