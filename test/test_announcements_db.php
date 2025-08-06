<?php
require_once '../config/database.php';

try {
    // 測試公告表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'announcements'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "✓ announcements 表存在<br>";
        
        // 檢查表結構
        $stmt = $pdo->query("DESCRIBE announcements");
        $columns = $stmt->fetchAll();
        echo "✓ 表結構：<br>";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})<br>";
        }
        
        // 檢查是否有數據
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM announcements");
        $count = $stmt->fetch();
        echo "✓ 公告數量: {$count['count']}<br>";
        
        // 測試查詢功能
        $stmt = $pdo->query("SELECT * FROM announcements LIMIT 3");
        $announcements = $stmt->fetchAll();
        echo "✓ 測試查詢成功，找到 " . count($announcements) . " 條記錄<br>";
        
    } else {
        echo "✗ announcements 表不存在<br>";
    }
    
    // 測試 announcement_reads 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'announcement_reads'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "✓ announcement_reads 表存在<br>";
    } else {
        echo "✗ announcement_reads 表不存在<br>";
    }
    
    echo "<br>資料庫測試完成！";
    
} catch (PDOException $e) {
    echo "錯誤: " . $e->getMessage();
}
?>
