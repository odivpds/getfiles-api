<?php
// index.php - Router & Controller
require_once 'config.php';

// Atur Header CORS agar bisa dipanggil dari domain/localhost lain
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY, x-api-key');
header('Content-Type: application/json');

// Jika method OPTIONS (Pre-flight CORS), langsung kirim response 200 OK
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Menangkap URL Path (misal: /api/generator)
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Fungsi pembantu respons JSON
function responseJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Fungsi generate random string (pengganti Str::random Laravel)
function generateRandomString($length = 5) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Fungsi slugify (pengganti Str::slug Laravel)
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

// Fungsi generate kode unik 6 digit angka
function generateUniqueCode($db) {
    $maxAttempts = 100;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE code = ?");
        $stmt->execute([$code]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $code;
        }
    }
    return null; // Gagal generate kode unik setelah 100 percobaan
}


// ==========================================
// ROUTING & LOGIC
// ==========================================

// 1. GET /api/generator -> Ambil semua video terbaru
if (preg_match('#(/api)?/generator$#', $path) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query("SELECT * FROM videos WHERE storage_type = 'r2' ORDER BY id DESC");
    $videos = $stmt->fetchAll();
    
    responseJson([
        'success' => true,
        'data' => $videos
    ]);
}

// 2. POST /api/sync-bunny -> Tarik data dari Bunny.net API (+ auto-generate code)
elseif (preg_match('#(/api)?/sync-bunny$#', $path) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!BUNNY_LIBRARY_ID || !BUNNY_API_KEY) {
        responseJson(['success' => false, 'message' => 'Bunny Config Error'], 500);
    }

    $url = "https://video.bunnycdn.com/library/" . BUNNY_LIBRARY_ID . "/videos?page=1&itemsPerPage=1000";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "AccessKey: " . BUNNY_API_KEY,
        "accept: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        $items = $data['items'] ?? [];
        $count = 0;

        // Ambil semua bunny_id yang sudah ada agar hemat query (array_column)
        $stmt = $db->query("SELECT bunny_id FROM videos");
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $insertStmt = $db->prepare("INSERT INTO videos (bunny_id, title, slug, code) VALUES (?, ?, ?, ?)");

        foreach ($items as $vid) {
            if (!in_array($vid['guid'], $existing)) {
                $slug = slugify($vid['title']) . '-' . generateRandomString(5);
                $code = generateUniqueCode($db);
                $insertStmt->execute([$vid['guid'], $vid['title'], $slug, $code]);
                $count++;
            }
        }

        responseJson(['success' => true, 'message' => "Berhasil sinkronisasi $count video baru dari Bunny.net!"]);
    } else {
        responseJson(['success' => false, 'message' => 'Gagal mengambil data dari Bunny.net API'], 500);
    }
}

// 2.7 POST /api/verify-token -> Verifikasi password untuk admin
elseif (preg_match('#(/api)?(/videos)?/verify-token$#', $path) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = apache_request_headers();
    $apiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? '';
    
    if (defined('ADMIN_UPLOAD_PASSWORD') && $apiKey === ADMIN_UPLOAD_PASSWORD) {
        responseJson(['success' => true, 'message' => 'Authorized']);
    } else {
        responseJson(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}

// 2.8 POST /api/upload -> Upload langsung ke Bunny Storage & Simpan ke DB
elseif (preg_match('#(/api)?(/videos)?/upload$#', $path) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = apache_request_headers();
    $apiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? '';
    if (defined('ADMIN_UPLOAD_PASSWORD') && $apiKey !== ADMIN_UPLOAD_PASSWORD) {
        responseJson(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    if (!BUNNY_STORAGE_ZONE || !BUNNY_STORAGE_API_KEY) {
        responseJson(['success' => false, 'message' => 'Bunny Storage Config Error (Belum disetting)'], 500);
    }

    if (!isset($_FILES['video'])) {
        responseJson(['success' => false, 'message' => 'File video tidak ditemukan'], 400);
    }

    $title = $_POST['title'] ?? 'Untitled';
    $slug = $_POST['slug'] ?? (slugify($title) . '-' . generateRandomString(5));
    $videoFile = $_FILES['video']['tmp_name'];
    $videoName = $_FILES['video']['name'];
    $ext = pathinfo($videoName, PATHINFO_EXTENSION);
    if (!$ext) $ext = 'mp4';
    
    // Generate unique filename for storage
    $storageFilename = $slug . '.' . $ext;

    // Upload to Bunny Storage (PUT)
    $storageUrl = "https://" . BUNNY_STORAGE_ENDPOINT . "/" . BUNNY_STORAGE_ZONE . "/" . $storageFilename;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $storageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_UPLOAD, true);
    
    $fileStream = fopen($videoFile, 'r');
    curl_setopt($ch, CURLOPT_INFILE, $fileStream);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($videoFile));
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "AccessKey: " . BUNNY_STORAGE_API_KEY,
        "Content-Type: application/octet-stream"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fileStream);

    if ($httpCode == 201 || $httpCode == 200) {
        // Upload Thumbnail if provided
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['tmp_name']) {
            $thumbFile = $_FILES['thumbnail']['tmp_name'];
            $thumbStorageUrl = "https://" . BUNNY_STORAGE_ENDPOINT . "/" . BUNNY_STORAGE_ZONE . "/" . $slug . ".jpg";
            $chT = curl_init();
            curl_setopt($chT, CURLOPT_URL, $thumbStorageUrl);
            curl_setopt($chT, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chT, CURLOPT_UPLOAD, true);
            $thumbStream = fopen($thumbFile, 'r');
            curl_setopt($chT, CURLOPT_INFILE, $thumbStream);
            curl_setopt($chT, CURLOPT_INFILESIZE, filesize($thumbFile));
            curl_setopt($chT, CURLOPT_HTTPHEADER, [
                "AccessKey: " . BUNNY_STORAGE_API_KEY,
                "Content-Type: image/jpeg"
            ]);
            curl_exec($chT);
            curl_close($chT);
            fclose($thumbStream);
        }

        // Simpan ke DB
        $insertStmt = $db->prepare("INSERT INTO videos (bunny_id, title, slug, storage_type, filename) VALUES (?, ?, ?, ?, ?)");
        // bunny_id bisa diisi dengan $slug untuk kompatibilitas jika dibutuhkan
        $insertStmt->execute([$slug, $title, $slug, 'storage', $storageFilename]);
        
        $videoId = $db->lastInsertId();
        
        // Return JSON format yang sama seperti yang diharapkan scrollTubeAdmin
        responseJson([
            'success' => true,
            'message' => 'Video berhasil diupload ke Storage!',
            'video' => [
                'id' => $videoId,
                'slug' => $slug,
                'title' => $title
            ]
        ]);
    } else {
        responseJson(['success' => false, 'message' => 'Gagal upload ke Bunny Storage', 'bunny_response' => $response], 500);
    }
}

// 2.8.1 POST /api/save-r2-video -> Simpan metadata video yang diupload ke R2 via Local Transcoder
elseif (preg_match('#(/api)?/save-r2-video$#', $path) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = apache_request_headers();
    $apiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? '';
    if (defined('ADMIN_UPLOAD_PASSWORD') && $apiKey !== ADMIN_UPLOAD_PASSWORD) {
        responseJson(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        responseJson(['success' => false, 'message' => 'Invalid JSON'], 400);
    }

    $title = $data['title'] ?? 'Untitled';
    $slug = $data['slug'] ?? '';
    $bunnyId = $data['bunny_id'] ?? $slug; // Untuk kompatibilitas field DB lama

    if (empty($slug)) {
        responseJson(['success' => false, 'message' => 'Slug is required'], 400);
    }

    // Simpan ke DB dengan storage_type = 'r2'
    // filename biarkan kosong atau isi playlist.m3u8
    $insertStmt = $db->prepare("INSERT INTO videos (bunny_id, title, slug, storage_type, filename) VALUES (?, ?, ?, ?, ?)");
    $insertStmt->execute([$bunnyId, $title, $slug, 'r2', 'playlist.m3u8']);
    
    $videoId = $db->lastInsertId();
    
    responseJson([
        'success' => true,
        'message' => 'Metadata video R2 berhasil disimpan',
        'videoId' => $videoId
    ]);
}


// 2.5. GET /api/videos -> Ambil video dengan Pagination (Untuk Welcome Page)
elseif (preg_match('#(/api)?/videos$#', $path) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($limit > 100) $limit = 100;
    
    $offset = ($page - 1) * $limit;
    
    $seed = isset($_GET['seed']) ? (int)$_GET['seed'] : null;
    $countStmt = $db->query("SELECT COUNT(*) FROM videos WHERE storage_type = 'r2'");
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);
    
    if ($seed !== null) {
        $stmt = $db->prepare("SELECT * FROM videos WHERE storage_type = 'r2' ORDER BY RAND(:seed) LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':seed', $seed, PDO::PARAM_INT);
    } else {
        $stmt = $db->prepare("SELECT * FROM videos WHERE storage_type = 'r2' ORDER BY id DESC LIMIT :limit OFFSET :offset");
    }
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $videos = $stmt->fetchAll();
    
    responseJson([
        'success' => true,
        'data' => $videos,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'limit' => $limit
        ]
    ]);
}

// 3. GET /api/video/{slug} -> Ambil 1 video untuk Player (client-template)
elseif (preg_match('#(/api)?/video/([\w\.-]+)$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $slug = $matches[2];
    
    // Hapus ekstensi .mp4 jika URL API secara tidak sengaja memanggilnya
    $slug = str_ireplace('.mp4', '', $slug);
    
    // Ambil 5 karakter terakhir sebagai ID Unik
    $short_id = substr($slug, -5);

    // Cari video yang memiliki slug berakhiran dengan ID Unik tersebut
    $stmt = $db->prepare("SELECT * FROM videos WHERE slug LIKE ? LIMIT 1");
    $stmt->execute(['%-' . $short_id]);
    $video = $stmt->fetch();

    if ($video) {
        // Tambah views (Increment)
        $updateStmt = $db->prepare("UPDATE videos SET views = views + 1 WHERE id = ?");
        $updateStmt->execute([$video['id']]);
        
        // Kembalikan video dengan views yang sudah ditambah 1 (agar real-time di UI)
        $video['views'] = (int)$video['views'] + 1;

        responseJson([
            'success' => true,
            'video' => $video
        ]);
    } else {
        responseJson(['success' => false, 'message' => 'Video not found'], 404);
    }
}

// 4. POST /api/generate-codes -> Generate kode 6 digit untuk semua video yang belum punya kode
elseif (preg_match('#(/api)?/generate-codes$#', $path) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->query("SELECT id FROM videos WHERE code IS NULL OR code = ''");
    $videosWithoutCode = $stmt->fetchAll();
    $count = 0;

    $updateStmt = $db->prepare("UPDATE videos SET code = ? WHERE id = ?");

    foreach ($videosWithoutCode as $video) {
        $code = generateUniqueCode($db);
        if ($code !== null) {
            $updateStmt->execute([$code, $video['id']]);
            $count++;
        }
    }

    responseJson([
        'success' => true,
        'message' => "Berhasil generate kode untuk $count video.",
        'total_updated' => $count
    ]);
}

// 5. GET /api/lookup -> Cari video berdasarkan kode 6 digit, kembalikan URL redirect
elseif (preg_match('#(/api)?/lookup$#', $path) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $code = $_GET['code'] ?? '';

    if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
        responseJson(['success' => false, 'message' => 'Kode harus berupa 6 digit angka.'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM videos WHERE code = ? LIMIT 1");
    $stmt->execute([$code]);
    $video = $stmt->fetch();

    if ($video) {
        // Ambil 5 karakter terakhir slug sebagai short_id
        $shortId = substr($video['slug'], -5);

        responseJson([
            'success' => true,
            'redirect_url' => "https://getfile.click/?v=" . $shortId . ".mp4",
            'video' => [
                'id' => $video['id'],
                'title' => $video['title'],
                'slug' => $video['slug'],
                'code' => $video['code']
            ]
        ]);
    } else {
        responseJson(['success' => false, 'message' => 'Kode tidak ditemukan.'], 404);
    }
}

// 6. Default Endpoint
else {
    responseJson([
        'message' => 'Getfiles PHP Native API is running!'
    ]);
}
