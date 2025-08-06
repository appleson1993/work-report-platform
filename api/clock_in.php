<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// 檢查登入狀態
if (!isset($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$today = date('Y-m-d');
$current_time = date('Y-m-d H:i:s');
$user_agent = getUserAgent();
$ip_address = getClientIP();

try {
    // 檢查今日是否已有打卡記錄
    $check_stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE staff_id = ? AND work_date = ?
    ");
    $check_stmt->execute([$staff_id, $today]);
    $existing_record = $check_stmt->fetch();
    
    if ($existing_record) {
        echo json_encode(['success' => false, 'message' => '今日已經打過上班卡了']);
        exit;
    }
    
    // 判斷是否遲到
    $check_in_time_only = date('H:i:s', strtotime($current_time));
    $standard_start = '09:00:00';
    $status = ($check_in_time_only > $standard_start) ? 'late' : 'present';
    
    // 插入上班打卡記錄（包含UA和IP）
    $insert_stmt = $pdo->prepare("
        INSERT INTO attendance (staff_id, check_in_time, work_date, status, user_agent, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if ($insert_stmt->execute([$staff_id, $current_time, $today, $status, $user_agent, $ip_address])) {
        $status_text = ($status === 'late') ? '遲到' : '正常';
        echo json_encode([
            'success' => true, 
            'message' => "上班打卡成功！狀態：{$status_text}",
            'time' => $current_time,
            'status' => $status,
            'ip' => $ip_address
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '打卡失敗，請重試']);
    }
    
} catch (PDOException $e) {
    error_log("Clock in error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '系統錯誤，請稍後再試']);
}
?>
