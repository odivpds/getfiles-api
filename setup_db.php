<?php
require_once 'config.php';

try {
    // Buat tabel jika belum ada
    $sql = "CREATE TABLE IF NOT EXISTS videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bunny_id VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $db->exec($sql);
    echo "<h1>Sukses!</h1><p>Tabel 'videos' berhasil dibuat/ditemukan di database " . DB_NAME . ".</p>";
    echo "<p>API Anda sudah siap digunakan!</p>";
} catch (PDOException $e) {
    echo "<h1>Error!</h1><p>Gagal membuat tabel: " . $e->getMessage() . "</p>";
}
