<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
$current_user = getCurrentUser();

// 篩選參數
$staff_id = $_GET['staff_id'] ?? '';
$department = $_GET['department'] ?? '';
$date_start = $_GET['date_start'] ?? date('Y-m-01'); // 本月第一天
$date_end = $_GET['date_end'] ?? date('Y-m-t'); // 本月最後一天
$break_type = $_GET['break_type'] ?? '';

$limit = 50;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// 建立查詢條件
$where_conditions = ["1=1"];
$params = [];

if ($staff_id) {
    $where_conditions[] = "br.staff_id = ?";
    $params[] = $staff_id;
}

if ($department) {
    $where_conditions[] = "s.department = ?";
    $params[] = $department;
}

if ($date_start) {
    $where_conditions[] = "DATE(br.break_start_time) >= ?";
    $params[] = $date_start;
}

if ($date_end) {
    $where_conditions[] = "DATE(br.break_start_time) <= ?";
    $params[] = $date_end;
}

if ($break_type) {
    $where_conditions[] = "br.break_type = ?";
    $params[] = $break_type;
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
    FROM break_records br
    JOIN staff s ON br.staff_id = s.staff_id
    JOIN attendance a ON br.attendance_id = a.id
    WHERE $where_clause
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// 取得休息記錄
$query = "
    SELECT 
        br.*,
        s.name,
        s.department,
        s.position,
        a.work_date,
        a.check_in_time,
        a.check_out_time
    FROM break_records br
    JOIN staff s ON br.staff_id = s.staff_id
    JOIN attendance a ON br.attendance_id = a.id
    WHERE $where_clause
    ORDER BY br.break_start_time DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// 取得統計數據
$stats_query = "
    SELECT 
        COUNT(*) as total_breaks,
        COUNT(DISTINCT br.staff_id) as staff_count,
        SUM(CASE WHEN br.break_end_time IS NOT NULL THEN br.break_minutes ELSE 0 END) as total_minutes,
        AVG(CASE WHEN br.break_end_time IS NOT NULL THEN br.break_minutes ELSE NULL END) as avg_minutes,
        COUNT(CASE WHEN br.break_end_time IS NULL THEN 1 END) as ongoing_breaks,
        SUM(CASE WHEN br.break_type = 'lunch' THEN 1 ELSE 0 END) as lunch_breaks,
        SUM(CASE WHEN br.break_type = 'coffee' THEN 1 ELSE 0 END) as coffee_breaks,
        SUM(CASE WHEN br.break_type = 'personal' THEN 1 ELSE 0 END) as personal_breaks,
        SUM(CASE WHEN br.break_type = 'other' THEN 1 ELSE 0 END) as other_breaks
    FROM break_records br
    JOIN staff s ON br.staff_id = s.staff_id
    JOIN attendance a ON br.attendance_id = a.id
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
    <title>休息時間報表 - 打卡系統</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- 導航欄 -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">員工打卡系統 - 管理後台</div>
            <button class="nav-toggle" id="navToggle">
                <span class="hamburger"></span>
                <span class="hamburger"></span>
                <span class="hamburger"></span>
            </button>
            <div class="nav-menu" id="navMenu">
                <a href="dashboard.php" class="nav-link">控制台</a>
                <a href="attendance_report.php" class="nav-link">出勤報表</a>
                <a href="break_report.php" class="nav-link active">休息報表</a>
                <a href="staff_management.php" class="nav-link">員工管理</a>
                <a href="salary_management.php" class="nav-link">薪資管理</a>
                <a href="salary_reports.php" class="nav-link">薪資報表</a>
                <a href="work_reports.php" class="nav-link">工作報告</a>
                <a href="announcements.php" class="nav-link">公告管理</a>
                <span class="nav-user">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php" class="nav-link logout">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- 篩選條件 -->
        <div class="card">
            <h2 style="color: #fff; margin-bottom: 1rem;">休息時間報表</h2>
            
            <form method="GET" class="search-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="staff_id">員工</label>
                        <select name="staff_id" id="staff_id">
                            <option value="">所有員工</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?= $staff['staff_id'] ?>" <?= $staff_id === $staff['staff_id'] ? 'selected' : '' ?>>
                                    <?= escape($staff['name']) ?> (<?= escape($staff['staff_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="department">部門</label>
                        <select name="department" id="department">
                            <option value="">所有部門</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= escape($dept) ?>" <?= $department === $dept ? 'selected' : '' ?>>
                                    <?= escape($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="break_type">休息類型</label>
                        <select name="break_type" id="break_type">
                            <option value="">所有類型</option>
                            <option value="lunch" <?= $break_type === 'lunch' ? 'selected' : '' ?>>午餐休息</option>
                            <option value="coffee" <?= $break_type === 'coffee' ? 'selected' : '' ?>>茶水休息</option>
                            <option value="personal" <?= $break_type === 'personal' ? 'selected' : '' ?>>個人事務</option>
                            <option value="other" <?= $break_type === 'other' ? 'selected' : '' ?>>其他</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_start">開始日期</label>
                        <input type="date" name="date_start" id="date_start" value="<?= escape($date_start) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_end">結束日期</label>
                        <input type="date" name="date_end" id="date_end" value="<?= escape($date_end) ?>">
                    </div>
                    
                    <div class="form-group" style="align-self: end;">
                        <button type="submit" class="btn btn-primary">篩選</button>
                        <a href="break_report.php" class="btn btn-secondary">重置</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- 統計數據 -->
        <?php if ($total_records > 0): ?>
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">統計數據</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $statistics['total_breaks'] ?></div>
                        <div class="stat-label">總休息次數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $statistics['staff_count'] ?></div>
                        <div class="stat-label">涉及員工數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= round($statistics['total_minutes'] / 60, 1) ?></div>
                        <div class="stat-label">總休息時數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= round($statistics['avg_minutes'] ?: 0, 0) ?></div>
                        <div class="stat-label">平均休息(分)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $statistics['ongoing_breaks'] ?></div>
                        <div class="stat-label">進行中休息</div>
                    </div>
                </div>
                
                <div class="form-row" style="margin-top: 1rem; gap: 2rem;">
                    <div>
                        <strong style="color: #fff;">休息類型分布：</strong>
                        <span style="color: #ccc;">
                            午餐 <?= $statistics['lunch_breaks'] ?>次，
                            茶水 <?= $statistics['coffee_breaks'] ?>次，
                            個人事務 <?= $statistics['personal_breaks'] ?>次，
                            其他 <?= $statistics['other_breaks'] ?>次
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 記錄列表 -->
        <div class="card">
            <h2 style="color: #fff; margin-bottom: 1rem;">
                休息記錄 
                <?php if ($total_records > 0): ?>
                    <span style="font-size: 1rem; color: #ccc;">(共 <?= $total_records ?> 筆)</span>
                <?php endif; ?>
            </h2>
            
            <?php if ($records): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>員工</th>
                                <th>部門</th>
                                <th>休息類型</th>
                                <th>開始時間</th>
                                <th>結束時間</th>
                                <th>休息時長</th>
                                <th>狀態</th>
                                <th>備註</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?= date('m/d', strtotime($record['work_date'])) ?></td>
                                    <td><?= escape($record['name']) ?></td>
                                    <td><?= escape($record['department']) ?></td>
                                    <td>
                                        <span class="announcement-type announcement-type-info">
                                            <?= getBreakTypeText($record['break_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('H:i', strtotime($record['break_start_time'])) ?></td>
                                    <td>
                                        <?php if ($record['break_end_time']): ?>
                                            <?= date('H:i', strtotime($record['break_end_time'])) ?>
                                        <?php else: ?>
                                            <span style="color: #ffa500;">進行中</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['break_end_time']): ?>
                                            <?= formatBreakTime($record['break_minutes']) ?>
                                        <?php else: ?>
                                            <span style="color: #ffa500;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['break_end_time']): ?>
                                            <span class="status status-present">已結束</span>
                                        <?php else: ?>
                                            <span class="status" style="background: rgba(255, 165, 0, 0.2); color: #ffa500;">進行中</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= escape($record['notes'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- 分頁 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="margin-top: 1rem; text-align: center;">
                        <?php
                        $query_params = $_GET;
                        for ($i = 1; $i <= $total_pages; $i++):
                            $query_params['page'] = $i;
                            $url = '?' . http_build_query($query_params);
                        ?>
                            <a href="<?= escape($url) ?>" 
                               class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"
                               style="margin: 0 2px;">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center" style="color: #ccc; padding: 2rem;">
                    沒有找到休息記錄
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../includes/responsive_nav.js"></script>
</body>
</html>
