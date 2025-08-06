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
    
    // 檢查是否有進行中的休息
    $break_check_stmt = $pdo->prepare("
        SELECT * FROM break_records 
        WHERE attendance_id = ? AND break_end_time IS NULL
        ORDER BY break_start_time DESC 
        LIMIT 1
    ");
    $break_check_stmt->execute([$attendance_id]);
    $ongoing_break = $break_check_stmt->fetch();
    
    if (!$ongoing_break) {
        echo json_encode(['success' => false, 'message' => '目前沒有進行中的休息記錄']);
        exit;
    }
    
    // 計算休息時間
    $break_minutes = calculateBreakMinutes($ongoing_break['break_start_time'], $current_time);
    
    // 更新休息結束時間
    $update_break_stmt = $pdo->prepare("
        UPDATE break_records 
        SET break_end_time = ?, break_minutes = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    if ($update_break_stmt->execute([$current_time, $break_minutes, $ongoing_break['id']])) {
        // 計算總休息時間
        $total_break_stmt = $pdo->prepare("
            SELECT SUM(break_minutes) as total_break 
            FROM break_records 
            WHERE attendance_id = ? AND break_end_time IS NOT NULL
        ");
        $total_break_stmt->execute([$attendance_id]);
        $total_break_result = $total_break_stmt->fetch();
        $total_break_minutes = $total_break_result['total_break'] ?? 0;
        
        // 更新 attendance 表的休息結束時間和總休息時間
        $update_attendance = $pdo->prepare("
            UPDATE attendance 
            SET break_end_time = ?, total_break_minutes = ?
            WHERE id = ?
        ");
        $update_attendance->execute([$current_time, $total_break_minutes, $attendance_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => '休息結束記錄成功！',
            'break_type' => getBreakTypeText($ongoing_break['break_type']),
            'break_duration' => formatBreakTime($break_minutes),
            'total_break_today' => formatBreakTime($total_break_minutes),
            'end_time' => $current_time
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '休息結束記錄失敗，請重試']);
    }
    
} catch (PDOException $e) {
    error_log("Break end error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '系統錯誤，請稍後再試']);
}
?>
