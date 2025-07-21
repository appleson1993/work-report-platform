<?php
require_once 'config/database.php';

// 測試多筆回報功能
$db = new Database();

try {
    // 使用現有的任務 ID
    $taskId = 5;  // 使用實際存在的任務 ID
    $userId = 1;
    $reportDate = date('Y-m-d');
    
    echo "測試多筆回報功能...\n\n";
    
    // 提交第一筆回報
    echo "提交第一筆回報...\n";
    $db->execute(
        'INSERT INTO work_reports (task_id, user_id, report_date, content, status, is_rich_text, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$taskId, $userId, $reportDate, '第一筆測試回報 - 開始工作', 'in_progress', 0]
    );
    echo "第一筆回報提交成功！\n\n";
    
    // 稍等一下確保時間戳不同
    sleep(1);
    
    // 提交第二筆回報
    echo "提交第二筆回報...\n";
    $db->execute(
        'INSERT INTO work_reports (task_id, user_id, report_date, content, status, is_rich_text, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$taskId, $userId, $reportDate, '第二筆測試回報 - 進度更新', 'in_progress', 0]
    );
    echo "第二筆回報提交成功！\n\n";
    
    // 稍等一下確保時間戳不同
    sleep(1);
    
    // 提交第三筆回報
    echo "提交第三筆回報...\n";
    $db->execute(
        'INSERT INTO work_reports (task_id, user_id, report_date, content, status, is_rich_text, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$taskId, $userId, $reportDate, '第三筆測試回報 - 工作完成', 'completed', 0]
    );
    echo "第三筆回報提交成功！\n\n";
    
    // 查詢今天的所有回報
    echo "查詢今天同一任務的所有回報：\n";
    $reports = $db->fetchAll(
        'SELECT id, content, status, created_at FROM work_reports 
         WHERE task_id = ? AND user_id = ? AND report_date = ? 
         ORDER BY created_at DESC',
        [$taskId, $userId, $reportDate]
    );
    
    foreach ($reports as $index => $report) {
        echo ($index + 1) . ". ID: {$report['id']}, 狀態: {$report['status']}, 時間: {$report['created_at']}\n";
        echo "   內容: {$report['content']}\n\n";
    }
    
    echo "測試完成！成功提交了 " . count($reports) . " 筆回報記錄。\n";
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
}
?>
