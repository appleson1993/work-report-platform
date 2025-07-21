-- 新增功能的資料庫結構
-- 1. 案子討論區功能
CREATE TABLE IF NOT EXISTS project_discussions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_project_created (project_id, created_at)
);

-- 2. 擴展工作回報表 - 移除每日一次限制，改為支援多次回報
-- 先檢查約束是否存在再刪除
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                         WHERE TABLE_SCHEMA = 'staf_db' 
                         AND TABLE_NAME = 'work_reports' 
                         AND CONSTRAINT_NAME = 'unique_daily_report');

SET @sql = IF(@constraint_exists > 0, 
              'ALTER TABLE work_reports DROP INDEX unique_daily_report', 
              'SELECT "Constraint does not exist" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE work_reports ADD COLUMN IF NOT EXISTS is_rich_text BOOLEAN DEFAULT FALSE;

-- 3. 新增案子分成設定表
CREATE TABLE IF NOT EXISTS project_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    commission_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00, -- 分成百分比
    base_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- 案子基本金額
    bonus_amount DECIMAL(10,2) DEFAULT 0.00, -- 額外獎金
    notes TEXT, -- 備註
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_user (project_id, user_id)
);

-- 4. 新增收入紀錄表
CREATE TABLE IF NOT EXISTS income_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    income_type ENUM('commission', 'bonus', 'adjustment') DEFAULT 'commission',
    description TEXT,
    income_month VARCHAR(7) NOT NULL, -- 格式: YYYY-MM
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_user_month (user_id, income_month)
);
