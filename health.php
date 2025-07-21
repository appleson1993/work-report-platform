<?php
// 系統狀態檢查 - 僅供監控使用
require_once 'config/database.php';
require_once 'config/functions.php';

// 簡單的API key驗證
$apiKey = $_GET['key'] ?? '';
$expectedKey = 'worklog_health_check_2024';

if ($apiKey !== $expectedKey) {
    http_response_code(403);
    die('Unauthorized');
}

header('Content-Type: application/json');

try {
    $db = new Database();
    
    // 檢查數據庫連接
    $dbStatus = $db->fetch('SELECT 1 as status');
    
    // 檢查關鍵表是否存在
    $tables = [
        'users',
        'projects', 
        'tasks',
        'work_reports',
        'attendance_records',
        'overtime_records',
        'announcements',
        'audit_logs'
    ];
    
    $tableStatus = [];
    foreach ($tables as $table) {
        try {
            $count = $db->fetch("SELECT COUNT(*) as count FROM {$table}");
            $tableStatus[$table] = [
                'exists' => true,
                'count' => $count['count']
            ];
        } catch (Exception $e) {
            $tableStatus[$table] = [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // 檢查檔案權限
    $writableDirs = ['logs'];
    $dirStatus = [];
    
    foreach ($writableDirs as $dir) {
        $dirStatus[$dir] = [
            'exists' => is_dir($dir),
            'writable' => is_writable($dir)
        ];
    }
    
    // 檢查PHP版本和擴展
    $phpInfo = [
        'version' => PHP_VERSION,
        'extensions' => [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'session' => extension_loaded('session'),
            'json' => extension_loaded('json')
        ]
    ];
    
    $response = [
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => $dbStatus ? 'connected' : 'disconnected',
        'tables' => $tableStatus,
        'directories' => $dirStatus,
        'php' => $phpInfo,
        'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
        'app_version' => defined('APP_VERSION') ? APP_VERSION : 'unknown'
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
