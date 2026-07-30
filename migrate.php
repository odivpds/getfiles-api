<?php
// migrate.php - Skrip Migrasi Bunny Stream ke Bunny Edge Storage
require_once 'config.php';

// Pastikan hanya admin yang bisa menjalankan ini dengan memberikan parameter ?pass=... di URL, atau beri UI untuk input password
$password = $_GET['pass'] ?? $_POST['pass'] ?? '';
$action = $_GET['action'] ?? '';

// Handle AJAX Request (Ambil 1 video belum migrasi)
if ($action === 'get_unmigrated' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    if (!defined('ADMIN_UPLOAD_PASSWORD') || $password !== ADMIN_UPLOAD_PASSWORD) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $stmt = $db->query("SELECT * FROM videos WHERE storage_type = 'stream' OR storage_type LIKE 'error_%' OR storage_type IS NULL OR storage_type = '' LIMIT 1");
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        echo json_encode(['success' => true, 'done' => true, 'message' => 'Semua video telah berhasil dimigrasi!']);
        exit;
    }

    echo json_encode(['success' => true, 'done' => false, 'video' => $video]);
    exit;
}

// Handle AJAX Request (Tandai video sudah migrasi)
if ($action === 'mark_done' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!defined('ADMIN_UPLOAD_PASSWORD') || $password !== ADMIN_UPLOAD_PASSWORD) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $id = $_POST['id'] ?? '';
    $filename = $_POST['filename'] ?? '';
    $status = $_POST['status'] ?? 'storage'; // storage atau error_download
    
    if ($id) {
        $updateStmt = $db->prepare("UPDATE videos SET storage_type = ?, filename = ? WHERE id = ?");
        $updateStmt->execute([$status, $filename, $id]);
    }
    
    $sisa = $db->query("SELECT COUNT(*) FROM videos WHERE storage_type = 'stream' OR storage_type LIKE 'error_%' OR storage_type IS NULL OR storage_type = ''")->fetchColumn();
    echo json_encode(['success' => true, 'sisa' => $sisa]);
    exit;
}

// Handle AJAX Request (Migrasi 1 Video - Lama)
if ($action === 'step' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!defined('ADMIN_UPLOAD_PASSWORD') || $password !== ADMIN_UPLOAD_PASSWORD) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Ambil 1 video yang belum dimigrasi
    $stmt = $db->query("SELECT * FROM videos WHERE storage_type = 'stream' OR storage_type IS NULL OR storage_type = '' LIMIT 1");
    $video = $stmt->fetch();

    if (!$video) {
        echo json_encode(['success' => true, 'done' => true, 'message' => 'Semua video telah berhasil dimigrasi!']);
        exit;
    }

    $id = $video['id'];
    $slug = $video['slug'];
    $bunnyId = $video['bunny_id'];
    $title = $video['title'];

    // Jika bunny_id kosong, tandai error agar tidak stuck
    if (!$bunnyId) {
        $db->query("UPDATE videos SET storage_type = 'error_no_bunny_id' WHERE id = " . $id);
        echo json_encode(['success' => true, 'done' => false, 'message' => "Video ID $id dilewati karena bunny_id kosong."]);
        exit;
    }

    $streamPullZone = 'vz-80a83061-403.b-cdn.net';
    
    // Coba download 720p, jika gagal coba 480p, lalu 360p, lalu original
    $resolutions = ['play_720p.mp4', 'play_480p.mp4', 'play_360p.mp4', 'play_original.mp4', 'playlist.m3u8']; // fallback
    $videoUrl = '';
    $tempVideoFile = __DIR__ . '/temp_' . $slug . '.mp4';
    
    // Fungsi bantuan cURL untuk download (menggantikan file_get_contents / get_headers yang diblokir hosting)
    function downloadUsingCurl($url, $saveTo) {
        $ch = curl_init($url);
        $fp = fopen($saveTo, 'w+');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        return $httpCode;
    }

    $downloaded = false;
    foreach ($resolutions as $res) {
        $url = "https://{$streamPullZone}/{$bunnyId}/{$res}";
        $code = downloadUsingCurl($url, $tempVideoFile);
        if ($code == 200 || $code == 201) {
            $downloaded = true;
            break;
        } else {
            @unlink($tempVideoFile); // hapus file gagal
        }
    }

    if (!$downloaded) {
        $db->query("UPDATE videos SET storage_type = 'error_download' WHERE id = " . $id);
        echo json_encode(['success' => true, 'done' => false, 'message' => "Gagal download video $title dari Stream (HTTP Error). Pastikan MP4 Fallback aktif di Bunny."]);
        exit;
    }

    // Download Thumbnail
    $tempThumbFile = __DIR__ . '/temp_' . $slug . '.jpg';
    $thumbUrl = "https://{$streamPullZone}/{$bunnyId}/thumbnail.jpg";
    $thumbCode = downloadUsingCurl($thumbUrl, $tempThumbFile);
    $thumbDownloaded = ($thumbCode == 200 || $thumbCode == 201);
    if (!$thumbDownloaded) {
        @unlink($tempThumbFile);
    }

    // UPLOAD KE BUNNY STORAGE
    $storageFilename = $slug . '.mp4';
    $storageUrl = "https://" . BUNNY_STORAGE_ENDPOINT . "/" . BUNNY_STORAGE_ZONE . "/" . $storageFilename;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $storageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_UPLOAD, true);
    
    $fileStream = fopen($tempVideoFile, 'r');
    curl_setopt($ch, CURLOPT_INFILE, $fileStream);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($tempVideoFile));
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "AccessKey: " . BUNNY_STORAGE_API_KEY,
        "Content-Type: application/octet-stream"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fileStream);

    if ($httpCode == 201 || $httpCode == 200) {
        // Upload thumbnail jika ada
        if ($thumbDownloaded) {
            $thumbStorageUrl = "https://" . BUNNY_STORAGE_ENDPOINT . "/" . BUNNY_STORAGE_ZONE . "/" . $slug . ".jpg";
            $chT = curl_init();
            curl_setopt($chT, CURLOPT_URL, $thumbStorageUrl);
            curl_setopt($chT, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chT, CURLOPT_UPLOAD, true);
            $thumbStream = fopen($tempThumbFile, 'r');
            curl_setopt($chT, CURLOPT_INFILE, $thumbStream);
            curl_setopt($chT, CURLOPT_INFILESIZE, filesize($tempThumbFile));
            curl_setopt($chT, CURLOPT_HTTPHEADER, [
                "AccessKey: " . BUNNY_STORAGE_API_KEY,
                "Content-Type: image/jpeg"
            ]);
            curl_exec($chT);
            curl_close($chT);
            fclose($thumbStream);
        }

        // Hapus file temp
        @unlink($tempVideoFile);
        if ($thumbDownloaded) @unlink($tempThumbFile);

        // Update DB
        $updateStmt = $db->prepare("UPDATE videos SET storage_type = 'storage', filename = ? WHERE id = ?");
        $updateStmt->execute([$storageFilename, $id]);

        // Hitung sisa
        $sisa = $db->query("SELECT COUNT(*) FROM videos WHERE storage_type = 'stream' OR storage_type IS NULL OR storage_type = ''")->fetchColumn();

        echo json_encode([
            'success' => true,
            'done' => false,
            'message' => "Sukses migrasi: $title",
            'sisa' => $sisa
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => "Gagal upload $title ke Bunny Storage. $response"]);
    }
    
    exit;
}

// Cek Sisa Video untuk UI
$sisaAwal = 0;
try {
    $sisaAwal = $db->query("SELECT COUNT(*) FROM videos WHERE storage_type = 'stream' OR storage_type IS NULL OR storage_type = ''")->fetchColumn();
} catch(Exception $e) {}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Video | Bunny Stream ke Storage</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #f5f7fa; color: #333; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #1a202c; }
        input { padding: 10px; width: 100%; box-sizing: border-box; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; }
        button { background: #3182ce; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 16px;}
        button:disabled { background: #a0aec0; cursor: not-allowed; }
        #log { margin-top: 20px; background: #1a202c; color: #48bb78; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px; max-height: 300px; overflow-y: auto; white-space: pre-wrap;}
        .stat { background: #ebf8ff; color: #2b6cb0; padding: 10px; border-radius: 6px; font-weight: bold; margin-bottom: 20px; border: 1px solid #bee3f8;}
    </style>
</head>
<body>

<div class="card">
    <h2>🚀 Migrasi Bunny Storage</h2>
    
    <div class="stat" id="stat-box">
        Sisa Video di Stream: <span id="sisa-count"><?= $sisaAwal ?></span> video
    </div>

    <label>Admin Password:</label>
    <input type="password" id="pass" placeholder="Masukkan password admin (moneytreeinc)">
    
    <button id="btn-start" onclick="startMigration()">Mulai Migrasi Massal</button>
    <button id="btn-stop" onclick="stopMigration()" style="display:none; background:#e53e3e; margin-top:10px;">Hentikan</button>

    <div id="log">Siap memulai migrasi...</div>
</div>

<script>
    let isRunning = false;

    function logMessage(msg) {
        const logEl = document.getElementById('log');
        logEl.innerHTML = msg + '<br>' + logEl.innerHTML;
    }

    async function processNext() {
        if (!isRunning) {
            logMessage('⚠️ Migrasi dihentikan oleh user.');
            return;
        }

        const pass = document.getElementById('pass').value;
        if (!pass) {
            alert('Password harus diisi!');
            stopMigration();
            return;
        }

        try {
            const fd = new FormData();
            fd.append('pass', pass);

            const res = await fetch('?action=step', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (data.done) {
                logMessage('🎉 ' + data.message);
                document.getElementById('sisa-count').innerText = '0';
                stopMigration();
            } else if (data.success) {
                logMessage('✅ ' + data.message);
                if (data.sisa !== undefined) document.getElementById('sisa-count').innerText = data.sisa;
                // Lanjut ke video berikutnya
                setTimeout(processNext, 1000); // jeda 1 detik agar server tidak stress
            } else {
                logMessage('❌ ERROR: ' + data.message);
                stopMigration();
            }
        } catch (e) {
            logMessage('❌ ERROR Koneksi: ' + e.message);
            stopMigration();
        }
    }

    function startMigration() {
        const sisa = parseInt(document.getElementById('sisa-count').innerText);
        if (sisa <= 0) {
            alert('Tidak ada video yang perlu dimigrasi!');
            return;
        }

        isRunning = true;
        document.getElementById('btn-start').style.display = 'none';
        document.getElementById('btn-stop').style.display = 'block';
        document.getElementById('pass').disabled = true;
        logMessage('⏳ Memulai migrasi...');
        processNext();
    }

    function stopMigration() {
        isRunning = false;
        document.getElementById('btn-start').style.display = 'block';
        document.getElementById('btn-stop').style.display = 'none';
        document.getElementById('pass').disabled = false;
    }
</script>

</body>
</html>
