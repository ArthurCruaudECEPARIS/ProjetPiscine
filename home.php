<?php
session_start();
require "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$menu = $_GET["menu"] ?? "Transactions";

if ($menu === "Espace Vendeurs") { include("views/espace_vendeurs_view.php"); die(); }
if ($menu === "Enchères")        { include("views/encheres_view.php"); die(); }
if ($menu === "Négociations")    { include("views/negociations_view.php"); die(); }
if ($menu === "Panier")          { include("views/cart.php"); die(); }
if ($menu === "Notifications")   { include("views/notifications.php"); die(); }

/* ── catalogue direct ── */
$name    = $_GET["name"] ?? null;
$price   = $_GET["price"] ?? null;
$console = $_GET["console"] ?? null;

$sql    = "SELECT * FROM products WHERE status='available' AND sale_type='direct'";
$types  = "";
$params = [];

if ($name) {
    $sql .= " AND LOWER(title) LIKE ?";
    $types .= "s";
    $params[] = "%" . strtolower($name) . "%";
}
if ($price) {
    $sql .= " AND price <= ?";
    $types .= "i";
    $params[] = (int)$price;
}
if ($console) {
    $sql .= " AND LOWER(category) LIKE ?";
    $types .= "s";
    $params[] = "%" . strtolower($console) . "%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mercato Nova — Catalogue</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include "partials/header.php"; ?>

<div class="page-wrapper">

<aside class="sidebar">
    <div class="sidebar-section">
        <h3>Filtres</h3>

        <span class="filter-label">Prix maximum</span>
        <input id="priceFilter" type="range" class="price-slider" min="0" max="5000" value="<?= htmlspecialchars($_GET['price'] ?? 5000) ?>">
        <div class="price-labels">
            <span>0 €</span>
            <span id="priceValue"><?= htmlspecialchars($_GET['price'] ?? 5000) ?> €</span>
        </div>

        <div class="filter-group">
            <span class="filter-label">Nom du produit</span>
            <input type="text" id="nameFilter" class="filter-input" placeholder="Ex: Mario, Zelda..." value="<?= htmlspecialchars($_GET['name'] ?? '') ?>">
        </div>

        <div class="filter-group">
            <span class="filter-label">Plateforme / Console</span>
            <select id="consoleFilter" class="filter-select">
                <option value="">Toutes les plateformes</option>
                <?php
                $consoles = ["PS1","PS2","PS3","PS4","PS5","XBOX","XBOX 360","XBOX ONE","XBOX SERIES","GAMECUBE","WII","SWITCH","PC","RETRO"];
                foreach ($consoles as $c):
                ?>
                <option value="<?= $c ?>" <?= (($_GET['console'] ?? '') === $c) ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button id="applybutton" class="neon-btn">Appliquer les filtres</button>
        <a href="home.php" class="btn-ghost" style="display:block;text-align:center;margin-top:10px;">Réinitialiser</a>
    </div>
</aside>

<main class="main-content">

    <div class="catalog-header">
        <div class="catalog-title">
            <h1>GAME MARKET</h1>
            <p><?= count($products) ?> produit<?= count($products) > 1 ? 's' : '' ?> disponible<?= count($products) > 1 ? 's' : '' ?></p>
        </div>
    </div>

    <?php if (empty($products)): ?>
    <div class="alert alert-info" style="margin-top:30px;">Aucun produit ne correspond à votre recherche.</div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $p):
            $pid = (int)$p['id'];
            $stmt2 = $conn->prepare("SELECT image_path FROM product_images WHERE product_id=? LIMIT 1");
            $stmt2->bind_param("i", $pid);
            $stmt2->execute();
            $imgRow = $stmt2->get_result()->fetch_assoc();
            $path = $imgRow ? "uploads/{$p['seller_id']}/$pid/{$imgRow['image_path']}" : "assets/default_image.png";
        ?>
        <a href="product_view.php?id=<?= $pid ?>" class="product-link">
            <div class="product-card">
                <div class="card-img-wrap">
                    <img src="<?= htmlspecialchars($path) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
                </div>
                <div class="card-body">
                    <span class="category-badge badge-direct">🛒 Achat direct</span>
                    <div class="card-name"><?= htmlspecialchars($p['title']) ?></div>
                    <div class="card-seller">Stock : <?= (int)$p['stock'] ?> dispo</div>
                    <div class="card-footer">
                        <span class="card-price"><?= number_format($p['price'],2,',',' ') ?> €</span>
                        <button class="add-cart-btn" onclick="event.preventDefault();addToCart(<?= $pid ?>)">
                            <svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>
</div>

<?php include "partials/footer.php"; ?>

<div id="toast" style="position:fixed;bottom:30px;right:30px;background:linear-gradient(135deg,var(--neon-blue),var(--neon-purple));color:white;padding:14px 22px;border-radius:14px;display:none;font-weight:600;z-index:9999;"></div>

<script>
const priceSlider = document.getElementById("priceFilter");
const priceVal = document.getElementById("priceValue");
if(priceSlider){
    priceSlider.addEventListener("input", () => priceVal.textContent = priceSlider.value + " €");
}

document.getElementById("applybutton")?.addEventListener("click", function(){
    const url = new URL(window.location.href);
    url.searchParams.set("price", document.getElementById("priceFilter").value);
    const name = document.getElementById("nameFilter").value.trim();
    const cons = document.getElementById("consoleFilter").value;
    name ? url.searchParams.set("name", name) : url.searchParams.delete("name");
    cons ? url.searchParams.set("console", cons) : url.searchParams.delete("console");
    url.searchParams.set("menu", "Transactions");
    window.location.href = url.toString();
});

function addToCart(productId) {
    const form = new FormData();
    form.append("product_id", productId);
    form.append("qty", 1);
    fetch("actions/add_to_cart.php", { method:"POST", body:form })
        .then(r => r.text()).then(msg => {
            showToast("✅ " + msg);
        }).catch(() => showToast("Erreur"));
}

function showToast(msg) {
    const t = document.getElementById("toast");
    t.textContent = msg;
    t.style.display = "block";
    setTimeout(() => t.style.display = "none", 2500);
}
</script>
</body>
</html>

