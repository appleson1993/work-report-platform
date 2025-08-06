<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// 檢查是否請求導出
if (!isset($_GET['export']) || $_GET['export'] !== 'csv') {
    header('Location: attendance_report.php');
    exit;
}

// 處理查詢參數（與報表頁面相同）
$staff_id = $_GET['staff_id'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$month = $_GET['month'] ?? date('Y-m');

// 建立查詢條件
$where_conditions = ["1=1"];
$params = [];

if ($staff_id) {
    $where_conditions[] = "a.staff_id = ?";
    $params[] = $staff_id;
}

if ($department) {
    $where_conditions[] = "s.department = ?";
    $params[] = $department;
}

if ($status) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status;
}

if ($start_date) {
    $where_conditions[] = "a.work_date >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $where_conditions[] = "a.work_date <= ?";
    $params[] = $end_date;
}

if ($month && !$start_date && !$end_date) {
    $where_conditions[] = "DATE_FORMAT(a.work_date, '%Y-%m') = ?";
    $params[] = $month;
}

$where_clause = implode(' AND ', $where_conditions);

// 取得所有記錄（不分頁）
$query = "
    SELECT 
        a.work_date,
        a.staff_id,
        s.name,
        s.department,
        s.position,
        a.check_in_time,
        a.check_out_time,
        a.total_hours,
        a.status,
        a.notes
    FROM attendance a
    JOIN staff s ON a.staff_id = s.staff_id
    WHERE $where_clause
    ORDER BY a.work_date DESC, s.department, s.name
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // 設置CSV檔案標頭
    $filename = '出勤報表_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // 輸出UTF-8 BOM，確保Excel正確顯示中文
    echo "\xEF\xBB\xBF";
    
    // 開啟輸出緩衝區
    $output = fopen('php://output', 'w');
    
    // CSV標題行
    $headers = [
        '日期',
        '員工編號',
        '姓名',
        '部門',
        '職位',
        '上班時間',
        '下班時間',
        '工作時數',
        '狀態',
        '備註'
    ];
    fputcsv($output, $headers);
    
    // 輸出資料行
    foreach ($records as $record) {
        $row = [
            $record['work_date'],
            $record['staff_id'],
            $record['name'],
            $record['department'],
            $record['position'],
            $record['check_in_time'] ? date('H:i:s', strtotime($record['check_in_time'])) : '',
            $record['check_out_time'] ? date('H:i:s', strtotime($record['check_out_time'])) : '',
            $record['total_hours'] ?: '0',
            getStatusText($record['status']),
            $record['notes'] ?: ''
        ];
        fputcsv($output, $row);
    }
    
    // 添加統計摘要
    fputcsv($output, []); // 空行
    fputcsv($output, ['統計摘要']);
    fputcsv($output, ['總記錄數', count($records)]);
    
    if ($records) {
        $present_count = count(array_filter($records, fn($r) => $r['status'] === 'present'));
        $late_count = count(array_filter($records, fn($r) => $r['status'] === 'late'));
        $absent_count = count(array_filter($records, fn($r) => $r['status'] === 'absent'));
        $early_leave_count = count(array_filter($records, fn($r) => $r['status'] === 'early_leave'));
        
        $total_hours = array_sum(array_column($records, 'total_hours'));
        $avg_hours = count($records) > 0 ? round($total_hours / count($records), 2) : 0;
        
        fputcsv($output, ['正常出勤', $present_count]);
        fputcsv($output, ['遲到次數', $late_count]);
        fputcsv($output, ['缺席次數', $absent_count]);
        fputcsv($output, ['早退次數', $early_leave_count]);
        fputcsv($output, ['總工作時數', $total_hours]);
        fputcsv($output, ['平均工作時數', $avg_hours]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['導出時間', date('Y-m-d H:i:s')]);
    
    // 篩選條件摘要
    if ($staff_id || $department || $status || $start_date || $end_date || $month) {
        fputcsv($output, []);
        fputcsv($output, ['篩選條件']);
        if ($staff_id) fputcsv($output, ['員工編號', $staff_id]);
        if ($department) fputcsv($output, ['部門', $department]);
        if ($status) fputcsv($output, ['狀態', getStatusText($status)]);
        if ($start_date) fputcsv($output, ['開始日期', $start_date]);
        if ($end_date) fputcsv($output, ['結束日期', $end_date]);
        if ($month && !$start_date && !$end_date) fputcsv($output, ['月份', $month]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    $_SESSION['error_message'] = '導出失敗，請重試';
    header('Location: attendance_report.php');
    exit;
}
?>
