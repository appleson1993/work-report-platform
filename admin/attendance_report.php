<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
$current_user = getCurrentUser();

// 處理查詢參數
$staff_id = $_GET['staff_id'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$month = $_GET['month'] ?? date('Y-m');

$limit = 50;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

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

// 取得部門列表
$dept_stmt = $pdo->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL ORDER BY department");
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// 取得員工列表
$staff_stmt = $pdo->query("SELECT staff_id, name FROM staff WHERE is_admin = 0 ORDER BY name");
$staff_list = $staff_stmt->fetchAll();

// 計算總記錄數
$count_query = "
    SELECT COUNT(*) as total 
    FROM attendance a 
    JOIN staff s ON a.staff_id = s.staff_id 
    WHERE $where_clause
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// 取得記錄
$query = "
    SELECT 
        a.*,
        s.name,
        s.department,
        s.position
    FROM attendance a
    JOIN staff s ON a.staff_id = s.staff_id
    WHERE $where_clause
    ORDER BY a.work_date DESC, a.check_in_time DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// 取得統計數據
$stats_query = "
    SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT a.staff_id) as staff_count,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'early_leave' THEN 1 ELSE 0 END) as early_leave_count,
        AVG(a.total_hours) as avg_hours,
        SUM(a.total_hours) as total_hours
    FROM attendance a
    JOIN staff s ON a.staff_id = s.staff_id
    WHERE $where_clause
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute($params);
$statistics = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>出勤報表 - 管理後台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- 導航欄 -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">員工打卡系統 - 管理後台</a>
            <div class="nav-links">
                <a href="dashboard.php">控制台</a>
                <a href="attendance_report.php">出勤報表</a>
                <a href="staff_management.php">員工管理</a>
                                <a href="announcements.php">公告管理</a>

                <span style="color: #ccc;">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">出勤報表</h1>
            </div>
            
            <!-- 篩選器 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">篩選條件</h2>
                
                <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="staff_id">員工</label>
                        <select id="staff_id" name="staff_id" class="form-input">
                            <option value="">所有員工</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?= escape($staff['staff_id']) ?>" 
                                        <?= $staff_id === $staff['staff_id'] ? 'selected' : '' ?>>
                                    <?= escape($staff['name']) ?> (<?= escape($staff['staff_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="department">部門</label>
                        <select id="department" name="department" class="form-input">
                            <option value="">所有部門</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= escape($dept) ?>" 
                                        <?= $department === $dept ? 'selected' : '' ?>>
                                    <?= escape($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="status">狀態</label>
                        <select id="status" name="status" class="form-input">
                            <option value="">所有狀態</option>
                            <option value="present" <?= $status === 'present' ? 'selected' : '' ?>>正常</option>
                            <option value="late" <?= $status === 'late' ? 'selected' : '' ?>>遲到</option>
                            <option value="absent" <?= $status === 'absent' ? 'selected' : '' ?>>缺席</option>
                            <option value="early_leave" <?= $status === 'early_leave' ? 'selected' : '' ?>>早退</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="month">月份</label>
                        <input type="month" 
                               id="month" 
                               name="month" 
                               class="form-input" 
                               value="<?= escape($month) ?>">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="start_date">開始日期</label>
                        <input type="date" 
                               id="start_date" 
                               name="start_date" 
                               class="form-input" 
                               value="<?= escape($start_date) ?>">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="end_date">結束日期</label>
                        <input type="date" 
                               id="end_date" 
                               name="end_date" 
                               class="form-input" 
                               value="<?= escape($end_date) ?>">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary">篩選</button>
                        <a href="attendance_report.php" class="btn btn-primary">重置</a>
                    </div>
                </form>
            </div>
            
            <!-- 統計概覽 -->
            <?php if ($statistics && $statistics['total_records'] > 0): ?>
                <div class="card">
                    <h2 style="color: #fff; margin-bottom: 1rem;">統計概覽</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= $statistics['total_records'] ?></div>
                            <div class="stat-label">總記錄數</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $statistics['staff_count'] ?></div>
                            <div class="stat-label">涉及員工數</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $statistics['present_count'] ?></div>
                            <div class="stat-label">正常出勤</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $statistics['late_count'] ?></div>
                            <div class="stat-label">遲到次數</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $statistics['early_leave_count'] ?></div>
                            <div class="stat-label">早退次數</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= round($statistics['avg_hours'] ?: 0, 1) ?></div>
                            <div class="stat-label">平均工時</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 記錄列表 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">
                    出勤記錄 
                    <?php if ($total_records > 0): ?>
                        <span style="font-size: 1rem; color: #ccc;">(共 <?= $total_records ?> 筆)</span>
                    <?php endif; ?>
                </h2>
                
                <?php if ($records): ?>
                    <div style="margin-bottom: 1rem;">
                        <input type="text" 
                               id="search-input" 
                               class="form-input" 
                               placeholder="搜尋員工姓名、部門..."
                               style="max-width: 300px;">
                    </div>
                    
                    <table class="table" id="data-table">
                        <thead>
                            <tr>
                                <th onclick="sortTable(0)">日期</th>
                                <th onclick="sortTable(1)">員工編號</th>
                                <th onclick="sortTable(2)">姓名</th>
                                <th onclick="sortTable(3)">部門</th>
                                <th onclick="sortTable(4)">上班時間</th>
                                <th onclick="sortTable(5)">下班時間</th>
                                <th onclick="sortTable(6)">工時</th>
                                <th onclick="sortTable(7)">休息</th>
                                <th onclick="sortTable(8)">狀態</th>
                                <th>IP地址</th>
                                <th>備註</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?= formatDate($record['work_date']) ?></td>
                                    <td><?= escape($record['staff_id']) ?></td>
                                    <td><?= escape($record['name']) ?></td>
                                    <td><?= escape($record['department']) ?></td>
                                    <td><?= $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-' ?></td>
                                    <td><?= $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-' ?></td>
                                    <td><?= $record['total_hours'] ?: '0' ?></td>
                                    <td><?= $record['total_break_minutes'] ? formatBreakTime($record['total_break_minutes']) : '-' ?></td>
                                    <td>
                                        <span class="status <?= getStatusClass($record['status']) ?>">
                                            <?= getStatusText($record['status']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: #ccc;" title="<?= escape($record['user_agent'] ?: '') ?>">
                                        <?= escape($record['ip_address'] ?: '-') ?>
                                    </td>
                                    <td><?= escape($record['notes'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- 分頁 -->
                    <?php if ($total_pages > 1): ?>
                        <div class="text-center mt-3">
                            <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                <?php if ($page > 1): ?>
                                    <?php
                                    $prev_params = $_GET;
                                    $prev_params['page'] = $page - 1;
                                    $prev_url = '?' . http_build_query($prev_params);
                                    ?>
                                    <a href="<?= escape($prev_url) ?>" class="btn btn-primary btn-sm">上一頁</a>
                                <?php endif; ?>
                                
                                <span style="color: #ccc; padding: 0 1rem;">
                                    第 <?= $page ?> 頁，共 <?= $total_pages ?> 頁
                                </span>
                                
                                <?php if ($page < $total_pages): ?>
                                    <?php
                                    $next_params = $_GET;
                                    $next_params['page'] = $page + 1;
                                    $next_url = '?' . http_build_query($next_params);
                                    ?>
                                    <a href="<?= escape($next_url) ?>" class="btn btn-primary btn-sm">下一頁</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center">
                        <p style="color: #ccc;">暫無符合條件的記錄</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 導出功能 -->
            <?php if ($records): ?>
                <div class="card">
                    <h2 style="color: #fff; margin-bottom: 1rem;">導出報表</h2>
                    <div class="text-center">
                        <p style="color: #ccc; margin-bottom: 1rem;">
                            將當前篩選條件的報表導出為 CSV 檔案
                        </p>
                        <?php
                        $export_params = $_GET;
                        $export_params['export'] = 'csv';
                        $export_url = 'export_attendance.php?' . http_build_query($export_params);
                        ?>
                        <a href="<?= escape($export_url) ?>" class="btn btn-success">
                            下載 CSV 報表
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
