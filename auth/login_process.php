<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$staff_id = trim($_POST['staff_id'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($staff_id) || empty($password)) {
    $_SESSION['error_message'] = '請輸入員工編號和密碼';
    header('Location: ../index.php');
    exit;
}

try {
    // 查詢員工資料
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch();
    
    if ($staff && password_verify($password, $staff['password'])) {
        // 登入成功，設定Session
        $_SESSION['staff_id'] = $staff['staff_id'];
        $_SESSION['staff_name'] = $staff['name'];
        $_SESSION['is_admin'] = $staff['is_admin'];
        $_SESSION['department'] = $staff['department'];
        $_SESSION['position'] = $staff['position'];
        
        // 更新最後登入時間
        $update_stmt = $pdo->prepare("UPDATE staff SET updated_at = CURRENT_TIMESTAMP WHERE staff_id = ?");
        $update_stmt->execute([$staff_id]);
        
        // 根據權限導向不同頁面
        if ($staff['is_admin']) {
            header('Location: ../admin/dashboard.php');
        } else {
            header('Location: ../staff/dashboard.php');
        }
        exit;
    } else {
        $_SESSION['error_message'] = '員工編號或密碼錯誤';
        header('Location: ../index.php');
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    $_SESSION['error_message'] = '系統錯誤，請稍後再試';
    header('Location: ../index.php');
    exit;
}
?>
