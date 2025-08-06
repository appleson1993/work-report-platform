<?php
// 測試薪資記錄欄位名稱
require_once 'config/database.php';

try {
    // 檢查 salary_records 表結構
    $stmt = $pdo->prepare("DESCRIBE salary_records");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "薪資記錄表的欄位結構：\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // 檢查是否有現有的薪資記錄
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM salary_records");
    $stmt->execute();
    $count = $stmt->fetch();
    
    echo "\n目前薪資記錄數量：" . $count['count'] . "\n";
    
    if ($count['count'] > 0) {
        // 顯示一筆範例記錄
        $stmt = $pdo->prepare("SELECT * FROM salary_records LIMIT 1");
        $stmt->execute();
        $record = $stmt->fetch();
        
        echo "\n範例記錄欄位：\n";
        foreach ($record as $key => $value) {
            if (!is_numeric($key)) {
                echo "- $key: $value\n";
            }
        }
    }
    
    echo "\n✅ 資料庫連接和薪資記錄表檢查完成\n";
    
} catch (Exception $e) {
    echo "❌ 錯誤：" . $e->getMessage() . "\n";
}
?>
