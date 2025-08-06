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

$input = json_decode(file_get_contents('php://input'), true);
$break_type = $input['break_type'] ?? 'other';

$staff_id = $_SESSION['staff_id'];
$today = date('Y-m-d');
$current_time = date('Y-m-d H:i:s');

try {
    // 檢查今日是否已有打卡記錄且尚未下班
    $check_stmt = $pdo->prepare("
        SELECT id FROM attendance 
        WHERE staff_id = ? AND work_date = ? AND check_in_time IS NOT NULL AND check_out_time IS NULL
    ");
    $check_stmt->execute([$staff_id, $today]);
    $attendance_record = $check_stmt->fetch();
    
    if (!$attendance_record) {
        echo json_encode(['success' => false, 'message' => '請先上班打卡或您今日已下班']);
        exit;
    }
    
    $attendance_id = $attendance_record['id'];
    
    // 檢查是否已經在休息中
    $break_check_stmt = $pdo->prepare("
        SELECT id FROM break_records 
        WHERE attendance_id = ? AND break_end_time IS NULL
    ");
    $break_check_stmt->execute([$attendance_id]);
    $ongoing_break = $break_check_stmt->fetch();
    
    if ($ongoing_break) {
        echo json_encode(['success' => false, 'message' => '您目前已在休息中，請先結束當前休息']);
        exit;
    }
    
    // 插入休息開始記錄
    $insert_stmt = $pdo->prepare("
        INSERT INTO break_records (attendance_id, staff_id, break_start_time, break_type) 
        VALUES (?, ?, ?, ?)
    ");
    
    if ($insert_stmt->execute([$attendance_id, $staff_id, $current_time, $break_type])) {
        $break_id = $pdo->lastInsertId();
        
        // 更新 attendance 表的休息開始時間
        $update_attendance = $pdo->prepare("
            UPDATE attendance 
            SET break_start_time = ? 
            WHERE id = ?
        ");
        $update_attendance->execute([$current_time, $attendance_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => '休息開始記錄成功！',
            'break_id' => $break_id,
            'break_type' => getBreakTypeText($break_type),
            'start_time' => $current_time
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '休息記錄失敗，請重試']);
    }
    
} catch (PDOException $e) {
    error_log("Break start error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '系統錯誤，請稍後再試']);
}
?>
