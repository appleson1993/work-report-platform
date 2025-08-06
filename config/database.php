<?php
// 資料庫配置

//taipei time
date_default_timezone_set('Asia/Taipei');

$db_config = [
    'host' => 'localhost',
    'dbname' => 'staf_db',
    'username' => 'staf_db',
    'password' => '1WTKSist4acVVDvo',
    'charset' => 'utf8mb4'
];

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}", 
        $db_config['username'], 
        $db_config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch(PDOException $e) {
    die("資料庫連接失敗: " . $e->getMessage());
}
?>
