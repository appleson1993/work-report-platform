<?php
// 資料庫初始化腳本
// 執行此腳本來建立資料庫結構和初始資料

require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getPdo();
    
    echo "開始初始化資料庫...\n";
    
    // 建立使用者表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ 使用者表建立完成\n";
    
    // 建立專案表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ 專案表建立完成\n";
    
    // 建立任務表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            assigned_user_id INT NOT NULL,
            project_id INT,
            status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            due_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ 任務表建立完成\n";
    
    // 建立工作回報表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS work_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            user_id INT NOT NULL,
            report_date DATE NOT NULL,
            content TEXT NOT NULL,
            status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_daily_report (task_id, user_id, report_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ 工作回報表建立完成\n";
    
    // 檢查是否已有管理員
    $adminExists = $db->fetch('SELECT id FROM users WHERE role = "admin"');
    
    if (!$adminExists) {
        // 插入預設管理員帳號 (密碼: admin123)
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $db->execute(
            'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
            ['系統管理員', 'admin@worklog.com', $adminPassword, 'admin']
        );
        echo "✓ 預設管理員帳號建立完成 (admin@worklog.com / admin123)\n";
    } else {
        echo "✓ 管理員帳號已存在\n";
    }
    
    // 檢查是否已有範例專案
    $projectExists = $db->fetch('SELECT id FROM projects LIMIT 1');
    
    if (!$projectExists) {
        // 插入範例專案
        $db->execute(
            'INSERT INTO projects (name, description) VALUES (?, ?)',
            ['網站開發專案', '公司官方網站重新設計與開發']
        );
        $db->execute(
            'INSERT INTO projects (name, description) VALUES (?, ?)',
            ['行銷活動', '2025年度行銷推廣活動']
        );
        echo "✓ 範例專案建立完成\n";
    } else {
        echo "✓ 專案資料已存在\n";
    }
    
    echo "\n資料庫初始化完成！\n";
    echo "可以開始使用系統了。\n";
    echo "管理員登入：admin@worklog.com / admin123\n";
    
} catch (Exception $e) {
    echo "初始化失敗：" . $e->getMessage() . "\n";
}
?>
