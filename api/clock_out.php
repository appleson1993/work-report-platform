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

try {
    // 檢查今日是否有上班打卡記錄且尚未下班打卡
    $check_stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE staff_id = ? AND work_date = ? AND check_in_time IS NOT NULL
    ");
    $check_stmt->execute([$staff_id, $today]);
    $existing_record = $check_stmt->fetch();
    
    if (!$existing_record) {
        echo json_encode(['success' => false, 'message' => '請先進行上班打卡']);
        exit;
    }
    
    if ($existing_record['check_out_time']) {
        echo json_encode(['success' => false, 'message' => '今日已經打過下班卡了']);
        exit;
    }
    
    // 計算工作時數
    $check_in_time = $existing_record['check_in_time'];
    $total_hours = calculateWorkHours($check_in_time, $current_time);
    
    // 判斷狀態（是否早退）
    $check_out_time_only = date('H:i:s', strtotime($current_time));
    $standard_end = '18:00:00';
    $current_status = $existing_record['status'];
    
    // 如果早退，更新狀態
    if ($check_out_time_only < $standard_end && $total_hours < 8) {
        $current_status = 'early_leave';
    }
    
    // 更新下班打卡記錄
    $update_stmt = $pdo->prepare("
        UPDATE attendance 
        SET check_out_time = ?, total_hours = ?, status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    if ($update_stmt->execute([$current_time, $total_hours, $current_status, $existing_record['id']])) {
        $status_text = getStatusText($current_status);
        echo json_encode([
            'success' => true, 
            'message' => "下班打卡成功！工作時數：{$total_hours}小時，狀態：{$status_text}",
            'time' => $current_time,
            'total_hours' => $total_hours,
            'status' => $current_status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '打卡失敗，請重試']);
    }
    
} catch (PDOException $e) {
    error_log("Clock out error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '系統錯誤，請稍後再試']);
}
?>
