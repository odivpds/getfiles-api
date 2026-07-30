<?php
require_once 'config.php';

try {
    // Buat tabel jika belum ada
    $sql = "CREATE TABLE IF NOT EXISTS videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bunny_id VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        code VARCHAR(6) UNIQUE DEFAULT NULL,
        storage_type VARCHAR(20) DEFAULT 'stream',
        filename VARCHAR(255) DEFAULT NULL,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $db->exec($sql);

    // Tambahkan kolom code jika belum ada (untuk tabel yang sudah dibuat sebelumnya)
    try {
        $db->exec("ALTER TABLE videos ADD COLUMN code VARCHAR(6) UNIQUE DEFAULT NULL");
        echo "<p>Kolom 'code' berhasil ditambahkan.</p>";
    } catch (PDOException $e) { }

    // Tambahkan kolom storage_type dan filename untuk migrasi Bunny Storage
    try {
        $db->exec("ALTER TABLE videos ADD COLUMN storage_type VARCHAR(20) DEFAULT 'stream'");
        echo "<p>Kolom 'storage_type' berhasil ditambahkan.</p>";
    } catch (PDOException $e) { }

    try {
        $db->exec("ALTER TABLE videos ADD COLUMN filename VARCHAR(255) DEFAULT NULL");
        echo "<p>Kolom 'filename' berhasil ditambahkan.</p>";
    } catch (PDOException $e) { }

    echo "<h1>Sukses!</h1><p>Tabel 'videos' berhasil dibuat/ditemukan di database " . DB_NAME . ".</p>";
    echo "<p>API Anda sudah siap digunakan!</p>";
} catch (PDOException $e) {
    echo "<h1>Error!</h1><p>Gagal membuat tabel: " . $e->getMessage() . "</p>";
}
