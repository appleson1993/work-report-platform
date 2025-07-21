# 錯誤修正報告

## 問題描述
1. 系統在存取 `$_POST['action']` 時出現 "Undefined array key" 警告
2. 資料庫表格不存在，出現 "Table 'staf_db.users' doesn't exist" 錯誤
3. 缺少必要的 PHP 擴展 (MySQL PDO, mbstring)

## 修正內容

### 1. 安裝必要的 PHP 擴展
```bash
sudo apt update
sudo apt install php8.1-mysql php8.1-pdo php8.1-mbstring -y
```

### 2. 初始化資料庫
執行 `php init_database.php` 建立所有必要的資料表：
- users (使用者表)
- projects (專案表) 
- tasks (任務表)
- work_reports (工作回報表)

### 3. 新增安全函數 (config/functions.php)
新增了三個安全的 POST 資料存取函數：

```php
// 安全獲取 POST 資料
function getPostValue($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// 安全獲取並清理 POST 資料
function getPostValueSanitized($key, $default = '') {
    return isset($_POST[$key]) ? sanitizeInput($_POST[$key]) : $default;
}

// 安全獲取整數型 POST 資料
function getPostValueInt($key, $default = 0) {
    return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
}
```

### 4. 修正的檔案

#### index.php
- **修正前：** `if ($_POST['action'] === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST')`
- **修正後：** `if ($_SERVER['REQUEST_METHOD'] === 'POST' && getPostValue('action') === 'login')`

- **修正前：** `$email = sanitizeInput($_POST['email']);`
- **修正後：** `$email = getPostValueSanitized('email');`

#### dashboard.php
- **修正前：** `if ($_POST['action'] === 'submit_report')`
- **修正後：** `if (getPostValue('action') === 'submit_report')`

- **修正前：** `$taskId = (int)$_POST['task_id'];`
- **修正後：** `$taskId = getPostValueInt('task_id');`

#### admin.php
- **修正前：** `switch ($_POST['action'])`
- **修正後：** `$action = getPostValue('action'); ... switch ($action)`

- **修正前：** `$name = sanitizeInput($_POST['name']);`
- **修正後：** `$name = getPostValueSanitized('name');`

### 5. 修正的好處

1. **消除警告：** 不再出現 "Undefined array key" 警告
2. **提高安全性：** 所有 POST 資料都經過適當的檢查和清理
3. **程式碼一致性：** 使用統一的函數來處理 POST 資料
4. **易於維護：** 集中的資料存取邏輯，便於日後修改
5. **完整的系統環境：** 安裝所有必要的 PHP 擴展和資料庫

### 6. 使用方式

```php
// 基本用法
$action = getPostValue('action');

// 帶預設值
$status = getPostValue('status', 'pending');

// 自動清理 HTML
$content = getPostValueSanitized('content');

// 整數類型
$userId = getPostValueInt('user_id');
```

## 測試
可以執行以下命令來驗證修正：
- `php test_fix.php` - 測試新的安全函數
- `php system_check.php` - 檢查系統環境
- `php init_database.php` - 重新初始化資料庫（如果需要）

## 結論
1. 所有的 "Undefined array key" 錯誤已經修正
2. 資料庫已成功建立並初始化
3. 所有必要的 PHP 擴展已安裝
4. 系統現在可以安全地處理 POST 請求，無論資料是否存在都不會產生警告
