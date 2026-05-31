<?php

$host     = "localhost";
$dbname   = "Game_Corner_DB";
$username = "root";
$password = "";

/* connexion sans sélectionner la base pour pouvoir la créer si absente */
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("❌ Impossible de contacter MySQL. Vérifiez que XAMPP (MySQL) est bien démarré.<br><small>" . $conn->connect_error . "</small>");
}

$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($dbname);
$conn->set_charset("utf8mb4");

/* ── auto-create tables if missing ── */
$createQueries = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(200) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role INT DEFAULT 0,
        privilege INT DEFAULT 0,
        solde DECIMAL(10,2) DEFAULT 0.00,
        description TEXT,
        profile_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        stock INT DEFAULT 1,
        status ENUM('available','sold','hidden') DEFAULT 'available',
        sale_type ENUM('direct','auction','negotiation') DEFAULT 'direct',
        category VARCHAR(100) DEFAULT 'Gaming',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL
    )",
    "CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        product_id INT,
        amount DECIMAL(10,2) NOT NULL,
        type ENUM('purchase','auction','negotiation') DEFAULT 'purchase',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS auctions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        seller_id INT NOT NULL,
        starting_price DECIMAL(10,2) NOT NULL,
        current_price DECIMAL(10,2) NOT NULL,
        current_winner_id INT DEFAULT NULL,
        end_date DATETIME NOT NULL,
        status ENUM('active','ended','cancelled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS auction_bids (
        id INT AUTO_INCREMENT PRIMARY KEY,
        auction_id INT NOT NULL,
        bidder_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS negotiations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        status ENUM('open','accepted','refused','concluded') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS negotiation_offers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        negotiation_id INT NOT NULL,
        sender_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        message TEXT,
        status ENUM('pending','accepted','refused','countered') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) DEFAULT 'info',
        message TEXT NOT NULL,
        is_read TINYINT DEFAULT 0,
        link VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS banned_emails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(200) NOT NULL UNIQUE
    )",
    "CREATE TABLE IF NOT EXISTS seller_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        shop_name VARCHAR(255) NOT NULL,
        activity_description TEXT NOT NULL,
        product_types VARCHAR(255) NOT NULL,
        experience TEXT,
        phone VARCHAR(50),
        motivation TEXT,
        status ENUM('pending','approved','refused') DEFAULT 'pending',
        admin_note TEXT,
        reviewed_by INT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
];

foreach ($createQueries as $q) {
    try { $conn->query($q); } catch (Exception $e) { /* ignoré */ }
}

/* ── add missing columns to pre-existing tables ── */
$alterQueries = [
    /* products */
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS sale_type ENUM('direct','auction','negotiation') DEFAULT 'direct'",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'Gaming'",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    /* users */
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS solde DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS description TEXT",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255)",
    /* transactions */
    "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS type ENUM('purchase','auction','negotiation') DEFAULT 'purchase'",
    "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    /* auctions */
    "ALTER TABLE auctions ADD COLUMN IF NOT EXISTS product_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE auctions ADD COLUMN IF NOT EXISTS seller_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE auctions ADD COLUMN IF NOT EXISTS starting_price DECIMAL(10,2) NOT NULL DEFAULT 0",
    "ALTER TABLE auctions ADD COLUMN IF NOT EXISTS current_price DECIMAL(10,2) NOT NULL DEFAULT 0",
    "ALTER TABLE auctions ADD COLUMN IF NOT EXISTS current_winner_id INT DEFAULT NULL",
    "ALTER TABLE auctions ADD COLUMN IF NOT EXISTS end_date DATETIME",
    "ALTER TABLE auctions ADD COLUMN IF NOT EXISTS status ENUM('active','ended','cancelled') DEFAULT 'active'",
    "ALTER TABLE auctions ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    /* auction_bids */
    "ALTER TABLE auction_bids ADD COLUMN IF NOT EXISTS auction_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE auction_bids ADD COLUMN IF NOT EXISTS bidder_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE auction_bids ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2) NOT NULL DEFAULT 0",
    "ALTER TABLE auction_bids ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    /* negotiations */
    "ALTER TABLE negotiations ADD COLUMN IF NOT EXISTS product_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE negotiations ADD COLUMN IF NOT EXISTS buyer_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE negotiations ADD COLUMN IF NOT EXISTS seller_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE negotiations ADD COLUMN IF NOT EXISTS status ENUM('open','accepted','refused','concluded') DEFAULT 'open'",
    "ALTER TABLE negotiations ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    /* negotiation_offers */
    "ALTER TABLE negotiation_offers ADD COLUMN IF NOT EXISTS negotiation_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE negotiation_offers ADD COLUMN IF NOT EXISTS sender_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE negotiation_offers ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2) NOT NULL DEFAULT 0",
    "ALTER TABLE negotiation_offers ADD COLUMN IF NOT EXISTS message TEXT",
    "ALTER TABLE negotiation_offers ADD COLUMN IF NOT EXISTS status ENUM('pending','accepted','refused','countered') DEFAULT 'pending'",
    "ALTER TABLE negotiation_offers ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    /* notifications */
    "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS user_id INT NOT NULL DEFAULT 0",
    "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'info'",
    "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS message TEXT",
    "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS is_read TINYINT DEFAULT 0",
    "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link VARCHAR(255)",
    "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    /* seller_requests */
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS shop_name VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS activity_description TEXT",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS product_types VARCHAR(255)",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS experience TEXT",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS phone VARCHAR(50)",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS motivation TEXT",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','refused') DEFAULT 'pending'",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS admin_note TEXT",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS reviewed_by INT DEFAULT NULL",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS reviewed_at DATETIME DEFAULT NULL",
    "ALTER TABLE seller_requests ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
];

foreach ($alterQueries as $q) {
    try { $conn->query($q); } catch (Exception $e) { /* colonne ou table absente — ignoré */ }
}

if (!function_exists('create_notification')) {

    function create_notification(mysqli $conn, int $user_id, string $type, string $message, string $link = ''): void {

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, message, link)
            VALUES (?,?,?,?)
        ");

        $stmt->bind_param("isss", $user_id, $type, $message, $link);
        $stmt->execute();
    }
}
