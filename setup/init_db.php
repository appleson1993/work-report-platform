<?php
require_once '../config/database.php';

// 創建員工表
$create_staff_table = "
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(100),
    position VARCHAR(100),
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// 創建打卡記錄表
$create_attendance_table = "
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(20) NOT NULL,
    check_in_time DATETIME,
    check_out_time DATETIME,
    work_date DATE NOT NULL,
    total_hours DECIMAL(4,2) DEFAULT 0,
    status ENUM('present', 'absent', 'late', 'early_leave') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
)";

try {
    $pdo->exec($create_staff_table);
    $pdo->exec($create_attendance_table);
    
    // 創建預設管理員帳號
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $insert_admin = "INSERT IGNORE INTO staff (staff_id, name, email, password, department, position, is_admin) 
                     VALUES ('ADMIN001', '系統管理員', 'admin@company.com', ?, 'IT', '系統管理員', 1)";
    $stmt = $pdo->prepare($insert_admin);
    $stmt->execute([$admin_password]);
    
    // 創建測試員工帳號
    $staff_password = password_hash('staff123', PASSWORD_DEFAULT);
    $insert_staff = "INSERT IGNORE INTO staff (staff_id, name, email, password, department, position) 
                     VALUES ('EMP001', '測試員工', 'staff@company.com', ?, '業務部', '業務員')";
    $stmt = $pdo->prepare($insert_staff);
    $stmt->execute([$staff_password]);
    
    echo "資料庫初始化完成！<br>";
    echo "管理員帳號: ADMIN001 / admin123<br>";
    echo "測試員工帳號: EMP001 / staff123<br>";
    
} catch(PDOException $e) {
    echo "錯誤: " . $e->getMessage();
}
?>
