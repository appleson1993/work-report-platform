<?php
require_once __DIR__ . '/../config/database.php';

try {
    // 創建公告表
    $create_announcements_table = "
    CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        type ENUM('info', 'warning', 'urgent', 'success') DEFAULT 'info',
        is_active TINYINT(1) DEFAULT 1,
        start_date DATETIME NOT NULL,
        end_date DATETIME,
        created_by VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active_date (is_active, start_date, end_date),
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($create_announcements_table);
    echo "announcements 表創建成功！<br>";
    
    // 創建公告已讀記錄表
    $create_announcement_reads_table = "
    CREATE TABLE IF NOT EXISTS announcement_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id INT NOT NULL,
        staff_id VARCHAR(20) NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (announcement_id, staff_id),
        INDEX idx_announcement (announcement_id),
        INDEX idx_staff (staff_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($create_announcement_reads_table);
    echo "announcement_reads 表創建成功！<br>";
    
    // 插入示例公告
    $sample_announcement = "
    INSERT INTO announcements (title, content, type, start_date, created_by) VALUES
    ('系統升級通知', '系統將於本週末進行維護升級，屆時可能會有短暫的服務中斷，請大家提前做好準備。', 'warning', NOW(), 'admin'),
    ('歡迎使用新版打卡系統', '新版打卡系統已正式上線，新增了休息時間記錄和公告功能，請大家多多使用！', 'success', NOW(), 'admin')
    ";
    
    $pdo->exec($sample_announcement);
    echo "示例公告插入成功！<br>";
    
    echo "<br>公告功能資料庫設置完成！新功能包括：<br>";
    echo "1. 公告管理系統<br>";
    echo "2. 多種公告類型（資訊、警告、緊急、成功）<br>";
    echo "3. 公告已讀狀態追蹤<br>";
    echo "4. 公告有效期設定<br>";
    
} catch(PDOException $e) {
    echo "錯誤: " . $e->getMessage();
}
?>
