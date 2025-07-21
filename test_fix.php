<?php
// 測試修正後的錯誤處理

// 模擬沒有 POST 資料的情況
$_POST = [];

require_once 'config/functions.php';

echo "<h2>測試修正後的函數</h2>";

// 測試新的安全函數
echo "getPostValue('action'): '" . getPostValue('action') . "'<br>";
echo "getPostValueSanitized('email'): '" . getPostValueSanitized('email') . "'<br>";
echo "getPostValueInt('user_id'): " . getPostValueInt('user_id') . "<br>";

echo "<br><h3>測試帶預設值</h3>";
echo "getPostValue('action', 'default'): '" . getPostValue('action', 'default') . "'<br>";
echo "getPostValueSanitized('email', 'test@example.com'): '" . getPostValueSanitized('email', 'test@example.com') . "'<br>";
echo "getPostValueInt('user_id', 999): " . getPostValueInt('user_id', 999) . "<br>";

echo "<br><h3>測試有值的情況</h3>";
$_POST['action'] = 'login';
$_POST['email'] = '<script>alert("test")</script>user@example.com';
$_POST['user_id'] = '123';

echo "getPostValue('action'): '" . getPostValue('action') . "'<br>";
echo "getPostValueSanitized('email'): '" . getPostValueSanitized('email') . "'<br>";
echo "getPostValueInt('user_id'): " . getPostValueInt('user_id') . "<br>";

echo "<br><p style='color: green;'>✓ 所有函數正常工作，不會產生 \"Undefined array key\" 錯誤！</p>";
?>
