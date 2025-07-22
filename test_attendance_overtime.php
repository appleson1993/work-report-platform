<?php
// 測試加班時數整合查詢
require_once 'config/database.php';
require_once 'config/functions.php';

try {
    $db = new Database();
    echo "=== 測試attendance_admin的加班整合查詢 ===\n";
    
    // 測試主要的出勤查詢（與attendance_admin.php中相同的查詢）
    $month = date('Y-m');
    $whereClause = 'WHERE ar.work_date >= ? AND ar.work_date < ? + INTERVAL 1 MONTH';
    $params = [$month . '-01', $month . '-01'];
    
    $records = $db->fetchAll(
        "SELECT ar.*, u.name as user_name,
                COALESCE(SUM(ot.overtime_hours), 0) as overtime_hours,
                (ar.work_hours + COALESCE(SUM(ot.overtime_hours), 0)) as total_work_hours
         FROM attendance_records ar
         JOIN users u ON ar.user_id = u.id
         LEFT JOIN overtime_records ot ON ar.user_id = ot.user_id 
                                        AND ar.work_date = ot.work_date 
                                        AND ot.overtime_hours IS NOT NULL
                                        AND ot.status = 'ended'
         $whereClause
         GROUP BY ar.id, ar.user_id, ar.work_date, ar.work_hours, u.name
         ORDER BY ar.work_date DESC, u.name",
        $params
    );
    
    echo "\n查詢結果：\n";
    foreach ($records as $record) {
        echo "日期: {$record['work_date']}\n";
        echo "員工: {$record['user_name']}\n";
        echo "基本工時: {$record['work_hours']}\n";
        echo "加班時數: {$record['overtime_hours']}\n";
        echo "總工時: {$record['total_work_hours']}\n";
        echo "---\n";
    }
    
    // 計算統計數據
    $stats = [
        'total_records' => count($records),
        'total_users' => count(array_unique(array_column($records, 'user_id'))),
        'total_hours' => array_sum(array_column($records, 'total_work_hours')),
        'total_overtime' => array_sum(array_column($records, 'overtime_hours')),
    ];
    
    echo "\n統計數據：\n";
    echo "總記錄數: {$stats['total_records']}\n";
    echo "總員工數: {$stats['total_users']}\n";
    echo "總工時: {$stats['total_hours']}\n";
    echo "總加班時數: {$stats['total_overtime']}\n";
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
}
?>
