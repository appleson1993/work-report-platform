-- 員工薪資管理系統數據庫結構

-- 薪資類別表
CREATE TABLE IF NOT EXISTS salary_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL COMMENT '類別名稱',
    description TEXT COMMENT '類別描述',
    color VARCHAR(7) DEFAULT '#007bff' COMMENT '顯示顏色',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT='薪資類別表';

-- 薪資記錄表
CREATE TABLE IF NOT EXISTS salary_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id VARCHAR(20) NOT NULL COMMENT '員工編號',
    category_id INT COMMENT '薪資類別ID',
    project_name VARCHAR(200) NOT NULL COMMENT '項目名稱',
    amount DECIMAL(10,2) NOT NULL COMMENT '金額',
    record_date DATE NOT NULL COMMENT '記錄日期',
    description TEXT COMMENT '詳細描述',
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending' COMMENT '狀態',
    created_by VARCHAR(20) COMMENT '創建者員工編號',
    approved_by VARCHAR(20) COMMENT '批准者員工編號',
    approved_at TIMESTAMP NULL COMMENT '批准時間',
    paid_at TIMESTAMP NULL COMMENT '支付時間',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES salary_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    
    INDEX idx_staff_date (staff_id, record_date),
    INDEX idx_date (record_date),
    INDEX idx_status (status)
) COMMENT='薪資記錄表';

-- 薪資統計表（每月統計）
CREATE TABLE IF NOT EXISTS salary_monthly_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id VARCHAR(20) NOT NULL COMMENT '員工編號',
    year INT NOT NULL COMMENT '年份',
    month INT NOT NULL COMMENT '月份',
    total_amount DECIMAL(12,2) DEFAULT 0 COMMENT '總金額',
    total_records INT DEFAULT 0 COMMENT '總記錄數',
    avg_amount DECIMAL(10,2) DEFAULT 0 COMMENT '平均金額',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    UNIQUE KEY unique_staff_month (staff_id, year, month),
    INDEX idx_year_month (year, month)
) COMMENT='薪資月度統計表';

-- 插入預設薪資類別
INSERT INTO salary_categories (name, description, color) VALUES
('專案獎金', '完成特定專案的獎勵金', '#28a745'),
('業績獎金', '達成業績目標的獎勵', '#ffc107'),
('加班費', '超時工作的加班費用', '#17a2b8'),
('技能津貼', '技能提升或認證的津貼', '#6f42c1'),
('年終獎金', '年度績效獎金', '#dc3545'),
('其他收入', '其他類型的收入', '#6c757d')
ON DUPLICATE KEY UPDATE name=VALUES(name);
