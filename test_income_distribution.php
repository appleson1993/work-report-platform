<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();

// 模擬新增專案收入
$projectId = 6; // 專案 "123"
$totalAmount = 10000; // 總收入 10,000 元
$incomeMonth = '2025-07';
$description = '測試專案收入分配';

echo "=== 開始分配專案收入 ===\n";
echo "專案ID: $projectId\n";
echo "總收入: $totalAmount\n";
echo "月份: $incomeMonth\n";

// 獲取該專案的分成設定
$commissions = $db->fetchAll(
    'SELECT * FROM project_commissions WHERE project_id = ?',
    [$projectId]
);

if (empty($commissions)) {
    echo "錯誤: 該專案尚未設定分成比例\n";
    exit;
}

echo "找到 " . count($commissions) . " 項分成設定\n\n";

// 為每個有分成的員工計算並新增收入紀錄
foreach ($commissions as $commission) {
    $commissionAmount = ($totalAmount * $commission['commission_percentage'] / 100) + $commission['base_amount'] + $commission['bonus_amount'];
    
    echo "員工ID: {$commission['user_id']}\n";
    echo "  比例: {$commission['commission_percentage']}%\n";
    echo "  基本金額: {$commission['base_amount']}\n"; 
    echo "  獎金: {$commission['bonus_amount']}\n";
    echo "  計算: ($totalAmount * {$commission['commission_percentage']} / 100) + {$commission['base_amount']} + {$commission['bonus_amount']} = $commissionAmount\n";
    
    $db->execute(
        'INSERT INTO income_records (user_id, project_id, income_type, amount, income_month, description) VALUES (?, ?, ?, ?, ?, ?)',
        [$commission['user_id'], $projectId, 'commission', $commissionAmount, $incomeMonth, $description]
    );
    
    echo "  ✓ 已新增收入紀錄\n\n";
}

echo "=== 分配完成 ===\n";

// 檢查結果
echo "=== 檢查新增的收入紀錄 ===\n";
$newIncomes = $db->fetchAll(
    'SELECT ir.*, u.name as user_name, p.name as project_name
     FROM income_records ir
     JOIN users u ON ir.user_id = u.id
     JOIN projects p ON ir.project_id = p.id
     WHERE ir.income_month = ?
     ORDER BY ir.created_at DESC',
    [$incomeMonth]
);

foreach ($newIncomes as $income) {
    echo "專案: {$income['project_name']}, 員工: {$income['user_name']}, 金額: {$income['amount']}, 說明: {$income['description']}\n";
}
?>
