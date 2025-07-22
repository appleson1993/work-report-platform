<?php
// 測試多用戶任務分配功能
require_once 'config/database.php';
require_once 'config/functions.php';

try {
    $db = new Database();
    echo "Database connection: OK\n";
    
    // 測試任務查詢
    $tasks = $db->fetchAll('SELECT t.*, p.name as project_name,
     CASE 
         WHEN t.assign_to_all = 1 THEN "全體員工"
         ELSE GROUP_CONCAT(u.name SEPARATOR ", ")
     END as assigned_users
     FROM tasks t 
     LEFT JOIN projects p ON t.project_id = p.id 
     LEFT JOIN task_assignments ta ON t.id = ta.task_id
     LEFT JOIN users u ON ta.user_id = u.id
     GROUP BY t.id
     ORDER BY t.created_at DESC');
    
    echo "Task query: OK\n";
    echo "Found " . count($tasks) . " tasks\n";
    
    foreach ($tasks as $task) {
        echo "Task: " . $task['title'] . " -> " . $task['assigned_users'] . "\n";
    }
    
    // 測試用戶查詢
    $users = $db->fetchAll('SELECT id, name, email FROM users WHERE role = "user"');
    echo "\nFound " . count($users) . " users:\n";
    foreach ($users as $user) {
        echo "User: " . $user['name'] . " (" . $user['email'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
