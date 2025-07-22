<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// 模擬富文本提交
$_POST['ajax'] = '1';
$_POST['action'] = 'submit_report';
$_POST['task_id'] = '5';
$_POST['content'] = '<p><strong>測試富文本內容</strong></p><ul><li>項目 1</li><li>項目 2</li></ul>';
$_POST['status'] = 'in_progress';
$_POST['is_rich_text'] = 'true';

// 模擬 session
session_start();
$_SESSION['user_id'] = 2;

$db = new Database();

echo "=== 測試富文本報告提交 ===\n";
echo "任務ID: {$_POST['task_id']}\n";
echo "內容: {$_POST['content']}\n";
echo "是否富文本: {$_POST['is_rich_text']}\n\n";

try {
    $action = getPostValue('action');
    $taskId = getPostValueInt('task_id');
    $content = getPostValue('content');
    $status = getPostValueSanitized('status');
    $isRichText = getPostValueBool('is_rich_text');
    $userId = $_SESSION['user_id'];
    
    echo "處理後的數據:\n";
    echo "  任務ID: $taskId\n";
    echo "  用戶ID: $userId\n";
    echo "  狀態: $status\n";
    echo "  富文本標記: $isRichText\n";
    
    // 對於富文本，檢查去除HTML標籤後的內容；對於純文本，直接檢查
    $contentToCheck = $isRichText ? strip_tags($content) : trim($content);
    echo "  檢查內容: '$contentToCheck'\n";
    
    if (empty($contentToCheck)) {
        throw new Exception('請填寫工作內容');
    }
    
    // 使用 ON DUPLICATE KEY UPDATE 來處理重複報告
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
    
    echo "\n✅ 富文本報告提交成功！\n";
    
    // 檢查結果
    $report = $db->fetch(
        'SELECT * FROM work_reports WHERE task_id = ? AND user_id = ? AND report_date = ?',
        [$taskId, $userId, date('Y-m-d')]
    );
    
    echo "\n📋 儲存的報告內容:\n";
    echo "  ID: {$report['id']}\n";
    echo "  內容: {$report['content']}\n";
    echo "  富文本: " . ($report['is_rich_text'] ? '是' : '否') . "\n";
    echo "  更新時間: {$report['updated_at']}\n";
    
} catch (Exception $e) {
    echo "❌ 錯誤: " . $e->getMessage() . "\n";
}
?>
