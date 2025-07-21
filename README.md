# WorkLog Manager - 工作進度回報管理系統

## 📋 系統概述

WorkLog Manager 是一個基於 PHP 的工作進度回報管理系統，專為中小型企業設計，幫助管理員追蹤員工工作進度，並讓員工便於回報每日工作狀況。

## 🛠 技術架構

- **前端：** HTML5 + Bootstrap 5 + SweetAlert2
- **後端：** PHP 8+ (無框架)
- **資料庫：** MySQL 5.7+/MariaDB
- **Session：** PHP 內建 Session 管理
- **安全性：** PDO 防 SQL injection、bcrypt 密碼加密

## 📁 檔案結構

```
public_html/
├── config/
│   ├── database.php      # 資料庫連線設定
│   └── functions.php     # 共用函數和工具
├── index.php             # 登入/註冊頁面
├── dashboard.php         # 員工面板
├── admin.php            # 管理員後台
├── logout.php           # 登出處理
├── init_database.php    # 資料庫初始化腳本
├── database.sql         # 資料庫結構 SQL
└── README.md           # 說明文件
```

## 🚀 安裝步驟

### 1. 環境需求
- PHP 8.0 以上
- MySQL 5.7 以上或 MariaDB
- Web 伺服器 (Apache/Nginx)

### 2. 資料庫設定
1. 建立資料庫 `staf_db`
2. 執行初始化腳本：
   ```bash
   php init_database.php
   ```
   或直接匯入 `database.sql`

### 3. 設定檔案
在 `config/database.php` 中設定您的資料庫連線資訊：
```php
private $host = 'localhost';
private $dbname = 'staf_db';
private $username = 'staf_db';
private $password = 'vJ@B5xKBxUxsc45o';
```

### 4. 權限設定
確保 web 伺服器對專案目錄有適當的讀寫權限。

## 👥 使用者角色

### 一般員工 (user)
- 註冊帳號
- 查看指派給自己的任務
- 填寫每日工作回報
- 查看自己的歷史回報
- 編輯個人資料

### 管理員 (admin)
- 管理使用者身分和角色
- 建立和管理專案
- 建立和指派任務
- 查看所有員工的工作回報
- 產生工作報表

## 🔑 預設帳號

**管理員帳號：**
- Email: admin@worklog.com
- 密碼: admin123

## 📱 主要功能

### 1. 員工面板 (dashboard.php)
- **任務檢視：** 顯示指派給該員工的所有任務
- **狀態管理：** 未開始、進行中、已完成
- **每日回報：** 限制每日一筆回報，可編輯當日內容
- **歷史紀錄：** 查看過往所有回報紀錄
- **統計資訊：** 任務完成度統計

### 2. 管理員後台 (admin.php)
- **使用者管理：** 查看所有使用者，切換角色權限
- **專案管理：** 建立、編輯專案資訊
- **任務管理：** 建立任務並指派給員工
- **回報查看：** 多條件篩選查看所有工作回報
- **統計儀表板：** 系統整體使用狀況

### 3. 登入/註冊系統 (index.php)
- **安全登入：** bcrypt 密碼加密
- **使用者註冊：** Email 唯一性驗證
- **表單驗證：** 前端和後端雙重驗證
- **友善介面：** 響應式設計，支援各種裝置

## 🔒 安全特性

- **SQL Injection 防護：** 使用 PDO 預處理語句
- **密碼安全：** bcrypt 雜湊加密
- **Session 管理：** 安全的會話控制
- **權限檢查：** 頁面層級的權限驗證
- **輸入清理：** 防止 XSS 攻擊

## 📊 資料庫設計

### 主要資料表

1. **users** - 使用者資料
   - id, name, email, password, role, created_at, updated_at

2. **projects** - 專案資料
   - id, name, description, created_at, updated_at

3. **tasks** - 任務資料
   - id, title, description, assigned_user_id, project_id, status, due_date, created_at, updated_at

4. **work_reports** - 工作回報
   - id, task_id, user_id, report_date, content, status, created_at, updated_at

## 🎨 前端特色

- **Bootstrap 5：** 現代化響應式設計
- **SweetAlert2：** 美觀的提示對話框
- **Font Awesome：** 豐富的圖示庫
- **漸層設計：** 現代化的視覺效果
- **卡片佈局：** 清晰的資訊組織

## 🔧 客製化

### 新增狀態類型
在 `config/functions.php` 中修改 `getStatusText()` 和 `getStatusBadge()` 函數。

### 修改樣式
所有樣式都使用內聯 CSS，可直接在各檔案中修改。

### 新增功能
遵循現有的 MVC 模式和 AJAX 處理方式來擴展功能。

## 🐛 常見問題

### Q: 無法連接資料庫
A: 檢查 `config/database.php` 中的連線設定是否正確。

### Q: 登入後出現空白頁面
A: 檢查 PHP 錯誤日誌，確認 Session 功能正常。

### Q: 無法上傳或提交表單
A: 檢查 PHP 設定中的 `post_max_size` 和 `upload_max_filesize`。

## 📞 技術支援

如遇到問題，請檢查：
1. PHP 版本是否符合需求
2. 資料庫連線是否正常
3. 檔案權限是否正確設定
4. Web 伺服器錯誤日誌

## 📄 授權

此專案為內部使用系統，請勿用於商業用途。
