<?php
// 系統配置檢查腳本
// 用於檢查系統環境是否符合運行要求

echo "<h2>WorkLog Manager 系統環境檢查</h2>";
echo "<hr>";

// PHP 版本檢查
echo "<h3>1. PHP 版本檢查</h3>";
$phpVersion = phpversion();
echo "當前 PHP 版本: <strong>" . $phpVersion . "</strong><br>";
if (version_compare($phpVersion, '8.0.0', '>=')) {
    echo "<span style='color: green'>✓ PHP 版本符合要求 (需要 8.0+)</span><br>";
} else {
    echo "<span style='color: red'>✗ PHP 版本過低，請升級至 8.0 以上</span><br>";
}

echo "<br>";

// 必要擴展檢查
echo "<h3>2. PHP 擴展檢查</h3>";
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'session'];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span style='color: green'>✓ {$ext} 擴展已安裝</span><br>";
    } else {
        echo "<span style='color: red'>✗ {$ext} 擴展未安裝</span><br>";
    }
}

echo "<br>";

// 資料庫連線檢查
echo "<h3>3. 資料庫連線檢查</h3>";
try {
    require_once 'config/database.php';
    $db = new Database();
    echo "<span style='color: green'>✓ 資料庫連線成功</span><br>";
    
    // 檢查資料表是否存在
    $tables = ['users', 'projects', 'tasks', 'work_reports'];
    foreach ($tables as $table) {
        try {
            $result = $db->query("SHOW TABLES LIKE '{$table}'");
            if ($result->rowCount() > 0) {
                echo "<span style='color: green'>✓ 資料表 {$table} 存在</span><br>";
            } else {
                echo "<span style='color: orange'>⚠ 資料表 {$table} 不存在，請執行 init_database.php</span><br>";
            }
        } catch (Exception $e) {
            echo "<span style='color: red'>✗ 檢查資料表 {$table} 時發生錯誤</span><br>";
        }
    }
    
} catch (Exception $e) {
    echo "<span style='color: red'>✗ 資料庫連線失敗: " . $e->getMessage() . "</span><br>";
    echo "<span style='color: orange'>請檢查 config/database.php 中的設定</span><br>";
}

echo "<br>";

// 檔案權限檢查
echo "<h3>4. 檔案權限檢查</h3>";
$files_to_check = [
    'config/database.php',
    'config/functions.php',
    'index.php',
    'dashboard.php',
    'admin.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            echo "<span style='color: green'>✓ {$file} 可讀取</span><br>";
        } else {
            echo "<span style='color: red'>✗ {$file} 無法讀取</span><br>";
        }
    } else {
        echo "<span style='color: red'>✗ {$file} 檔案不存在</span><br>";
    }
}

echo "<br>";

// Session 檢查
echo "<h3>5. Session 功能檢查</h3>";
if (session_status() == PHP_SESSION_NONE) {
    ob_start(); // 開始輸出緩衝
    session_start();
    ob_end_clean(); // 清除輸出緩衝
}

if (session_status() == PHP_SESSION_ACTIVE) {
    echo "<span style='color: green'>✓ Session 功能正常</span><br>";
} else {
    echo "<span style='color: red'>✗ Session 功能異常</span><br>";
}

echo "<br>";

// 系統資訊
echo "<h3>6. 系統資訊</h3>";
echo "作業系統: " . PHP_OS . "<br>";
echo "Web 伺服器: " . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'CLI模式') . "<br>";
echo "文件根目錄: " . (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'N/A') . "<br>";
echo "當前腳本路徑: " . __DIR__ . "<br>";

echo "<br>";

// 建議
echo "<h3>7. 系統建議</h3>";
echo "• 確保 PHP 錯誤報告已開啟以便除錯<br>";
echo "• 定期備份資料庫<br>";
echo "• 在生產環境中關閉 PHP 錯誤顯示<br>";
echo "• 設定適當的檔案權限 (644 for files, 755 for directories)<br>";
echo "• 考慮使用 HTTPS 加密連線<br>";

echo "<br>";

// 快速操作連結
echo "<h3>8. 快速操作</h3>";
echo "<a href='init_database.php' style='margin-right: 10px;'>初始化資料庫</a>";
echo "<a href='index.php' style='margin-right: 10px;'>前往登入頁面</a>";
echo "<a href='admin.php' style='margin-right: 10px;'>管理員後台</a>";

echo "<hr>";
echo "<p><small>檢查完成時間: " . date('Y-m-d H:i:s') . "</small></p>";
?>
