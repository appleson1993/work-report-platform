<?php
require_once __DIR__ . '/../config/database.php';

try {
    // 修改 attendance 表，新增 UA 和 IP 欄位
    $alter_attendance = "
    ALTER TABLE attendance 
    ADD COLUMN user_agent TEXT AFTER notes,
    ADD COLUMN ip_address VARCHAR(45) AFTER user_agent,
    ADD COLUMN break_start_time DATETIME AFTER ip_address,
    ADD COLUMN break_end_time DATETIME AFTER break_start_time,
    ADD COLUMN total_break_minutes INT DEFAULT 0 AFTER break_end_time
    ";
    
    $pdo->exec($alter_attendance);
    echo "attendance 表欄位新增成功！<br>";
    
    // 創建休息記錄表
    $create_breaks_table = "
    CREATE TABLE IF NOT EXISTS break_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attendance_id INT NOT NULL,
        staff_id VARCHAR(20) NOT NULL,
        break_start_time DATETIME NOT NULL,
        break_end_time DATETIME,
        break_minutes INT DEFAULT 0,
        break_type ENUM('lunch', 'coffee', 'personal', 'other') DEFAULT 'other',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
    )";
    
    $pdo->exec($create_breaks_table);
    echo "break_records 表創建成功！<br>";
    
    echo "<br>資料庫更新完成！新增功能：<br>";
    echo "1. 記錄簽到時的 User Agent 和 IP 地址<br>";
    echo "2. 支援中途休息功能<br>";
    echo "3. 休息記錄詳細追蹤<br>";
    
} catch(PDOException $e) {
    echo "錯誤: " . $e->getMessage();
}
?>
