<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$staff_id = $current_user['staff_id'];

// 獲取篩選參數
$filter_year = $_GET['year'] ?? date('Y');
$filter_month = $_GET['month'] ?? '';
$filter_category = $_GET['category_id'] ?? '';
$filter_status = $_GET['status'] ?? '';

// 獲取分類列表
$categories_stmt = $pdo->prepare("SELECT * FROM salary_categories ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll();

// 構建查詢條件
$where_conditions = ["sr.staff_id = ?"];
$params = [$staff_id];

if ($filter_year) {
    $where_conditions[] = "YEAR(sr.record_date) = ?";
    $params[] = $filter_year;
}

if ($filter_month) {
    $where_conditions[] = "MONTH(sr.record_date) = ?";
    $params[] = $filter_month;
}

if ($filter_category) {
    $where_conditions[] = "sr.category_id = ?";
    $params[] = $filter_category;
}

if ($filter_status) {
    $where_conditions[] = "sr.status = ?";
    $params[] = $filter_status;
}

$where_clause = implode(' AND ', $where_conditions);

// 獲取薪資記錄
$records_stmt = $pdo->prepare("
    SELECT 
        sr.*,
        sc.name as category_name,
        sc.color as category_color
    FROM salary_records sr
    LEFT JOIN salary_categories sc ON sr.category_id = sc.id
    WHERE $where_clause
    ORDER BY sr.record_date DESC, sr.created_at DESC
    LIMIT 50
");
$records_stmt->execute($params);
$records = $records_stmt->fetchAll();

// 獲取統計數據
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_records,
        SUM(sr.amount) as total_amount,
        AVG(sr.amount) as avg_amount,
        SUM(CASE WHEN sr.status = 'paid' THEN sr.amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN sr.status = 'pending' THEN sr.amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN sr.status = 'approved' THEN sr.amount ELSE 0 END) as approved_amount
    FROM salary_records sr
    WHERE $where_clause
");
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

// 獲取月度統計
$monthly_stmt = $pdo->prepare("
    SELECT 
        MONTH(sr.record_date) as month,
        COUNT(*) as record_count,
        SUM(sr.amount) as total_amount,
        SUM(CASE WHEN sr.status = 'paid' THEN sr.amount ELSE 0 END) as paid_amount
    FROM salary_records sr
    WHERE sr.staff_id = ? AND YEAR(sr.record_date) = ?
    GROUP BY MONTH(sr.record_date)
    ORDER BY month
");
$monthly_stmt->execute([$staff_id, $filter_year]);
$monthly_data = $monthly_stmt->fetchAll();

// 填充12個月的數據
$monthly_stats = array_fill(1, 12, [
    'month' => 0,
    'record_count' => 0,
    'total_amount' => 0,
    'paid_amount' => 0
]);

foreach ($monthly_data as $data) {
    $monthly_stats[$data['month']] = $data;
}

// 狀態顏色映射
$status_colors = [
    'pending' => '#ffc107',
    'approved' => '#28a745', 
    'rejected' => '#dc3545',
    'paid' => '#007bff'
];

$status_labels = [
    'pending' => '待審核',
    'approved' => '已批准',
    'rejected' => '已拒絕',
    'paid' => '已支付'
];
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的薪資記錄 - 員工打卡系統</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .salary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .salary-stat-card {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            border-left: 4px solid;
            position: relative;
        }
        
        .salary-stat-card.total { border-left-color: #28a745; }
        .salary-stat-card.records { border-left-color: #17a2b8; }
        .salary-stat-card.average { border-left-color: #ffc107; }
        .salary-stat-card.paid { border-left-color: #007bff; }
        .salary-stat-card.pending { border-left-color: #fd7e14; }
        .salary-stat-card.approved { border-left-color: #6f42c1; }
        
        .stat-value {
            font-size: 1.6rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #ccc;
            font-size: 0.85rem;
        }
        
        .salary-record {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }
        
        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .record-title {
            font-weight: bold;
            color: #fff;
            font-size: 1.1rem;
        }
        
        .record-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }
        
        .record-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .record-detail {
            font-size: 0.85rem;
            color: #ccc;
        }
        
        .record-detail strong {
            color: #fff;
        }
        
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            color: #fff;
            font-weight: bold;
        }
        
        .category-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            color: #fff;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        
        .motivational-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
            color: #fff;
        }
        
        .motivational-message h3 {
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .motivational-message p {
            font-size: 1.1rem;
            margin-bottom: 0;
        }
        
        .no-records {
            text-align: center;
            padding: 3rem;
            color: #ccc;
        }
        
        .no-records i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .salary-stats {
                grid-template-columns: 1fr;
            }
            
            .record-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .chart-container {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- 導航欄 -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">員工打卡系統</div>
            <button class="nav-toggle" id="navToggle">
                <span class="hamburger"></span>
                <span class="hamburger"></span>
                <span class="hamburger"></span>
            </button>
            <div class="nav-menu" id="navMenu">
                <a href="dashboard.php" class="nav-link">控制台</a>
                <a href="attendance_history.php" class="nav-link">出勤記錄</a>
                <a href="salary_view.php" class="nav-link active">薪資記錄</a>
                <span class="nav-user">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php" class="nav-link logout">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- 激勵訊息 -->
        <?php if ($stats['total_amount'] > 0): ?>
            <div class="motivational-message">
                <h3>🎉 您的努力成果</h3>
                <p>
                    您在 <?= $filter_year ?> 年已獲得 <strong>$<?= number_format($stats['total_amount']) ?></strong> 的薪資，
                    共 <?= $stats['total_records'] ?> 筆記錄。繼續保持優秀的工作表現！
                </p>
            </div>
        <?php endif; ?>

        <!-- 篩選器 -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">💰 我的薪資記錄</h2>
            </div>
            
            <form method="GET" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">年份</label>
                        <select name="year" class="form-input">
                            <?php for ($year = date('Y'); $year >= date('Y') - 3; $year--): ?>
                                <option value="<?= $year ?>" <?= $filter_year == $year ? 'selected' : '' ?>>
                                    <?= $year ?> 年
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">月份</label>
                        <select name="month" class="form-input">
                            <option value="">全年</option>
                            <?php for ($month = 1; $month <= 12; $month++): ?>
                                <option value="<?= $month ?>" <?= $filter_month == $month ? 'selected' : '' ?>>
                                    <?= $month ?> 月
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">類別</label>
                        <select name="category_id" class="form-input">
                            <option value="">所有類別</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" 
                                        <?= $filter_category == $category['id'] ? 'selected' : '' ?>>
                                    <?= escape($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">狀態</label>
                        <select name="status" class="form-input">
                            <option value="">所有狀態</option>
                            <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>待審核</option>
                            <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>已批准</option>
                            <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>已支付</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">🔍 篩選</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 工作報告提醒 -->
        <div class="card" style="background: linear-gradient(135deg, #28a745, #20c997); border: none;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="color: white; margin: 0 0 0.5rem 0; font-size: 1.1rem;">📝 提升薪資表現</h3>
                    <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 0.9rem;">完成每日工作報告有助於薪資評估與績效提升</p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="dashboard.php#daily-report" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                        前往填寫
                    </a>
                    <a href="https://docs.google.com/forms/d/e/1FAIpQLSeccnsf6UQuG31A6cxNpjI8ez5ATvVE7YxJ5-GREh8sSJg8Dg/viewform" target="_blank" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                        📋 工作報告
                    </a>
                </div>
            </div>
        </div>

        <!-- 統計概覽 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📊 統計概覽</h3>
            </div>
            
            <div class="salary-stats">
                <div class="salary-stat-card total">
                    <div class="stat-value">$<?= number_format($stats['total_amount'] ?: 0) ?></div>
                    <div class="stat-label">總金額</div>
                </div>
                
                <div class="salary-stat-card records">
                    <div class="stat-value"><?= $stats['total_records'] ?: 0 ?></div>
                    <div class="stat-label">記錄數</div>
                </div>
                
                <div class="salary-stat-card average">
                    <div class="stat-value">$<?= number_format($stats['avg_amount'] ?: 0) ?></div>
                    <div class="stat-label">平均金額</div>
                </div>
                
                <div class="salary-stat-card paid">
                    <div class="stat-value">$<?= number_format($stats['paid_amount'] ?: 0) ?></div>
                    <div class="stat-label">已支付</div>
                </div>
                
                <div class="salary-stat-card approved">
                    <div class="stat-value">$<?= number_format($stats['approved_amount'] ?: 0) ?></div>
                    <div class="stat-label">已批准</div>
                </div>
                
                <div class="salary-stat-card pending">
                    <div class="stat-value">$<?= number_format($stats['pending_amount'] ?: 0) ?></div>
                    <div class="stat-label">待處理</div>
                </div>
            </div>
        </div>

        <!-- 月度趨勢圖 -->
        <?php if (!empty($monthly_data)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📈 <?= $filter_year ?> 年月度趨勢</h3>
                </div>
                
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- 薪資記錄列表 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📋 薪資記錄明細</h3>
            </div>
            
            <?php if (!empty($records)): ?>
                <?php foreach ($records as $record): ?>
                    <div class="salary-record" style="border-left-color: <?= escape($record['category_color']) ?>">
                        <div class="record-header">
                            <div class="record-title"><?= escape($record['project_name']) ?></div>
                            <div class="record-amount">$<?= number_format($record['amount']) ?></div>
                        </div>
                        
                        <?php if ($record['description']): ?>
                            <div style="color: #ccc; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <?= escape($record['description']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="record-details">
                            <div class="record-detail">
                                <strong>類別：</strong>
                                <span class="category-badge" style="background-color: <?= escape($record['category_color']) ?>">
                                    <span class="category-dot" style="background-color: rgba(255,255,255,0.8)"></span>
                                    <?= escape($record['category_name']) ?>
                                </span>
                            </div>
                            
                            <div class="record-detail">
                                <strong>狀態：</strong>
                                <span class="status-badge" style="background-color: <?= $status_colors[$record['status']] ?>">
                                    <?= $status_labels[$record['status']] ?>
                                </span>
                            </div>
                            
                            <div class="record-detail">
                                <strong>記錄日期：</strong><?= formatDate($record['record_date']) ?>
                            </div>
                            
                            <div class="record-detail">
                                <strong>創建時間：</strong><?= formatDateTime($record['created_at']) ?>
                            </div>
                            
                            <?php if ($record['approved_at']): ?>
                                <div class="record-detail">
                                    <strong>批准時間：</strong><?= formatDateTime($record['approved_at']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($record['paid_at']): ?>
                                <div class="record-detail">
                                    <strong>支付時間：</strong><?= formatDateTime($record['paid_at']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-records">
                    <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;">💼</div>
                    <h3>暫無薪資記錄</h3>
                    <p>在選定的篩選條件下沒有找到薪資記錄。</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../includes/responsive_nav.js"></script>
    
    <!-- 月度趨勢圖 -->
    <?php if (!empty($monthly_data)): ?>
        <script>
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
</body>
</html>
