<?php
// migrate-r2.php - Skrip API Khusus Migrasi Bunny Stream ke Cloudflare R2 (HLS)
require_once 'config.php';

$password = $_GET['pass'] ?? $_POST['pass'] ?? '';
$action = $_GET['action'] ?? '';

// Handle AJAX Request (Ambil 1 video belum migrasi r2)
if ($action === 'get_unmigrated' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    if (!defined('ADMIN_UPLOAD_PASSWORD') || $password !== ADMIN_UPLOAD_PASSWORD) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Ambil video yang masih di stream (abaikan yang error agar tidak berulang tanpa henti)
    $stmt = $db->query("SELECT * FROM videos WHERE storage_type = 'stream' LIMIT 1");
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        echo json_encode(['success' => true, 'done' => true, 'message' => 'Semua video telah berhasil dimigrasi ke R2!']);
        exit;
    }

    echo json_encode(['success' => true, 'done' => false, 'video' => $video]);
    exit;
}

// Handle AJAX Request (Ambil semua video yang sudah di R2 untuk fix thumbnail)
if ($action === 'get_all_r2' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    if (!defined('ADMIN_UPLOAD_PASSWORD') || $password !== ADMIN_UPLOAD_PASSWORD) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $stmt = $db->query("SELECT id, title, slug, bunny_id FROM videos WHERE storage_type = 'r2'");
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'videos' => $videos]);
    exit;
}
// Handle AJAX Request (Tandai video sudah migrasi ke r2)
if ($action === 'mark_done' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!defined('ADMIN_UPLOAD_PASSWORD') || $password !== ADMIN_UPLOAD_PASSWORD) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $id = $_POST['id'] ?? '';
    // Filename untuk HLS di R2 kita cukup gunakan folder (slug) sebagai referensi
    // Namun kita bisa biarkan kosong karena struktur HLS biasanya: /[slug]/playlist.m3u8
    $filename = $_POST['filename'] ?? ''; 
    $status = $_POST['status'] ?? 'r2'; // 'r2' atau 'error_r2_...'
    
    if ($id) {
        $updateStmt = $db->prepare("UPDATE videos SET storage_type = ?, filename = ? WHERE id = ?");
        $updateStmt->execute([$status, $filename, $id]);
    }
    
    $sisa = $db->query("SELECT COUNT(*) FROM videos WHERE storage_type = 'stream'")->fetchColumn();
    echo json_encode(['success' => true, 'sisa' => $sisa]);
    exit;
}
