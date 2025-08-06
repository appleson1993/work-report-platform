<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
$current_user = getCurrentUser();

// 獲取今日日期
$today = date('Y-m-d');
$current_month = date('Y-m');

// 獲取員工列表
$staff_stmt = $pdo->prepare("SELECT staff_id, name, department FROM staff WHERE is_admin = 0 ORDER BY name");
$staff_stmt->execute();
$staff_list = $staff_stmt->fetchAll();

// 模擬工作報告狀態（實際應用中應該連接到Google Forms API或建立本地報告系統）
// 這裡使用localStorage模擬狀態，實際中可以連接到Google Sheets API
$report_stats = [
    'total_staff' => count($staff_list),
    'completed_today' => 0,
    'completion_rate' => 0,
    'weekly_completion' => [],
    'monthly_completion' => []
];

// 計算本週完成率（模擬數據）
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('m/d', strtotime($date));
    $completion = rand(60, 95); // 模擬60-95%的完成率
    $report_stats['weekly_completion'][] = [
        'date' => $date,
        'day' => $day_name,
        'completion' => $completion
    ];
}

// 計算本月完成率（模擬數據）
$days_in_month = date('t');
for ($day = 1; $day <= min($days_in_month, date('d')); $day++) {
    $date = date('Y-m') . '-' . sprintf('%02d', $day);
    $completion = rand(65, 90);
    $report_stats['monthly_completion'][] = [
        'date' => $date,
        'day' => $day,
        'completion' => $completion
    ];
}

$report_stats['completion_rate'] = array_sum(array_column($report_stats['weekly_completion'], 'completion')) / 7;
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>工作報告統計 - 員工打卡系統</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        }
        
        .report-stat-card.total { border-left-color: #007bff; }
        .report-stat-card.completed { border-left-color: #28a745; }
        .report-stat-card.rate { border-left-color: #ffc107; }
        .report-stat-card.reminder { border-left-color: #dc3545; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #ccc;
            font-size: 0.9rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        
        .staff-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .staff-card {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid;
        }
        
        .staff-card.completed {
            border-left-color: #28a745;
        }
        
        .staff-card.pending {
            border-left-color: #ffc107;
        }
        
        .staff-card.missing {
            border-left-color: #dc3545;
        }
        
        .staff-name {
            font-weight: bold;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        
        .staff-department {
            color: #ccc;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .staff-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            color: #fff;
        }
        
        .reminder-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .report-stats {
                grid-template-columns: 1fr;
            }
            
            .staff-list {
                grid-template-columns: 1fr;
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
                <a href="salary_reports.php" class="nav-link">薪資報表</a>
                <a href="work_reports.php" class="nav-link active">工作報告</a>
                <a href="announcements.php" class="nav-link">公告管理</a>
                <span class="nav-user">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php" class="nav-link logout">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- 統計概覽 -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📊 工作報告統計概覽</h2>
                <p style="color: #ccc; margin: 0.5rem 0 0 0;">追蹤員工每日工作報告完成情況</p>
            </div>
            
            <div class="report-stats">
                <div class="report-stat-card total">
                    <div class="stat-value"><?= $report_stats['total_staff'] ?></div>
                    <div class="stat-label">總員工數</div>
                </div>
                
                <div class="report-stat-card completed">
                    <div class="stat-value" id="todayCompleted">計算中...</div>
                    <div class="stat-label">今日已完成</div>
                </div>
                
                <div class="report-stat-card rate">
                    <div class="stat-value" id="completionRate">計算中...</div>
                    <div class="stat-label">本週平均完成率</div>
                </div>
                
                <div class="report-stat-card reminder">
                    <div class="stat-value" id="needReminder">計算中...</div>
                    <div class="stat-label">需要提醒</div>
                </div>
            </div>
        </div>

        <!-- 本週趨勢 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📈 本週完成趨勢</h3>
            </div>
            
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>

        <!-- 本月完成率 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📅 本月完成情況</h3>
            </div>
            
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <!-- 員工狀態列表 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">👥 員工今日報告狀態</h3>
                <div class="reminder-actions">
                    <button onclick="sendReminder()" class="btn btn-warning">📧 發送提醒</button>
                    <button onclick="refreshStatus()" class="btn btn-primary">🔄 重新整理</button>
                    <a href="https://docs.google.com/forms/d/e/1FAIpQLSeccnsf6UQuG31A6cxNpjI8ez5ATvVE7YxJ5-GREh8sSJg8Dg/viewform" 
                       target="_blank" class="btn btn-secondary">🔗 查看表單</a>
                </div>
            </div>
            
            <div class="staff-list" id="staffList">
                <!-- 員工狀態卡片將由JavaScript動態生成 -->
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../includes/responsive_nav.js"></script>
    
    <script>
        // 員工列表數據
        const staffList = <?= json_encode($staff_list) ?>;
        const today = '<?= $today ?>';
        
        // 檢查員工報告狀態
        function checkStaffReportStatus() {
            const staffStatusHTML = [];
            let completedCount = 0;
            let needReminderCount = 0;
            
            staffList.forEach(staff => {
                const statusKey = `daily_report_${today}_${staff.staff_id}`;
                const completed = localStorage.getItem(statusKey) === 'true';
                
                if (completed) {
                    completedCount++;
                } else {
                    needReminderCount++;
                }
                
                const statusClass = completed ? 'completed' : 'pending';
                const statusText = completed ? '已完成' : '待填寫';
                const statusColor = completed ? '#28a745' : '#ffc107';
                const statusIcon = completed ? '✅' : '⏳';
                
                staffStatusHTML.push(`
                    <div class="staff-card ${statusClass}">
                        <div class="staff-name">${staff.name}</div>
                        <div class="staff-department">${staff.department}</div>
                        <div class="staff-status">
                            <span style="font-size: 1.2rem;">${statusIcon}</span>
                            <span class="status-badge" style="background-color: ${statusColor}">
                                ${statusText}
                            </span>
                        </div>
                    </div>
                `);
            });
            
            document.getElementById('staffList').innerHTML = staffStatusHTML.join('');
            document.getElementById('todayCompleted').textContent = completedCount;
            document.getElementById('needReminder').textContent = needReminderCount;
            document.getElementById('completionRate').textContent = 
                Math.round((completedCount / staffList.length) * 100) + '%';
        }
        
        // 發送提醒
        function sendReminder() {
            // 這裡可以實現實際的提醒功能，例如發送郵件或系統通知
            alert('提醒已發送給所有未完成報告的員工！\n\n實際應用中，這裡可以整合：\n- 郵件系統\n- 即時通訊工具\n- 系統內通知');
        }
        
        // 重新整理狀態
        function refreshStatus() {
            checkStaffReportStatus();
            alert('狀態已更新！');
        }
        
        // 初始化頁面
        document.addEventListener('DOMContentLoaded', function() {
            checkStaffReportStatus();
            
            // 本週趨勢圖表
            const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
            new Chart(weeklyCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($report_stats['weekly_completion'], 'day')) ?>,
                    datasets: [{
                        label: '完成率 (%)',
                        data: <?= json_encode(array_column($report_stats['weekly_completion'], 'completion')) ?>,
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
                            labels: { color: '#ffffff' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                color: '#ffffff',
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        },
                        x: {
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        }
                    }
                }
            });
            
            // 本月完成率圖表
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($report_stats['monthly_completion'], 'day')) ?>,
                    datasets: [{
                        label: '完成率 (%)',
                        data: <?= json_encode(array_column($report_stats['monthly_completion'], 'completion')) ?>,
                        backgroundColor: function(context) {
                            const value = context.parsed.y;
                            if (value >= 90) return '#28a745';
                            if (value >= 75) return '#ffc107';
                            return '#dc3545';
                        },
                        borderColor: function(context) {
                            const value = context.parsed.y;
                            if (value >= 90) return '#1e7e34';
                            if (value >= 75) return '#e0a800';
                            return '#c82333';
                        },
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#ffffff' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                color: '#ffffff',
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        },
                        x: {
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
