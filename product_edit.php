<?php
session_start();
require "config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

/* fetch product and verify ownership */
$stmt = $conn->prepare("SELECT * FROM products WHERE id=? AND seller_id=?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: home.php?menu=Espace Vendeurs");
    exit();
}

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title       = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price       = floatval($_POST["price"] ?? 0);
    $stock       = max(0, (int)($_POST["stock"] ?? 0));
    $category    = trim($_POST["category"] ?? "Gaming");
    $status      = in_array($_POST["status"] ?? "", ["available","hidden"]) ? $_POST["status"] : "available";

    if ($title === "" || $price <= 0) {
        $error = "Titre et prix obligatoires.";
    } else {
        $upd = $conn->prepare("UPDATE products SET title=?, description=?, price=?, stock=?, category=?, status=? WHERE id=? AND seller_id=?");
        $upd->bind_param("ssdissii", $title, $description, $price, $stock, $category, $status, $id, $user_id);
        if ($upd->execute()) {
            /* new image */
            if (!empty($_FILES["image"]["name"])) {
                $uploadDir = "uploads/$user_id/$id/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
                if (in_array($ext, ["jpg","jpeg","png","webp"])) {
                    $filename = uniqid() . "." . $ext;
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $uploadDir . $filename)) {
                        $del = $conn->prepare("DELETE FROM product_images WHERE product_id=?");
                        $del->bind_param("i", $id);
                        $del->execute();
                        $si = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?,?)");
                        $si->bind_param("is", $id, $filename);
                        $si->execute();
                    }
                }
            }
            $success = "Produit modifié avec succès !";
            /* refresh */
            $stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Erreur lors de la modification.";
        }
    }
}

$stmt2 = $conn->prepare("SELECT image_path FROM product_images WHERE product_id=? LIMIT 1");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$imgRow = $stmt2->get_result()->fetch_assoc();
$imgPath = $imgRow ? "uploads/$user_id/$id/{$imgRow['image_path']}" : "assets/default_image.png";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier — <?= htmlspecialchars($product['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container" style="margin-top:30px;">
    <a href="home.php?menu=Espace Vendeurs" class="back-link">← Espace Vendeur</a>
    <div class="section-title">✏️ Modifier le produit</div>

    <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="neon-card" style="margin-bottom:20px;display:flex;gap:20px;align-items:flex-start;">
            <img src="<?= htmlspecialchars($imgPath) ?>" style="width:140px;height:140px;border-radius:14px;object-fit:cover;">
            <div style="flex:1;">
                <label class="form-label">Changer l'image</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="color:var(--text-soft);">
            </div>
        </div>

        <div class="neon-card" style="margin-bottom:20px;">
            <h2>Informations</h2>
            <div class="form-group">
                <label class="form-label">Titre *</label>
                <input class="form-input" type="text" name="title" value="<?= htmlspecialchars($product['title']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-input form-textarea" name="description"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Prix (€) *</label>
                    <input class="form-input" type="number" step="0.01" name="price" value="<?= $product['price'] ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Stock</label>
                    <input class="form-input" type="number" name="stock" value="<?= (int)$product['stock'] ?>" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select class="form-select" name="status">
                        <option value="available" <?= $product['status']==='available'?'selected':'' ?>>Disponible</option>
                        <option value="hidden" <?= $product['status']==='hidden'?'selected':'' ?>>Masqué</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Catégorie / Console</label>
                <select class="form-select" name="category">
                    <?php foreach (["Gaming","PS1","PS2","PS3","PS4","PS5","XBOX","XBOX 360","XBOX ONE","XBOX SERIES","GAMECUBE","WII","SWITCH","PC","RETRO","Collection","Premium"] as $cat): ?>
                    <option value="<?= $cat ?>" <?= $product['category']===$cat?'selected':'' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:12px;">
            <button type="submit" class="neon-btn" style="margin:0;">💾 Sauvegarder</button>
            <a href="home.php?menu=Espace Vendeurs" class="btn-ghost" style="padding:13px 20px;">Annuler</a>
        </div>
    </form>
</div>

<?php include "partials/footer.php"; ?>
</body>
</html>

