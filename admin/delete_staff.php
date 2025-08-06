<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$staff_id_to_delete = $input['id'] ?? '';

if (!$staff_id_to_delete) {
    echo json_encode(['success' => false, 'message' => '無效的員工ID']);
    exit;
}

// 檢查是否嘗試刪除自己
$current_user = getCurrentUser();
$check_stmt = $pdo->prepare("SELECT staff_id FROM staff WHERE id = ?");
$check_stmt->execute([$staff_id_to_delete]);
$target_staff = $check_stmt->fetch();

if ($target_staff && $target_staff['staff_id'] === $current_user['staff_id']) {
    echo json_encode(['success' => false, 'message' => '不能刪除自己的帳號']);
    exit;
}

try {
    // 開始交易
    $pdo->beginTransaction();
    
    // 刪除相關的出勤記錄
    $delete_attendance_stmt = $pdo->prepare("DELETE FROM attendance WHERE staff_id = (SELECT staff_id FROM staff WHERE id = ?)");
    $delete_attendance_stmt->execute([$staff_id_to_delete]);
    
    // 刪除員工記錄
    $delete_staff_stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
    $delete_staff_stmt->execute([$staff_id_to_delete]);
    
    if ($delete_staff_stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => '員工刪除成功']);
    } else {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => '找不到該員工']);
    }
    
} catch (PDOException $e) {
    $pdo->rollback();
    error_log("Delete staff error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '刪除失敗：' . $e->getMessage()]);
}
?>
