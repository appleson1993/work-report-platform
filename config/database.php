<?php
// 資料庫連線設定
class Database {
    private $host = 'localhost';
    private $dbname = 'staf_db';
    private $username = 'staf_db';
    private $password = 'vJ@B5xKBxUxsc45o';
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            die('資料庫連線失敗: ' . $e->getMessage());
        }
    }

    public function getPdo() {
        return $this->pdo;
    }

    // 通用查詢方法
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('查詢錯誤: ' . $e->getMessage());
        }
    }

    // 獲取單筆資料
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    // 獲取多筆資料
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    // 執行插入/更新/刪除
    public function execute($sql, $params = []) {
        return $this->query($sql, $params);
    }

    // 獲取最後插入的ID
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}
?>
