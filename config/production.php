<?php
// 生產環境配置
define('ENVIRONMENT', 'production');
define('DEBUG', false);

// 錯誤報告設定（生產環境關閉顯示錯誤）
if (ENVIRONMENT === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

// 安全設定
ini_set('expose_php', 0);
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);

// Session 安全設定
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');

// 上傳檔案限制
ini_set('file_uploads', 1);
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '10M');
ini_set('max_file_uploads', 5);

// 記憶體和執行時間限制
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 30);
ini_set('max_input_time', 60);

// 應用版本
define('APP_VERSION', '1.0.0');
define('APP_NAME', 'WorkLog Manager');

// 系統維護模式
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', '系統維護中，請稍後再試');

// 功能開關
define('ENABLE_REGISTRATION', true);
define('ENABLE_PASSWORD_RESET', false);
define('ENABLE_FILE_UPLOAD', true);

// 檢查維護模式
function checkMaintenanceMode() {
    if (MAINTENANCE_MODE && !isAdmin()) {
        http_response_code(503);
        die('<h1>系統維護中</h1><p>' . MAINTENANCE_MESSAGE . '</p>');
    }
}

// 自動載入器 (如果需要)
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
?>
