# 員工打卡系統

這是一個使用 PHP 和 MySQL 開發的簡單員工打卡系統，採用黑色系配色設計。

## 功能特色

### 員工功能
- 🔐 安全登入驗證
- ⏰ 即時時間顯示
- 📍 上班/下班打卡
- 📊 個人出勤統計
- 📋 出勤記錄查詢
- 📱 響應式設計（支援手機和平板）

### 管理員功能
- 👥 員工管理（新增、編輯、刪除）
- 📈 出勤報表查看
- 📊 統計數據分析
- 🔍 多條件篩選搜尋
- 📤 CSV 報表導出
- 🎯 即時監控出勤狀況

### 系統特色
- 🎨 黑色系現代化介面
- 🚀 快速響應的 AJAX 操作
- 🔒 完整的權限控制
- 📱 移動設備友好
- 🛡️ SQL 注入防護
- 🔐 密碼加密存儲

## 系統需求

- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Apache/Nginx 網頁伺服器
- 支援 PDO MySQL 擴展

## 安裝步驟

### 1. 上傳檔案
將所有檔案上傳到您的網站根目錄。

### 2. 資料庫設定
編輯 `config/database.php` 檔案，修改資料庫連接資訊：

```php
$db_config = [
    'host' => 'localhost',
    'dbname' => 'staf_db',
    'username' => 'root',
    'password' => 'Cv#w5I6OE%l4x%0D',
    'charset' => 'utf8mb4'
];
```

### 3. 初始化資料庫
在瀏覽器中訪問：`http://your-domain.com/setup/init_db.php`

這將自動創建必要的資料表並插入預設帳號。

### 4. 預設帳號
系統會自動創建以下帳號：

**管理員帳號**
- 員工編號：`ADMIN001`
- 密碼：`admin123`

**測試員工帳號**
- 員工編號：`EMP001`
- 密碼：`staff123`

## 目錄結構

```
/
├── admin/                    # 管理員後台
│   ├── dashboard.php        # 管理員控制台
│   ├── attendance_report.php # 出勤報表
│   ├── staff_management.php # 員工管理
│   ├── edit_staff.php      # 編輯員工
│   ├── delete_staff.php    # 刪除員工
│   └── export_attendance.php # 導出CSV
├── api/                     # API 接口
│   ├── clock_in.php        # 上班打卡API
│   └── clock_out.php       # 下班打卡API
├── assets/                  # 靜態資源
│   ├── css/
│   │   └── style.css       # 主要樣式檔案
│   └── js/
│       └── main.js         # JavaScript 功能
├── auth/                    # 認證相關
│   ├── login_process.php   # 登入處理
│   └── logout.php          # 登出處理
├── config/                  # 配置檔案
│   └── database.php        # 資料庫配置
├── includes/                # 共用檔案
│   └── functions.php       # 共用函數
├── setup/                   # 安裝檔案
│   └── init_db.php         # 資料庫初始化
├── staff/                   # 員工功能
│   ├── dashboard.php       # 員工控制台
│   └── attendance_history.php # 出勤記錄
└── index.php               # 登入頁面
```

## 資料庫結構

### staff 表（員工資料）
- `id` - 主鍵
- `staff_id` - 員工編號（唯一）
- `name` - 姓名
- `email` - 電子郵件
- `password` - 密碼（加密）
- `department` - 部門
- `position` - 職位
- `is_admin` - 是否為管理員
- `created_at` - 創建時間
- `updated_at` - 更新時間

### attendance 表（出勤記錄）
- `id` - 主鍵
- `staff_id` - 員工編號（外鍵）
- `check_in_time` - 上班時間
- `check_out_time` - 下班時間
- `work_date` - 工作日期
- `total_hours` - 總工作時數
- `status` - 出勤狀態
- `notes` - 備註
- `created_at` - 創建時間
- `updated_at` - 更新時間

## 出勤狀態說明

- `present` - 正常出勤
- `late` - 遲到（超過 09:00 打卡）
- `absent` - 缺席（未打卡）
- `early_leave` - 早退（未滿 8 小時且早於 18:00 下班）

## 使用說明

### 員工使用
1. 使用員工編號和密碼登入
2. 在控制台進行上班打卡
3. 工作結束後進行下班打卡
4. 可查看個人出勤記錄和統計

### 管理員使用
1. 使用管理員帳號登入
2. 在控制台查看整體出勤狀況
3. 在出勤報表中查看詳細記錄
4. 在員工管理中新增/編輯員工
5. 可導出 CSV 報表進行分析

## 安全特性

- 密碼使用 PHP `password_hash()` 加密
- 使用 PDO 預處理語句防止 SQL 注入
- Session 管理和權限控制
- 輸入驗證和 HTML 轉義
- CSRF 保護（表單提交驗證）

## 自訂設定

### 工作時間設定
在 `includes/functions.php` 中可以修改：
- 標準上班時間：預設 09:00
- 標準下班時間：預設 18:00
- 標準工作時數：預設 8 小時

### 樣式自訂
主要樣式檔案位於 `assets/css/style.css`，採用 CSS 變數設計，便於修改配色。

## 技術支援

如果遇到問題，請檢查：
1. PHP 版本是否符合需求
2. MySQL 服務是否正常運行
3. 資料庫連接設定是否正確
4. 檔案權限是否正確設置

## 授權說明

此專案僅供學習和內部使用，請勿用於商業用途。

## 更新日誌

### v1.0.0 (2025-01-22)
- 初始版本發布
- 完整的打卡功能
- 管理員後台
- 報表導出功能
- 響應式設計
