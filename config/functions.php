<?php
// Session 管理和工具函數

// 載入生產環境配置
require_once __DIR__ . '/production.php';

// 定義安全檢查常數
define('SECURITY_CHECK', true);

//time taipei
date_default_timezone_set('Asia/Taipei');

// 載入安全模組
require_once __DIR__ . '/security.php';

// 啟動 Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    secureSession(); // 應用安全的 session 配置
}

// 檢查維護模式
checkMaintenanceMode();

// 檢查是否已登入
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// 檢查是否為管理員
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// 登入使用者
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
}

// 登出使用者
function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        AuditLogger::log('user_logout', "User logged out: " . ($_SESSION['user_name'] ?? 'Unknown'));
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

// 需要登入檢查
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

// 需要管理員權限檢查
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

// 安全獲取 POST 資料
function getPostValue($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// 安全獲取並清理 POST 資料
function getPostValueSanitized($key, $default = '') {
    return isset($_POST[$key]) ? sanitizeInput($_POST[$key]) : $default;
}

// 安全獲取整數型 POST 資料
function getPostValueInt($key, $default = 0) {
    return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
}

// 安全獲取布林型 POST 資料（轉為整數）
function getPostValueBool($key, $default = 0) {
    if (!isset($_POST[$key])) {
        return $default;
    }
    $value = $_POST[$key];
    if ($value === 'true' || $value === '1' || $value === 1 || $value === true) {
        return 1;
    }
    return 0;
}

// 清理輸入資料
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// 驗證 Email 格式
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// 密碼加密
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// 密碼驗證
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// 格式化日期
function formatDate($date) {
    return date('Y-m-d', strtotime($date));
}

// 格式化日期時間
function formatDateTime($datetime) {
    return date('Y-m-d H:i:s', strtotime($datetime));
}

// 取得狀態中文顯示
function getStatusText($status) {
    $statusMap = [
        'pending' => '未開始',
        'in_progress' => '進行中',
        'completed' => '已完成'
    ];
    return $statusMap[$status] ?? '未知';
}

// 取得狀態 Bootstrap 樣式
function getStatusBadge($status) {
    $badgeMap = [
        'pending' => 'badge bg-secondary',
        'in_progress' => 'badge bg-warning',
        'completed' => 'badge bg-success'
    ];
    return $badgeMap[$status] ?? 'badge bg-secondary';
}

// JSON 回應
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
?>
