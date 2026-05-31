<?php
session_start();
require "config/database.php";

if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }
if (($_SESSION['role'] ?? 0) < 1) { header("Location: home.php"); exit(); }

$seller_id = $_SESSION['user_id'];
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title       = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price       = floatval($_POST["price"] ?? 0);
    $stock       = max(1, (int)($_POST["stock"] ?? 1));
    $category    = trim($_POST["category"] ?? "Gaming");
    $sale_type   = in_array($_POST["sale_type"] ?? "", ["direct","auction","negotiation"]) ? $_POST["sale_type"] : "direct";

    if ($title === "" || $price <= 0) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        $stmt = $conn->prepare("INSERT INTO products (seller_id, title, description, price, stock, category, sale_type) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issdiss", $seller_id, $title, $description, $price, $stock, $category, $sale_type);

        if ($stmt->execute()) {
            $product_id = $stmt->insert_id;

            /* upload image */
            if (!empty($_FILES["image"]["name"])) {
                $uploadDir = "uploads/$seller_id/$product_id/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
                if (in_array($ext, ["jpg","jpeg","png","webp"])) {
                    $filename = uniqid() . "." . $ext;
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $uploadDir . $filename)) {
                        $si = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?,?)");
                        $si->bind_param("is", $product_id, $filename);
                        $si->execute();
                    }
                }
            }

            /* if auction: create auction record */
            if ($sale_type === 'auction') {
                $startPrice = floatval($_POST["start_price"] ?? $price);
                $endDate    = $_POST["end_date"] ?? date('Y-m-d H:i:s', strtotime('+7 days'));
                $sa = $conn->prepare("INSERT INTO auctions (product_id, seller_id, starting_price, current_price, end_date) VALUES (?,?,?,?,?)");
                $sa->bind_param("iidds", $product_id, $seller_id, $startPrice, $startPrice, $endDate);
                $sa->execute();
            }

            $success = "Produit ajouté avec succès !";
        } else {
            $error = "Erreur lors de l'ajout.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ajouter un produit — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container" style="margin-top:30px;">
    <a href="home.php?menu=Espace Vendeurs" class="back-link">← Espace Vendeur</a>
    <div class="section-title">+ Ajouter un produit</div>

    <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="neon-card" style="margin-bottom:20px;">
            <h2>Informations du produit</h2>

            <div class="form-group">
                <label class="form-label">Titre *</label>
                <input class="form-input" type="text" name="title" placeholder="Ex: Nintendo Switch OLED" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-input form-textarea" name="description" placeholder="Décrivez votre produit..."></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Prix (€) *</label>
                    <input class="form-input" type="number" step="0.01" name="price" min="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Stock</label>
                    <input class="form-input" type="number" name="stock" min="1" value="1">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Catégorie / Console</label>
                <select class="form-select" name="category">
                    <?php foreach (["Gaming","PS1","PS2","PS3","PS4","PS5","XBOX","XBOX 360","XBOX ONE","XBOX SERIES","GAMECUBE","WII","SWITCH","PC","RETRO","Collection","Premium"] as $cat): ?>
                    <option value="<?= $cat ?>"><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Image du produit</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="color:var(--text-soft);">
            </div>
        </div>

        <!-- Sale type -->
        <div class="neon-card" style="margin-bottom:20px;">
            <h2>Type de vente</h2>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;">
                <label class="payment-method selected" id="type-direct">
                    <input type="radio" name="sale_type" value="direct" checked onchange="toggleAuction(false)">
                    <div><div style="font-weight:700;color:white;">🛒 Achat direct</div><div style="color:var(--text-soft);font-size:12px;">Prix fixe</div></div>
                </label>
                <label class="payment-method" id="type-auction">
                    <input type="radio" name="sale_type" value="auction" onchange="toggleAuction(true)">
                    <div><div style="font-weight:700;color:white;">⚡ Enchère</div><div style="color:var(--text-soft);font-size:12px;">Meilleure offre gagne</div></div>
                </label>
                <label class="payment-method" id="type-nego">
                    <input type="radio" name="sale_type" value="negotiation" onchange="toggleAuction(false)">
                    <div><div style="font-weight:700;color:white;">🤝 Négociation</div><div style="color:var(--text-soft);font-size:12px;">Prix négociable</div></div>
                </label>
            </div>

            <!-- Auction extra fields -->
            <div id="auction-fields" style="display:none;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Prix de départ (€)</label>
                        <input class="form-input" type="number" name="start_price" step="0.01" min="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date limite</label>
                        <input class="form-input" type="datetime-local" name="end_date" min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="neon-btn">🚀 Publier le produit</button>
    </form>
</div>

<?php include "partials/footer.php"; ?>
<script>
function toggleAuction(show) {
    document.getElementById('auction-fields').style.display = show ? 'block' : 'none';
}
document.querySelectorAll('.payment-method').forEach(el => {
    el.addEventListener('click', function(){
        document.querySelectorAll('.payment-method').forEach(e => e.classList.remove('selected'));
        this.classList.add('selected');
    });
});
</script>
</body>
</html>

