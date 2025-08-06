<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
$current_user = getCurrentUser();

// 獲取篩選參數
$filter_year = $_GET['year'] ?? date('Y');
$filter_staff = $_GET['staff_id'] ?? '';
$report_type = $_GET['type'] ?? 'monthly';

// 獲取員工列表
$staff_stmt = $pdo->prepare("SELECT staff_id, name FROM staff WHERE is_admin = 0 ORDER BY name");
$staff_stmt->execute();
$staff_list = $staff_stmt->fetchAll();

// 獲取年度統計
$yearly_stats = [];
$where_conditions = ["YEAR(sr.record_date) = ?"];
$params = [$filter_year];

if ($filter_staff) {
    $where_conditions[] = "sr.staff_id = ?";
    $params[] = $filter_staff;
}

$where_clause = implode(' AND ', $where_conditions);

// 月度統計
if ($report_type === 'monthly') {
    $monthly_stmt = $pdo->prepare("
        SELECT 
            MONTH(sr.record_date) as month,
            COUNT(*) as record_count,
            SUM(sr.amount) as total_amount,
            AVG(sr.amount) as avg_amount,
            SUM(CASE WHEN sr.status = 'paid' THEN sr.amount ELSE 0 END) as paid_amount
        FROM salary_records sr
        WHERE $where_clause
        GROUP BY MONTH(sr.record_date)
        ORDER BY month
    ");
    $monthly_stmt->execute($params);
    $monthly_data = $monthly_stmt->fetchAll();
    
    // 填充12個月的數據
    $monthly_stats = array_fill(1, 12, [
        'month' => 0,
        'record_count' => 0,
        'total_amount' => 0,
        'avg_amount' => 0,
        'paid_amount' => 0
    ]);
    
    foreach ($monthly_data as $data) {
        $monthly_stats[$data['month']] = $data;
    }
}

// 員工排名統計
$ranking_stmt = $pdo->prepare("
    SELECT 
        sr.staff_id,
        s.name as staff_name,
        COUNT(*) as record_count,
        SUM(sr.amount) as total_amount,
        AVG(sr.amount) as avg_amount,
        SUM(CASE WHEN sr.status = 'paid' THEN sr.amount ELSE 0 END) as paid_amount,
        MAX(sr.record_date) as last_record_date
    FROM salary_records sr
    LEFT JOIN staff s ON sr.staff_id = s.staff_id
    WHERE $where_clause
    GROUP BY sr.staff_id
    ORDER BY total_amount DESC
    LIMIT 10
");
$ranking_stmt->execute($params);
$staff_ranking = $ranking_stmt->fetchAll();

// 類別統計
$category_stmt = $pdo->prepare("
    SELECT 
        sc.name as category_name,
        sc.color as category_color,
        COUNT(*) as record_count,
        SUM(sr.amount) as total_amount,
        AVG(sr.amount) as avg_amount
    FROM salary_records sr
    LEFT JOIN salary_categories sc ON sr.category_id = sc.id
    WHERE $where_clause
    GROUP BY sr.category_id
    ORDER BY total_amount DESC
");
$category_stmt->execute($params);
$category_stats = $category_stmt->fetchAll();

// 總體統計
$total_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_records,
        SUM(sr.amount) as total_amount,
        AVG(sr.amount) as avg_amount,
        SUM(CASE WHEN sr.status = 'paid' THEN sr.amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN sr.status = 'pending' THEN sr.amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN sr.status = 'approved' THEN sr.amount ELSE 0 END) as total_approved
    FROM salary_records sr
    WHERE $where_clause
");
$total_stmt->execute($params);
$total_stats = $total_stmt->fetch();

// 導出功能
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="salary_report_' . $filter_year . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['員工編號', '員工姓名', '記錄數', '總金額', '平均金額', '已支付金額', '最後記錄日期']);
    
    foreach ($staff_ranking as $row) {
        fputcsv($output, [
            $row['staff_id'],
            $row['staff_name'],
            $row['record_count'],
            $row['total_amount'],
            round($row['avg_amount'], 2),
            $row['paid_amount'],
            $row['last_record_date']
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>薪資報表 - 員工打卡系統</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .report-stat-card {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            border-left: 4px solid;
            position: relative;
            overflow: hidden;
        }
        
        .report-stat-card.total { border-left-color: #28a745; }
        .report-stat-card.records { border-left-color: #17a2b8; }
        .report-stat-card.average { border-left-color: #ffc107; }
        .report-stat-card.paid { border-left-color: #007bff; }
        .report-stat-card.pending { border-left-color: #fd7e14; }
        .report-stat-card.approved { border-left-color: #6f42c1; }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #ccc;
            font-size: 0.85rem;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin: 2rem 0;
        }
        
        .ranking-list {
            list-style: none;
            padding: 0;
        }
        
        .ranking-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            margin-bottom: 0.5rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 6px;
            border-left: 4px solid #28a745;
        }
        
        .ranking-item:nth-child(1) { border-left-color: #ffd700; }
        .ranking-item:nth-child(2) { border-left-color: #c0c0c0; }
        .ranking-item:nth-child(3) { border-left-color: #cd7f32; }
        
        .ranking-info {
            flex: 1;
        }
        
        .ranking-name {
            font-weight: bold;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        
        .ranking-details {
            font-size: 0.85rem;
            color: #ccc;
        }
        
        .ranking-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }
        
        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 6px;
        }
        
        .category-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .category-color {
            width: 16px;
            height: 16px;
            border-radius: 50%;
        }
        
        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .report-stats {
                grid-template-columns: 1fr;
            }
            
            .export-buttons {
                flex-direction: column;
            }
            
            .chart-container {
                height: 300px;
            }
        }
    </style>
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
                <a href="break_report.php" class="nav-link">休息報表</a>
                <a href="staff_management.php" class="nav-link">員工管理</a>
                <a href="salary_management.php" class="nav-link">薪資管理</a>
                <a href="salary_reports.php" class="nav-link active">薪資報表</a>
                <a href="work_reports.php" class="nav-link">工作報告</a>
                <a href="announcements.php" class="nav-link">公告管理</a>
                <span class="nav-user">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php" class="nav-link logout">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- 篩選器 -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📊 薪資報表分析</h2>
            </div>
            
            <form method="GET" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">年份</label>
                        <select name="year" class="form-input">
                            <?php for ($year = date('Y'); $year >= date('Y') - 5; $year--): ?>
                                <option value="<?= $year ?>" <?= $filter_year == $year ? 'selected' : '' ?>>
                                    <?= $year ?> 年
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">員工</label>
                        <select name="staff_id" class="form-input">
                            <option value="">所有員工</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?= escape($staff['staff_id']) ?>" 
                                        <?= $filter_staff === $staff['staff_id'] ? 'selected' : '' ?>>
                                    <?= escape($staff['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">報表類型</label>
                        <select name="type" class="form-input">
                            <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>月度分析</option>
                            <option value="category" <?= $report_type === 'category' ? 'selected' : '' ?>>類別分析</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">🔍 生成報表</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 總體統計 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📈 <?= $filter_year ?> 年度統計總覽</h3>
            </div>
            
            <div class="report-stats">
                <div class="report-stat-card total">
                    <div class="stat-value">$<?= number_format($total_stats['total_amount'] ?: 0) ?></div>
                    <div class="stat-label">總薪資金額</div>
                </div>
                
                <div class="report-stat-card records">
                    <div class="stat-value"><?= $total_stats['total_records'] ?: 0 ?></div>
                    <div class="stat-label">薪資記錄數</div>
                </div>
                
                <div class="report-stat-card average">
                    <div class="stat-value">$<?= number_format($total_stats['avg_amount'] ?: 0) ?></div>
                    <div class="stat-label">平均金額</div>
                </div>
                
                <div class="report-stat-card paid">
                    <div class="stat-value">$<?= number_format($total_stats['total_paid'] ?: 0) ?></div>
                    <div class="stat-label">已支付金額</div>
                </div>
                
                <div class="report-stat-card approved">
                    <div class="stat-value">$<?= number_format($total_stats['total_approved'] ?: 0) ?></div>
                    <div class="stat-label">已批准金額</div>
                </div>
                
                <div class="report-stat-card pending">
                    <div class="stat-value">$<?= number_format($total_stats['total_pending'] ?: 0) ?></div>
                    <div class="stat-label">待處理金額</div>
                </div>
            </div>
        </div>

        <!-- 月度趨勢圖表 -->
        <?php if ($report_type === 'monthly' && !empty($monthly_stats)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📅 月度薪資趨勢</h3>
                </div>
                
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- 類別分布圖表 -->
        <?php if ($report_type === 'category' && !empty($category_stats)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🏷️ 薪資類別分布</h3>
                </div>
                
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <!-- 員工排名 -->
            <div class="card" style="flex: 1;">
                <div class="card-header">
                    <h3 class="card-title">🏆 員工薪資排名 TOP 10</h3>
                    <div class="export-buttons">
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
                           class="btn btn-sm btn-success">📄 導出 CSV</a>
                        <a href="salary_management.php" class="btn btn-sm btn-secondary">⬅️ 返回管理</a>
                    </div>
                </div>
                
                <?php if (!empty($staff_ranking)): ?>
                    <ul class="ranking-list">
                        <?php foreach ($staff_ranking as $index => $staff): ?>
                            <li class="ranking-item">
                                <div class="ranking-info">
                                    <div class="ranking-name">
                                        #<?= $index + 1 ?> <?= escape($staff['staff_name']) ?>
                                    </div>
                                    <div class="ranking-details">
                                        <?= $staff['record_count'] ?> 筆記錄 | 
                                        平均 $<?= number_format($staff['avg_amount']) ?> | 
                                        已支付 $<?= number_format($staff['paid_amount']) ?>
                                    </div>
                                </div>
                                <div class="ranking-amount">
                                    $<?= number_format($staff['total_amount']) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center" style="padding: 2rem;">
                        <p style="color: #ccc;">暫無薪資數據</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 類別統計 -->
            <div class="card" style="flex: 1;">
                <div class="card-header">
                    <h3 class="card-title">🏷️ 薪資類別統計</h3>
                </div>
                
                <?php if (!empty($category_stats)): ?>
                    <div>
                        <?php foreach ($category_stats as $category): ?>
                            <div class="category-item">
                                <div class="category-info">
                                    <div class="category-color" style="background-color: <?= escape($category['category_color']) ?>"></div>
                                    <div>
                                        <div style="color: #fff; font-weight: bold;">
                                            <?= escape($category['category_name']) ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #ccc;">
                                            <?= $category['record_count'] ?> 筆 | 
                                            平均 $<?= number_format($category['avg_amount']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="color: #28a745; font-weight: bold;">
                                    $<?= number_format($category['total_amount']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center" style="padding: 2rem;">
                        <p style="color: #ccc;">暫無類別數據</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../includes/responsive_nav.js"></script>
    
    <!-- 圖表腳本 -->
    <?php if ($report_type === 'monthly' && !empty($monthly_stats)): ?>
        <script>
            // 月度趨勢圖表
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
                    datasets: [{
                        label: '總金額',
                        data: [
                            <?php foreach ($monthly_stats as $month => $data): ?>
                                <?= $data['total_amount'] ?: 0 ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.4
                    }, {
                        label: '已支付金額',
                        data: [
                            <?php foreach ($monthly_stats as $month => $data): ?>
                                <?= $data['paid_amount'] ?: 0 ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#ffffff'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#ffffff',
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#ffffff'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>

    <?php if ($report_type === 'category' && !empty($category_stats)): ?>
        <script>
            // 類別分布圓餅圖
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php foreach ($category_stats as $category): ?>
                            '<?= escape($category['category_name']) ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($category_stats as $category): ?>
                                <?= $category['total_amount'] ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            <?php foreach ($category_stats as $category): ?>
                                '<?= escape($category['category_color']) ?>',
                            <?php endforeach; ?>
                        ],
                        borderColor: '#1a1a1a',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#ffffff',
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.formattedValue;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return `${label}: $${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
