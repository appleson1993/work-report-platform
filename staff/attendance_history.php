<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaff();
$current_user = getCurrentUser();

// 處理查詢參數
$month = $_GET['month'] ?? date('Y-m');
$limit = 50;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// 取得出勤記錄
$where_conditions = ["staff_id = ?"];
$params = [$current_user['staff_id']];

if ($month) {
    $where_conditions[] = "DATE_FORMAT(work_date, '%Y-%m') = ?";
    $params[] = $month;
}

$where_clause = implode(' AND ', $where_conditions);

// 計算總記錄數
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE $where_clause");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// 取得記錄
$stmt = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE $where_clause
    ORDER BY work_date DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// 取得月份統計
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'early_leave' THEN 1 ELSE 0 END) as early_leave_days,
        SUM(total_hours) as total_hours,
        AVG(total_hours) as avg_hours
    FROM attendance 
    WHERE staff_id = ? AND DATE_FORMAT(work_date, '%Y-%m') = ?
");
$stats_stmt->execute([$current_user['staff_id'], $month]);
$monthly_stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>出勤記錄 - 員工打卡系統</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- 導航欄 -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">員工打卡系統</a>
            <div class="nav-links">
                <a href="dashboard.php">控制台</a>
                <a href="attendance_history.php">出勤記錄</a>
                <span style="color: #ccc;">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">我的出勤記錄</h1>
            </div>
            
            <!-- 篩選器 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">篩選條件</h2>
                
                <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="month">選擇月份</label>
                        <input type="month" 
                               id="month" 
                               name="month" 
                               class="form-input" 
                               value="<?= escape($month) ?>"
                               style="width: 200px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary">篩選</button>
                        <a href="attendance_history.php" class="btn btn-primary">重置</a>
                    </div>
                </form>
            </div>
            
            <!-- 月份統計 -->
            <?php if ($monthly_stats && $monthly_stats['total_days'] > 0): ?>
                <div class="card">
                    <h2 style="color: #fff; margin-bottom: 1rem;">
                        <?= date('Y年m月', strtotime($month . '-01')) ?> 統計
                    </h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= $monthly_stats['total_days'] ?></div>
                            <div class="stat-label">總出勤天數</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $monthly_stats['present_days'] ?></div>
                            <div class="stat-label">正常出勤</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $monthly_stats['late_days'] ?></div>
                            <div class="stat-label">遲到次數</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $monthly_stats['early_leave_days'] ?></div>
                            <div class="stat-label">早退次數</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= round($monthly_stats['total_hours'] ?: 0, 1) ?></div>
                            <div class="stat-label">總工作時數</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= round($monthly_stats['avg_hours'] ?: 0, 1) ?></div>
                            <div class="stat-label">平均工時</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 記錄列表 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">出勤記錄</h2>
                
                <?php if ($records): ?>
                    <table class="table" id="data-table">
                        <thead>
                            <tr>
                                <th onclick="sortTable(0)">日期</th>
                                <th onclick="sortTable(1)">上班時間</th>
                                <th onclick="sortTable(2)">下班時間</th>
                                <th onclick="sortTable(3)">工作時數</th>
                                <th onclick="sortTable(4)">狀態</th>
                                <th>備註</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?= formatDate($record['work_date']) ?></td>
                                    <td><?= $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-' ?></td>
                                    <td><?= $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-' ?></td>
                                    <td><?= $record['total_hours'] ?: '0' ?></td>
                                    <td>
                                        <span class="status <?= getStatusClass($record['status']) ?>">
                                            <?= getStatusText($record['status']) ?>
                                        </span>
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
                                    <a href="?month=<?= urlencode($month) ?>&page=<?= $page - 1 ?>" class="btn btn-primary btn-sm">上一頁</a>
                                <?php endif; ?>
                                
                                <span style="color: #ccc; padding: 0 1rem;">
                                    第 <?= $page ?> 頁，共 <?= $total_pages ?> 頁
                                </span>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?month=<?= urlencode($month) ?>&page=<?= $page + 1 ?>" class="btn btn-primary btn-sm">下一頁</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center">
                        <p style="color: #ccc;">暫無出勤記錄</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
