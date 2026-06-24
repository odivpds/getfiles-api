<?php
// index.php - Router & Controller
require_once 'config.php';

// Atur Header CORS agar bisa dipanggil dari domain/localhost lain
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
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


// ==========================================
// ROUTING & LOGIC
// ==========================================

// 1. GET /api/generator -> Ambil semua video terbaru
if (preg_match('#(/api)?/generator$#', $path) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query("SELECT * FROM videos ORDER BY id DESC");
    $videos = $stmt->fetchAll();
    
    responseJson([
        'success' => true,
        'data' => $videos
    ]);
}

// 2. POST /api/sync-bunny -> Tarik data dari Bunny.net API
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
        
        $insertStmt = $db->prepare("INSERT INTO videos (bunny_id, title, slug) VALUES (?, ?, ?)");

        foreach ($items as $vid) {
            if (!in_array($vid['guid'], $existing)) {
                $slug = slugify($vid['title']) . '-' . generateRandomString(5);
                $insertStmt->execute([$vid['guid'], $vid['title'], $slug]);
                $count++;
            }
        }

        responseJson(['success' => true, 'message' => "Berhasil sinkronisasi $count video baru dari Bunny.net!"]);
    } else {
        responseJson(['success' => false, 'message' => 'Gagal mengambil data dari Bunny.net API'], 500);
    }
}

// 2.5. GET /api/videos -> Ambil video dengan Pagination (Untuk Welcome Page)
elseif (preg_match('#(/api)?/videos$#', $path) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($limit > 100) $limit = 100;
    
    $offset = ($page - 1) * $limit;
    
    $countStmt = $db->query("SELECT COUNT(*) FROM videos");
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);
    
    $stmt = $db->prepare("SELECT * FROM videos ORDER BY id DESC LIMIT :limit OFFSET :offset");
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
elseif (preg_match('#(/api)?/video/([\w-]+)$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $slug = $matches[2];
    
    // Cari video
    $stmt = $db->prepare("SELECT * FROM videos WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
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

// 4. Default Endpoint
else {
    responseJson([
        'message' => 'Getfiles PHP Native API is running!'
    ]);
}
