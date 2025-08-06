<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaff();
$current_user = getCurrentUser();

// 取得今日打卡記錄
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE staff_id = ? AND work_date = ?
");
$stmt->execute([$current_user['staff_id'], $today]);
$today_attendance = $stmt->fetch();

// 取得今日進行中的休息
$ongoing_break = null;
if ($today_attendance) {
    $break_stmt = $pdo->prepare("
        SELECT * FROM break_records 
        WHERE attendance_id = ? AND break_end_time IS NULL
        ORDER BY break_start_time DESC 
        LIMIT 1
    ");
    $break_stmt->execute([$today_attendance['id']]);
    $ongoing_break = $break_stmt->fetch();
}

// 取得今日休息記錄
$today_breaks = [];
if ($today_attendance) {
    $breaks_stmt = $pdo->prepare("
        SELECT * FROM break_records 
        WHERE attendance_id = ? 
        ORDER BY break_start_time ASC
    ");
    $breaks_stmt->execute([$today_attendance['id']]);
    $today_breaks = $breaks_stmt->fetchAll();
}

// 取得有效公告（未讀的）
$announcements_stmt = $pdo->prepare("
    SELECT a.*, 
           CASE WHEN ar.id IS NULL THEN 0 ELSE 1 END as is_read
    FROM announcements a
    LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.staff_id = ?
    WHERE a.is_active = 1 
    AND a.start_date <= NOW() 
    AND (a.end_date IS NULL OR a.end_date >= NOW())
    AND ar.id IS NULL
    ORDER BY a.type = 'urgent' DESC, a.created_at DESC
    LIMIT 5
");
$announcements_stmt->execute([$current_user['staff_id']]);
$unread_announcements = $announcements_stmt->fetchAll();

// 取得本月統計
$current_month = date('Y-m');
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        AVG(total_hours) as avg_hours
    FROM attendance 
    WHERE staff_id = ? AND DATE_FORMAT(work_date, '%Y-%m') = ?
");
$stats_stmt->execute([$current_user['staff_id'], $current_month]);
$monthly_stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>員工控制台 - 打卡系統</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* 每日報告樣式 */
        .daily-report-container {
            margin-top: 1rem;
        }
        
        .report-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .report-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-indicator {
            font-size: 1.2rem;
            animation: pulse 2s infinite;
        }
        
        .status-indicator.completed {
            color: #28a745;
            animation: none;
        }
        
        .status-indicator.pending {
            color: #ffc107;
        }
        
        .report-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .report-form-wrapper {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .report-form-wrapper.show {
            animation: slideDown 0.3s ease;
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .form-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        .form-footer {
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        #dailyReportForm {
            border: none;
            background: white;
            border-radius: 0;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* 響應式設計 */
        @media (max-width: 768px) {
            .report-info {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .report-actions {
                width: 100%;
                justify-content: center;
            }
            
            .form-header {
                padding: 0.75rem 1rem;
            }
            
            .form-header h3 {
                font-size: 1rem;
            }
            
            #dailyReportForm {
                height: 500px;
            }
        }
        
        /* 提醒動畫 */
        .report-reminder {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body data-staff-id="<?= escape($current_user['staff_id']) ?>" data-staff-name="<?= escape($current_user['name']) ?>">
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
                <a href="dashboard.php" class="nav-link active">控制台</a>
                <a href="attendance_history.php" class="nav-link">出勤記錄</a>
                <a href="salary_view.php" class="nav-link">薪資記錄</a>
                <span class="nav-user">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php" class="nav-link logout">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- 公告區域 -->
        <?php if (!empty($unread_announcements)): ?>
            <div class="announcements-section">
                <?php foreach ($unread_announcements as $announcement): ?>
                    <div class="announcement announcement-<?= $announcement['type'] ?>" data-id="<?= $announcement['id'] ?>">
                        <div class="announcement-header">
                            <span class="announcement-title"><?= escape($announcement['title']) ?></span>
                            <button class="announcement-close" onclick="markAnnouncementRead(<?= $announcement['id'] ?>)">×</button>
                        </div>
                        <div class="announcement-content">
                            <?= nl2br(escape($announcement['content'])) ?>
                        </div>
                        <div class="announcement-time">
                            <?= date('Y/m/d H:i', strtotime($announcement['start_date'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h1 class="card-title">員工控制台</h1>
            </div>
            
            <!-- 當前時間顯示 -->
            <div class="time-display" id="current-time"></div>
            
            <!-- 今日打卡狀態 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">今日打卡狀態</h2>
                
                <?php if ($today_attendance): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= formatDateTime($today_attendance['check_in_time']) ?></div>
                            <div class="stat-label">上班時間</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?= $today_attendance['check_out_time'] ? formatDateTime($today_attendance['check_out_time']) : '尚未打卡' ?>
                            </div>
                            <div class="stat-label">下班時間</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $today_attendance['total_hours'] ?: '0' ?> 小時</div>
                            <div class="stat-label">工作時數</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $today_attendance['total_break_minutes'] ? formatBreakTime($today_attendance['total_break_minutes']) : '0分鐘' ?></div>
                            <div class="stat-label">今日休息時間</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <span class="status <?= getStatusClass($today_attendance['status']) ?>">
                                    <?= getStatusText($today_attendance['status']) ?>
                                </span>
                            </div>
                            <div class="stat-label">出勤狀態</div>
                        </div>
                    </div>
                    
                    <!-- 打卡按鈕 -->
                    <div class="text-center mt-3">
                        <?php if (!$today_attendance['check_out_time']): ?>
                            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                                <?php if ($ongoing_break): ?>
                                    <button id="break-end-btn" class="btn btn-danger" onclick="endBreak()">
                                        結束休息
                                    </button>
                                    <div style="width: 100%; text-align: center; color: #ff9090; margin-top: 0.5rem;">
                                        休息中 - <?= getBreakTypeText($ongoing_break['break_type']) ?>
                                        (開始時間: <?= date('H:i', strtotime($ongoing_break['break_start_time'])) ?>)
                                    </div>
                                <?php else: ?>
                                    <button class="btn btn-primary" onclick="showBreakTypeModal()">
                                        開始休息
                                    </button>
                                <?php endif; ?>
                                
                                <button id="clock-out-btn" class="btn btn-danger" onclick="clockOut()">
                                    下班打卡
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">今日已完成打卡</div>
                        <?php endif; ?>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center">
                        <p style="color: #ccc; margin-bottom: 2rem;">今日尚未打卡</p>
                        <button id="clock-in-btn" class="btn btn-success" onclick="clockIn()">
                            上班打卡
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 今日休息記錄 -->
            <?php if ($today_breaks): ?>
                <div class="card">
                    <h2 style="color: #fff; margin-bottom: 1rem;">今日休息記錄</h2>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>休息類型</th>
                                <th>開始時間</th>
                                <th>結束時間</th>
                                <th>休息時長</th>
                                <th>狀態</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_breaks as $break_record): ?>
                                <tr>
                                    <td><?= getBreakTypeText($break_record['break_type']) ?></td>
                                    <td><?= date('H:i', strtotime($break_record['break_start_time'])) ?></td>
                                    <td><?= $break_record['break_end_time'] ? date('H:i', strtotime($break_record['break_end_time'])) : '-' ?></td>
                                    <td><?= $break_record['break_minutes'] ? formatBreakTime($break_record['break_minutes']) : '-' ?></td>
                                    <td>
                                        <?php if ($break_record['break_end_time']): ?>
                                            <span class="status status-present">已結束</span>
                                        <?php else: ?>
                                            <span class="status status-late">進行中</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- 每日工作報告 -->
            <div class="card" id="daily-report">
                <div class="card-header">
                    <h2 class="card-title">📝 每日工作報告</h2>
                    <p style="color: #ccc; font-size: 0.9rem; margin: 0.5rem 0 0 0;">請每日完成工作報告，記錄您的工作成果與心得</p>
                </div>
                
                <div class="daily-report-container">
                    <div class="report-info">
                        <div class="report-status" id="reportStatus">
                            <span class="status-indicator" id="statusIndicator">⏳</span>
                            <span id="statusText">檢查今日報告狀態...</span>
                        </div>
                        
                        <div class="report-actions">
                            <button onclick="toggleReportForm()" class="btn btn-primary" id="toggleBtn">
                                📋 填寫今日報告
                            </button>
                            <button onclick="openFullForm()" class="btn btn-secondary">
                                🔗 開啟完整表單
                            </button>
                            <button onclick="markReportCompleted()" class="btn btn-success" style="margin-left: 0.5rem;">
                                ✅ 標記完成
                            </button>
                        </div>
                    </div>
                    
                    <div class="report-form-wrapper" id="reportFormWrapper" style="display: none;">
                        <div class="form-header">
                            <h3>📅 <?= date('Y年m月d日') ?> 工作報告</h3>
                            <button onclick="toggleReportForm()" class="close-btn">×</button>
                        </div>
                        
                        <iframe 
                            id="dailyReportForm"
                            src="https://docs.google.com/forms/d/e/1FAIpQLSeccnsf6UQuG31A6cxNpjI8ez5ATvVE7YxJ5-GREh8sSJg8Dg/viewform?embedded=true&usp=pp_url&entry.1234567890=<?= urlencode($current_user['name']) ?>&entry.0987654321=<?= urlencode($current_user['staff_id']) ?>&entry.1111111111=<?= date('Y-m-d') ?>" 
                            width="100%" 
                            height="600" 
                            frameborder="0" 
                            marginheight="0" 
                            marginwidth="0">
                            載入中...
                        </iframe>
                        
                        <div class="form-footer">
                            <p style="color: #ccc; font-size: 0.85rem; text-align: center; margin: 1rem 0;">
                                💡 提示：請誠實填寫工作內容，這將有助於改善工作流程和績效評估
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 本月統計 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">本月統計 (<?= date('Y年m月') ?>)</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $monthly_stats['total_days'] ?: '0' ?></div>
                        <div class="stat-label">總出勤天數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $monthly_stats['present_days'] ?: '0' ?></div>
                        <div class="stat-label">正常出勤</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $monthly_stats['late_days'] ?: '0' ?></div>
                        <div class="stat-label">遲到次數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= round($monthly_stats['avg_hours'] ?: 0, 1) ?></div>
                        <div class="stat-label">平均工時</div>
                    </div>
                </div>
            </div>
            
            <!-- 近期打卡記錄 -->
            <?php
            $recent_stmt = $pdo->prepare("
                SELECT * FROM attendance 
                WHERE staff_id = ? 
                ORDER BY work_date DESC 
                LIMIT 7
            ");
            $recent_stmt->execute([$current_user['staff_id']]);
            $recent_records = $recent_stmt->fetchAll();
            ?>
            
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">近期打卡記錄</h2>
                
                <?php if ($recent_records): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>上班時間</th>
                                <th>下班時間</th>
                                <th>工作時數</th>
                                <th>狀態</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_records as $record): ?>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="text-center mt-2">
                        <a href="attendance_history.php" class="btn btn-primary">查看完整記錄</a>
                    </div>
                <?php else: ?>
                    <p style="color: #ccc;">暫無打卡記錄</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 休息類型選擇模態框 -->
    <div id="break-type-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
        <div class="card" style="max-width: 400px; margin: 0;">
            <div class="card-header">
                <h2 class="card-title">選擇休息類型</h2>
            </div>
            
            <div style="display: grid; gap: 1rem;">
                <button class="btn btn-primary" onclick="startBreak('lunch'); hideBreakTypeModal();">
                    🍽️ 午餐休息
                </button>
                <button class="btn btn-primary" onclick="startBreak('coffee'); hideBreakTypeModal();">
                    ☕ 茶水休息
                </button>
                <button class="btn btn-primary" onclick="startBreak('personal'); hideBreakTypeModal();">
                    🚶 個人事務
                </button>
                <button class="btn btn-primary" onclick="startBreak('other'); hideBreakTypeModal();">
                    ⏱️ 其他休息
                </button>
                <button class="btn btn-primary" onclick="hideBreakTypeModal();">
                    取消
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../includes/responsive_nav.js"></script>
    <script src="../assets/js/report-functions.js"></script>
</body>
</html>