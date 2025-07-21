<?php
require_once 'config/database.php';

$db = new Database();

// 檢查現有的分成設定
echo "=== 檢查分成設定 ===\n";
$commissions = $db->fetchAll(
    'SELECT pc.*, u.name as user_name, p.name as project_name 
     FROM project_commissions pc
     JOIN users u ON pc.user_id = u.id
     JOIN projects p ON pc.project_id = p.id'
);

if (empty($commissions)) {
    echo "沒有設定分成\n";
} else {
    foreach ($commissions as $c) {
        echo "專案: {$c['project_name']}, 員工: {$c['user_name']}, 比例: {$c['commission_percentage']}%, 基本: {$c['base_amount']}, 獎金: {$c['bonus_amount']}\n";
    }
}

echo "\n=== 檢查收入紀錄 ===\n";
$incomes = $db->fetchAll(
    'SELECT ir.*, u.name as user_name, p.name as project_name
     FROM income_records ir
     JOIN users u ON ir.user_id = u.id
     JOIN projects p ON ir.project_id = p.id
     ORDER BY ir.created_at DESC
     LIMIT 10'
);

if (empty($incomes)) {
    echo "沒有收入紀錄\n";
} else {
    foreach ($incomes as $income) {
        echo "專案: {$income['project_name']}, 員工: {$income['user_name']}, 類型: {$income['income_type']}, 金額: {$income['amount']}, 月份: {$income['income_month']}\n";
    }
}

echo "\n=== 可用專案列表 ===\n";
$projects = $db->fetchAll('SELECT id, name FROM projects');
foreach ($projects as $project) {
    echo "ID: {$project['id']}, 名稱: {$project['name']}\n";
}
?>
