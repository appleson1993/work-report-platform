<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();

// 模擬用戶提交報告的情況
$taskId = 3;
$userId = 2;
$content = '<p>測試更新報告內容 - ' . date('H:i:s') . '</p>';
$status = 'in_progress';
$isRichText = 1;

echo "=== 測試重複提交報告 ===\n";
echo "任務ID: $taskId\n";
echo "用戶ID: $userId\n";
echo "日期: " . date('Y-m-d') . "\n";
echo "內容: $content\n\n";

try {
    // 嘗試提交報告
    $db->execute(
        'INSERT INTO work_reports (task_id, user_id, report_date, content, status, is_rich_text) 
         VALUES (?, ?, ?, ?, ?, ?) 
         ON DUPLICATE KEY UPDATE 
         content = VALUES(content), 
         status = VALUES(status), 
         is_rich_text = VALUES(is_rich_text), 
         updated_at = NOW()',
        [$taskId, $userId, date('Y-m-d'), $content, $status, $isRichText]
    );
    
    echo "✅ 報告提交成功！\n";
    
    // 檢查結果
    $report = $db->fetch(
        'SELECT * FROM work_reports WHERE task_id = ? AND user_id = ? AND report_date = ?',
        [$taskId, $userId, date('Y-m-d')]
    );
    
    echo "✅ 報告內容已更新:\n";
    echo "  ID: {$report['id']}\n";
    echo "  內容: {$report['content']}\n";
    echo "  更新時間: {$report['updated_at']}\n";
    
} catch (Exception $e) {
    echo "❌ 錯誤: " . $e->getMessage() . "\n";
}
?>
