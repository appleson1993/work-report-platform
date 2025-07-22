<?php
// 測試加班時間整合功能
require_once 'config/database.php';
require_once 'config/functions.php';

try {
    $db = new Database();
    echo "=== 加班時間整合測試 ===\n";
    
    // 檢查出勤記錄表
    echo "\n1. 檢查出勤記錄:\n";
    $attendance = $db->fetchAll('SELECT a.*, u.name as user_name FROM attendance_records a JOIN users u ON a.user_id = u.id ORDER BY a.date DESC LIMIT 5');
    foreach ($attendance as $record) {
        echo "日期: {$record['date']}, 員工: {$record['user_name']}, 工作時數: {$record['work_hours']}\n";
    }
    
    // 檢查加班記錄表
    echo "\n2. 檢查加班記錄:\n";
    $overtime = $db->fetchAll('SELECT o.*, u.name as user_name FROM overtime_records o JOIN users u ON o.user_id = u.id ORDER BY o.date DESC LIMIT 5');
    foreach ($overtime as $record) {
        $hours = $record['hours'] ?: '未結束';
        echo "日期: {$record['date']}, 員工: {$record['user_name']}, 加班時數: {$hours}, 內容: {$record['work_content']}\n";
    }
    
    // 檢查是否有整合的總工時查詢
    echo "\n3. 測試整合工時查詢:\n";
    $integrationTest = $db->fetchAll("
        SELECT 
            a.date,
            u.name as user_name,
            a.work_hours as base_hours,
            COALESCE(SUM(o.hours), 0) as overtime_hours,
            (a.work_hours + COALESCE(SUM(o.hours), 0)) as total_hours
        FROM attendance_records a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN overtime_records o ON a.user_id = o.user_id AND a.date = o.date AND o.hours IS NOT NULL
        GROUP BY a.id, a.date, u.name, a.work_hours
        ORDER BY a.date DESC
        LIMIT 10
    ");
    
    foreach ($integrationTest as $record) {
        echo "日期: {$record['date']}, 員工: {$record['user_name']}, 基本工時: {$record['base_hours']}, 加班工時: {$record['overtime_hours']}, 總工時: {$record['total_hours']}\n";
    }
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
}
?>
