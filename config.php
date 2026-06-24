<?php
// config.php - Konfigurasi Utama API

// Konfigurasi Database
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'getfiles');

// Konfigurasi Bunny.net
define('BUNNY_LIBRARY_ID', '681218');
define('BUNNY_API_KEY', '89a6d8e1-7a9e-4838-aacb267dd177-b61f-4b01');

// Setup koneksi PDO
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
