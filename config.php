<?php
// config.php - Konfigurasi Utama API

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'yamahar2_getfiles');
define('DB_PASS', 'getfilestorastudio');
define('DB_NAME', 'yamahar2_getfiles');

// Konfigurasi Bunny.net (Stream Lama - untuk sync)
define('BUNNY_LIBRARY_ID', '681218');
define('BUNNY_API_KEY', '89a6d8e1-7a9e-4838-aacb267dd177-b61f-4b01');

// Konfigurasi Bunny Storage (Baru)
// TODO: ISI DENGAN DATA DARI BUNNY.NET DASHBOARD ANDA
define('BUNNY_STORAGE_ZONE', 'getfiles'); 
define('BUNNY_STORAGE_API_KEY', '7a69e843-4240-4d99-a1658da39ca0-2b3c-4de2'); // Storage Password
define('BUNNY_STORAGE_ENDPOINT', 'sg.storage.bunnycdn.com'); // Endpoint Region
define('BUNNY_PULL_ZONE_URL', 'getfiles.b-cdn.net'); // Contoh: cdn.domain.com atau xyz.b-cdn.net

// Konfigurasi Cloudflare R2 (Baru - Opsi Hemat Egress)
// TODO: ISI DENGAN KREDENSIAL DARI CLOUDFLARE R2 ANDA NANTI
define('R2_ACCOUNT_ID', '8260fc48c94939be68d2a3269e8c2d8b'); 
define('R2_ACCESS_KEY_ID', '91f769fdb90ca184b49182de2d428d58');
define('R2_SECRET_ACCESS_KEY', 'c3ab7bb499fd78b53aa0438ca6eaa61385d4a0aaadcaf3973bfcad29021a83a1');
define('R2_BUCKET_NAME', 'getfiles-video');
define('R2_PUBLIC_URL', 'pub-1880d72cd0f343999d3197d949ff8213.r2.dev'); // Domain custom R2 (tanpa https://)

// Konfigurasi Admin / Keamanan
define('ADMIN_UPLOAD_PASSWORD', 'moneytreeinc'); // Password untuk scrollTubeAdmin

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
