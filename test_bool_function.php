<?php
require_once 'config/functions.php';

// 模擬不同的POST值來測試getPostValueBool函數
$test_cases = [
    'true' => 'true',
    'false' => 'false',
    '1' => '1',
    '0' => '0',
    'empty' => '',
    'null' => null
];

echo "測試getPostValueBool函數:\n\n";

foreach ($test_cases as $name => $value) {
    $_POST['test_field'] = $value;
    $result = getPostValueBool('test_field');
    echo "輸入: '$value' ($name) => 輸出: $result\n";
}

// 測試未設置的欄位
unset($_POST['test_field']);
$result = getPostValueBool('test_field', 0);
echo "未設置欄位，default=0 => 輸出: $result\n";

$result = getPostValueBool('test_field', 1);
echo "未設置欄位，default=1 => 輸出: $result\n";

echo "\n測試完成！\n";
?>
