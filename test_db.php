<?php
$dbFile = __DIR__ . '/database.sqlite';
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $page = 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $countStmt = $db->query("SELECT COUNT(*) FROM videos");
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);
    
    $stmt = $db->prepare("SELECT * FROM videos ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    $videos = $stmt->fetchAll();
    
    echo "SUCCESS: " . count($videos) . " videos found. Total: " . $totalItems;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
