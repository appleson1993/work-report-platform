<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '不允許的請求方法']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

$current_user = getCurrentUser();
$announcement_id = $_POST['announcement_id'] ?? 0;

if (!$announcement_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少公告ID']);
    exit;
}

try {
    // 檢查公告是否存在且有效
    $stmt = $pdo->prepare("
        SELECT id FROM announcements 
        WHERE id = ? AND is_active = 1 
        AND start_date <= NOW() 
        AND (end_date IS NULL OR end_date >= NOW())
    ");
    $stmt->execute([$announcement_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '公告不存在或已失效']);
        exit;
    }
    
    // 標記為已讀（如果尚未讀過）
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO announcement_reads (announcement_id, staff_id) 
        VALUES (?, ?)
    ");
    $stmt->execute([$announcement_id, $current_user['staff_id']]);
    
    echo json_encode(['success' => true, 'message' => '已標記為已讀']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '資料庫錯誤']);
}
?>
