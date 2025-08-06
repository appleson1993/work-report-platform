<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
$current_user = getCurrentUser();

// 取得今日統計
$today = date('Y-m-d');
$today_stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT s.staff_id) as total_staff,
        COUNT(DISTINCT a.staff_id) as checked_in_staff,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM staff s
    LEFT JOIN attendance a ON s.staff_id = a.staff_id AND a.work_date = ?
    WHERE s.is_admin = 0
");
$today_stats_stmt->execute([$today]);
$today_stats = $today_stats_stmt->fetch();

// 取得本月統計
$current_month = date('Y-m');
$month_stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.staff_id) as active_staff,
        AVG(a.total_hours) as avg_work_hours,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as total_late,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as total_absent
    FROM attendance a
    WHERE DATE_FORMAT(a.work_date, '%Y-%m') = ?
");
$month_stats_stmt->execute([$current_month]);
$month_stats = $month_stats_stmt->fetch();

// 取得今日出勤情況
$today_attendance_stmt = $pdo->prepare("
    SELECT 
        s.staff_id,
        s.name,
        s.department,
        a.check_in_time,
        a.check_out_time,
        a.status,
        a.total_hours,
        a.total_break_minutes,
        a.ip_address,
        a.user_agent
    FROM staff s
    LEFT JOIN attendance a ON s.staff_id = a.staff_id AND a.work_date = ?
    WHERE s.is_admin = 0
    ORDER BY s.department, s.name
");
$today_attendance_stmt->execute([$today]);
$today_attendance = $today_attendance_stmt->fetchAll();

// 取得最近異常記錄
$recent_issues_stmt = $pdo->prepare("
    SELECT 
        a.*,
        s.name,
        s.department
    FROM attendance a
    JOIN staff s ON a.staff_id = s.staff_id
    WHERE a.status IN ('late', 'absent', 'early_leave')
    ORDER BY a.work_date DESC, a.check_in_time DESC
    LIMIT 10
");
$recent_issues_stmt->execute();
$recent_issues = $recent_issues_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理員控制台 - 打卡系統</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- 導航欄 -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo">員工打卡系統 - 管理後台</a>
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
                <h1 class="card-title">管理員控制台</h1>
            </div>
            
            <!-- 當前時間顯示 -->
            <div class="time-display" id="current-time"></div>
            
            <!-- 今日概況 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">今日概況 (<?= date('Y年m月d日') ?>)</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $today_stats['total_staff'] ?></div>
                        <div class="stat-label">總員工數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $today_stats['checked_in_staff'] ?></div>
                        <div class="stat-label">已打卡人數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $today_stats['late_count'] ?></div>
                        <div class="stat-label">遲到人數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?= $today_stats['total_staff'] - $today_stats['checked_in_staff'] ?>
                        </div>
                        <div class="stat-label">未打卡人數</div>
                    </div>
                </div>
            </div>
            
            <!-- 本月統計 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">本月統計 (<?= date('Y年m月') ?>)</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $month_stats['active_staff'] ?: '0' ?></div>
                        <div class="stat-label">活躍員工數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= round($month_stats['avg_work_hours'] ?: 0, 1) ?></div>
                        <div class="stat-label">平均工時</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $month_stats['total_late'] ?: '0' ?></div>
                        <div class="stat-label">總遲到次數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $month_stats['total_absent'] ?: '0' ?></div>
                        <div class="stat-label">總缺席次數</div>
                    </div>
                </div>
            </div>
            
            <!-- 今日出勤狀況 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">今日出勤狀況</h2>
                
                <?php if ($today_attendance): ?>
                    <div style="margin-bottom: 1rem;">
                        <input type="text" 
                               id="search-input" 
                               class="form-input" 
                               placeholder="搜尋員工姓名或部門..."
                               style="max-width: 300px;">
                    </div>
                    
                    <table class="table" id="data-table">
                        <thead>
                            <tr>
                                <th onclick="sortTable(0)">員工編號</th>
                                <th onclick="sortTable(1)">姓名</th>
                                <th onclick="sortTable(2)">部門</th>
                                <th onclick="sortTable(3)">上班時間</th>
                                <th onclick="sortTable(4)">下班時間</th>
                                <th onclick="sortTable(5)">工時</th>
                                <th onclick="sortTable(6)">休息</th>
                                <th onclick="sortTable(7)">狀態</th>
                                <th>IP/裝置</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_attendance as $record): ?>
                                <tr>
                                    <td><?= escape($record['staff_id']) ?></td>
                                    <td><?= escape($record['name']) ?></td>
                                    <td><?= escape($record['department']) ?></td>
                                    <td><?= $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-' ?></td>
                                    <td><?= $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-' ?></td>
                                    <td><?= $record['total_hours'] ?: '0' ?></td>
                                    <td><?= $record['total_break_minutes'] ? formatBreakTime($record['total_break_minutes']) : '-' ?></td>
                                    <td>
                                        <?php if ($record['status']): ?>
                                            <span class="status <?= getStatusClass($record['status']) ?>">
                                                <?= getStatusText($record['status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status status-absent">未打卡</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.8rem; color: #ccc;">
                                        <?php if ($record['ip_address']): ?>
                                            <div title="<?= escape($record['user_agent']) ?>" style="cursor: help;">
                                                IP: <?= escape($record['ip_address']) ?><br>
                                                <?php 
                                                $ua = $record['user_agent'];
                                                if (strpos($ua, 'Mobile') !== false) {
                                                    echo '📱 手機';
                                                } elseif (strpos($ua, 'Tablet') !== false) {
                                                    echo '📱 平板';
                                                } else {
                                                    echo '💻 電腦';
                                                }
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #ccc;">暫無員工資料</p>
                <?php endif; ?>
            </div>
            
            <!-- 最近異常記錄 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">最近異常記錄</h2>
                
                <?php if ($recent_issues): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>員工</th>
                                <th>部門</th>
                                <th>時間</th>
                                <th>狀態</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_issues as $issue): ?>
                                <tr>
                                    <td><?= formatDate($issue['work_date']) ?></td>
                                    <td><?= escape($issue['name']) ?></td>
                                    <td><?= escape($issue['department']) ?></td>
                                    <td>
                                        <?= $issue['check_in_time'] ? date('H:i', strtotime($issue['check_in_time'])) : '-' ?>
                                        <?php if ($issue['check_out_time']): ?>
                                            - <?= date('H:i', strtotime($issue['check_out_time'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status <?= getStatusClass($issue['status']) ?>">
                                            <?= getStatusText($issue['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="text-center mt-2">
                        <a href="attendance_report.php" class="btn btn-primary">查看完整報表</a>
                    </div>
                <?php else: ?>
                    <p style="color: #ccc;">最近無異常記錄</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
