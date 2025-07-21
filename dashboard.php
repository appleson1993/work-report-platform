<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// 檢查登入狀態
requireLogin();

$db = new Database();
$userId = $_SESSION['user_id'];

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $action = getPostValue('action');
        
        if ($action === 'submit_report') {
            $taskId = getPostValueInt('task_id');
            $content = getPostValue('content'); // 保持原始內容用於富文本
            $status = getPostValueSanitized('status');
            $isRichText = getPostValueBool('is_rich_text');
            
            // 對於富文本，檢查去除HTML標籤後的內容；對於純文本，直接檢查
            $contentToCheck = $isRichText ? strip_tags($content) : trim($content);
            if (empty($contentToCheck)) {
                throw new Exception('請填寫工作內容');
            }
            
            // 新增工作回報記錄（每次都新增一筆新記錄）
            $db->execute(
                'INSERT INTO work_reports (task_id, user_id, report_date, content, status, is_rich_text, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$taskId, $userId, date('Y-m-d'), $content, $status, $isRichText]
            );
            
            // 更新任務狀態為最新提交的狀態
            $db->execute('UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $taskId]);
            
            jsonResponse(true, '工作回報提交成功！');
            
        } elseif ($action === 'get_reports') {
            $taskId = getPostValueInt('task_id');
            
            $reports = $db->fetchAll(
                'SELECT * FROM work_reports WHERE task_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 20',
                [$taskId, $userId]
            );
            
            jsonResponse(true, '取得回報紀錄成功', $reports);
            
        } elseif ($action === 'post_discussion') {
            $projectId = getPostValueInt('project_id');
            $content = getPostValue('content');
            
            if (empty(strip_tags($content))) {
                throw new Exception('請填寫討論內容');
            }
            
            // 檢查用戶是否有此專案的權限
            $hasAccess = $db->fetch(
                'SELECT COUNT(*) as count FROM tasks WHERE project_id = ? AND assigned_user_id = ?',
                [$projectId, $userId]
            )['count'] > 0;
            
            if (!$hasAccess) {
                throw new Exception('您沒有此專案的權限');
            }
            
            $db->execute(
                'INSERT INTO project_discussions (project_id, user_id, content) VALUES (?, ?, ?)',
                [$projectId, $userId, $content]
            );
            
            jsonResponse(true, '討論發布成功！');
            
        } elseif ($action === 'get_discussions') {
            $projectId = getPostValueInt('project_id');
            
            // 檢查權限
            $hasAccess = $db->fetch(
                'SELECT COUNT(*) as count FROM tasks WHERE project_id = ? AND assigned_user_id = ?',
                [$projectId, $userId]
            )['count'] > 0;
            
            if (!$hasAccess) {
                throw new Exception('您沒有此專案的權限');
            }
            
            $discussions = $db->fetchAll(
                'SELECT pd.*, u.name as user_name FROM project_discussions pd 
                 JOIN users u ON pd.user_id = u.id 
                 WHERE pd.project_id = ? 
                 ORDER BY pd.created_at DESC LIMIT 50',
                [$projectId]
            );
            
            jsonResponse(true, '取得討論成功', $discussions);
            
        } elseif ($action === 'get_income_summary') {
            $month = getPostValue('month', date('Y-m'));
            
            // 獲取該月份的收入明細
            $incomeDetails = $db->fetchAll(
                'SELECT ir.*, p.name as project_name FROM income_records ir
                 JOIN projects p ON ir.project_id = p.id
                 WHERE ir.user_id = ? AND ir.income_month = ?
                 ORDER BY ir.created_at DESC',
                [$userId, $month]
            );
            
            // 計算總收入
            $totalIncome = array_sum(array_column($incomeDetails, 'amount'));
            
            jsonResponse(true, '取得收入明細成功', [
                'details' => $incomeDetails,
                'total' => $totalIncome,
                'month' => $month
            ]);
        } elseif ($action === 'create_project') {
            $name = getPostValueSanitized('name');
            $description = getPostValueSanitized('description');
            if (empty($name)) {
                throw new Exception('專案名稱不能為空');
            }
            $db->execute('INSERT INTO projects (name, description, created_by) VALUES (?, ?, ?)', [$name, $description, $userId]);
            jsonResponse(true, '專案建立成功！');
        } elseif ($action === 'create_task') {
            $title = getPostValueSanitized('title');
            $description = getPostValueSanitized('description');
            $assignedUserId = getPostValueInt('assigned_user_id');
            $projectId = getPostValueInt('project_id') ?: null;
            $dueDate = getPostValue('due_date') ?: null;
            if (empty($title) || !$assignedUserId) {
                throw new Exception('請填寫必要欄位');
            }
            $db->execute(
                'INSERT INTO tasks (title, description, assigned_user_id, project_id, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?)',
                [$title, $description, $assignedUserId, $projectId, $dueDate, $userId]
            );
            jsonResponse(true, '任務建立成功！');
        } elseif ($action === 'get_all_users') {
            $users = $db->fetchAll('SELECT id, name FROM users ORDER BY name');
            jsonResponse(true, '取得使用者列表成功', $users);
            
        } elseif ($action === 'check_in') {
            $currentDate = date('Y-m-d');
            $currentTime = date('Y-m-d H:i:s');
            
            // 檢查今天是否已經打卡
            $existingRecord = $db->fetch(
                'SELECT * FROM attendance_records WHERE user_id = ? AND work_date = ?',
                [$userId, $currentDate]
            );
            
            if ($existingRecord) {
                if ($existingRecord['status'] === 'checked_in') {
                    throw new Exception('您今天已經打過上班卡了');
                } elseif ($existingRecord['status'] === 'checked_out') {
                    throw new Exception('您今天已經下班了，無法重新上班');
                }
            }
            
            // 新增或更新打卡記錄
            if ($existingRecord) {
                $db->execute(
                    'UPDATE attendance_records SET check_in_time = ?, status = ?, updated_at = NOW() WHERE id = ?',
                    [$currentTime, 'checked_in', $existingRecord['id']]
                );
            } else {
                $db->execute(
                    'INSERT INTO attendance_records (user_id, work_date, check_in_time, status) VALUES (?, ?, ?, ?)',
                    [$userId, $currentDate, $currentTime, 'checked_in']
                );
            }
            
            jsonResponse(true, '上班打卡成功！', ['check_in_time' => $currentTime]);
            
        } elseif ($action === 'check_out') {
            $currentDate = date('Y-m-d');
            $currentTime = date('Y-m-d H:i:s');
            
            // 檢查今天的打卡記錄
            $existingRecord = $db->fetch(
                'SELECT * FROM attendance_records WHERE user_id = ? AND work_date = ?',
                [$userId, $currentDate]
            );
            
            if (!$existingRecord) {
                throw new Exception('您今天還沒有上班打卡');
            }
            
            if ($existingRecord['status'] !== 'checked_in') {
                throw new Exception('您今天還沒有上班或已經下班了');
            }
            
            // 計算工作時間
            $checkInTime = new DateTime($existingRecord['check_in_time']);
            $checkOutTime = new DateTime($currentTime);
            $workHours = ($checkOutTime->getTimestamp() - $checkInTime->getTimestamp()) / 3600;
            
            // 更新下班打卡
            $db->execute(
                'UPDATE attendance_records SET check_out_time = ?, work_hours = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [$currentTime, round($workHours, 2), 'checked_out', $existingRecord['id']]
            );
            
            jsonResponse(true, '下班打卡成功！', [
                'check_out_time' => $currentTime,
                'work_hours' => round($workHours, 2)
            ]);
            
        } elseif ($action === 'get_attendance_status') {
            $currentDate = date('Y-m-d');
            
            $record = $db->fetch(
                'SELECT * FROM attendance_records WHERE user_id = ? AND work_date = ?',
                [$userId, $currentDate]
            );
            
            jsonResponse(true, '取得打卡狀態成功', $record);
            
        } elseif ($action === 'get_attendance_summary') {
            $month = getPostValue('month', date('Y-m'));
            
            // 獲取該月份的打卡記錄
            $records = $db->fetchAll(
                'SELECT * FROM attendance_records 
                 WHERE user_id = ? AND work_date >= ? AND work_date < ? + INTERVAL 1 MONTH
                 ORDER BY work_date DESC',
                [$userId, $month . '-01', $month . '-01']
            );
            
            // 計算統計數據
            $totalDays = count($records);
            $checkedInDays = count(array_filter($records, fn($r) => $r['status'] !== 'absent'));
            $totalHours = array_sum(array_column($records, 'work_hours'));
            $avgHours = $checkedInDays > 0 ? $totalHours / $checkedInDays : 0;
            
            jsonResponse(true, '取得打卡統計成功', [
                'records' => $records,
                'stats' => [
                    'total_days' => $totalDays,
                    'checked_in_days' => $checkedInDays,
                    'total_hours' => round($totalHours, 2),
                    'avg_hours' => round($avgHours, 2)
                ],
                'month' => $month
            ]);
            
        } elseif ($action === 'start_overtime') {
            $workContent = getPostValue('work_content');
            $currentTime = date('Y-m-d H:i:s');
            $currentDate = date('Y-m-d');
            
            if (empty(trim($workContent))) {
                throw new Exception('請填寫加班工作內容');
            }
            
            // 檢查今天是否有進行中的加班
            $existingOvertime = $db->fetch(
                'SELECT * FROM overtime_records WHERE user_id = ? AND work_date = ? AND status = "started"',
                [$userId, $currentDate]
            );
            
            if ($existingOvertime) {
                throw new Exception('您今天已有進行中的加班記錄');
            }
            
            // 新增加班記錄
            $db->execute(
                'INSERT INTO overtime_records (user_id, work_date, start_time, work_content, status) VALUES (?, ?, ?, ?, ?)',
                [$userId, $currentDate, $currentTime, $workContent, 'started']
            );
            
            jsonResponse(true, '加班開始記錄成功！', ['start_time' => $currentTime]);
            
        } elseif ($action === 'end_overtime') {
            $overtimeId = getPostValueInt('overtime_id');
            $currentTime = date('Y-m-d H:i:s');
            
            // 取得加班記錄
            $overtime = $db->fetch(
                'SELECT * FROM overtime_records WHERE id = ? AND user_id = ? AND status = "started"',
                [$overtimeId, $userId]
            );
            
            if (!$overtime) {
                throw new Exception('找不到進行中的加班記錄');
            }
            
            // 計算加班時數
            $startTime = strtotime($overtime['start_time']);
            $endTime = strtotime($currentTime);
            $overtimeHours = round(($endTime - $startTime) / 3600, 2);
            
            // 更新加班記錄
            $db->execute(
                'UPDATE overtime_records SET end_time = ?, overtime_hours = ?, status = "ended", updated_at = NOW() WHERE id = ?',
                [$currentTime, $overtimeHours, $overtimeId]
            );
            
            jsonResponse(true, '加班結束記錄成功！', [
                'end_time' => $currentTime,
                'overtime_hours' => $overtimeHours
            ]);
            
        } elseif ($action === 'get_overtime_status') {
            $currentDate = date('Y-m-d');
            
            // 取得今日加班記錄
            $records = $db->fetchAll(
                'SELECT * FROM overtime_records WHERE user_id = ? AND work_date = ? ORDER BY start_time DESC',
                [$userId, $currentDate]
            );
            
            jsonResponse(true, '取得加班狀態成功', $records);
            
        } elseif ($action === 'get_overtime_summary') {
            $month = getPostValue('month', date('Y-m'));
            
            // 獲取該月份的加班記錄
            $records = $db->fetchAll(
                'SELECT * FROM overtime_records 
                 WHERE user_id = ? AND work_date >= ? AND work_date < ? + INTERVAL 1 MONTH
                 ORDER BY work_date DESC, start_time DESC',
                [$userId, $month . '-01', $month . '-01']
            );
            
            // 計算統計數據
            $totalDays = count(array_unique(array_column($records, 'work_date')));
            $totalHours = array_sum(array_column($records, 'overtime_hours'));
            $totalSessions = count($records);
            
            jsonResponse(true, '取得加班統計成功', [
                'records' => $records,
                'stats' => [
                    'total_days' => $totalDays,
                    'total_sessions' => $totalSessions,
                    'total_hours' => round($totalHours, 2)
                ],
                'month' => $month
            ]);
        }
        
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage());
    }
    
    exit;
}

// 取得使用者的任務和專案
$tasks = $db->fetchAll(
    'SELECT t.*, p.name as project_name, p.id as project_id FROM tasks t 
     LEFT JOIN projects p ON t.project_id = p.id 
     WHERE t.assigned_user_id = ? 
     ORDER BY t.created_at DESC',
    [$userId]
);

// 取得使用者參與的所有專案
$userProjects = $db->fetchAll(
    'SELECT DISTINCT p.* FROM projects p
     JOIN tasks t ON p.id = t.project_id
     WHERE t.assigned_user_id = ?
     ORDER BY p.name',
    [$userId]
);

// 取得當月收入分成設定
$currentMonth = date('Y-m');
$commissions = $db->fetchAll(
    'SELECT pc.*, p.name as project_name FROM project_commissions pc
     JOIN projects p ON pc.project_id = p.id
     WHERE pc.user_id = ?
     ORDER BY p.name',
    [$userId]
);

// 取得啟用的公告（限制顯示前5條，以便測試"查看全部"功能）
$announcements = $db->fetchAll(
    'SELECT a.*, u.name as created_by_name FROM announcements a
     JOIN users u ON a.created_by = u.id
     WHERE a.is_active = 1
     ORDER BY a.priority DESC, a.created_at DESC
     LIMIT 5'
);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>員工面板 - WorkLog Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <!-- 富文本編輯器 -->
    <script src="https://cdn.tiny.cloud/1/sydjdldkoxd6ws2x6gfqtqkdrtkas4kf1e1mfwqrebyk4e57/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        body {
            background-color: #f8f9fc;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.25rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        .task-card {
            border-left: 4px solid #667eea;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .status-pending { border-left-color: #6c757d !important; }
        .status-in_progress { border-left-color: #ffc107 !important; }
        .status-completed { border-left-color: #28a745 !important; }
        .discussion-item {
            border-left: 3px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
        }
        .income-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        /* RWD 優化 */
        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }
            
            /* 導航欄優化 */
            .navbar-brand {
                font-size: 1.1rem;
            }
            
            #attendance-display {
                font-size: 0.8rem;
            }
            
            #work-time {
                font-size: 0.7rem;
            }
            
            /* 功能導航優化 */
            .nav-pills .nav-link {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }
            
            .nav-pills .nav-link i {
                display: block;
                margin-bottom: 0.2rem;
            }
            
            /* 統計卡片優化 */
            .card-body h2 {
                font-size: 1.5rem;
            }
            
            .card-body h5 {
                font-size: 0.9rem;
            }
            
            /* 任務卡片優化 */
            .task-card .card-body {
                padding: 1rem;
            }
            
            .task-card .btn {
                font-size: 0.8rem;
                padding: 0.4rem 0.6rem;
                margin-bottom: 0.3rem;
            }
            
            .task-card .d-flex {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            /* 打卡按鈕優化 */
            #check-in-btn, #check-out-btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .d-flex.gap-3.justify-content-center {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            /* 表格優化 */
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .table th, .table td {
                padding: 0.5rem;
                vertical-align: middle;
            }
            
            /* Modal 優化 */
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            /* 專案討論區優化 */
            .discussion-item {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            
            /* 收入統計優化 */
            .income-card h2 {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 576px) {
            /* 超小螢幕優化 */
            .card-body {
                padding: 0.75rem;
            }
            
            .nav-pills .nav-link {
                font-size: 0.75rem;
                padding: 0.4rem 0.5rem;
            }
            
            .btn-group-vertical .btn {
                font-size: 0.75rem;
            }
            
            .task-card h6 {
                font-size: 0.9rem;
            }
            
            .badge {
                font-size: 0.65rem;
            }
            
            /* 隱藏不必要的圖標 */
            .fa-2x, .fa-3x {
                font-size: 1.5rem !important;
            }
        }
        
        /* 大螢幕優化 */
        @media (min-width: 1200px) {
            .container {
                max-width: 1400px;
            }
            
            .card-body {
                padding: 2rem;
            }
            
            .task-card {
                transition: all 0.3s ease;
            }
            
            .task-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 0.5rem 3rem 0 rgba(58, 59, 69, 0.25);
            }

            /* 專案討論區 RWD */
            .project-item {
                border-radius: 0.375rem !important;
                margin-bottom: 0.25rem;
                transition: all 0.2s ease;
            }

            .project-item:hover {
                background-color: var(--bs-primary) !important;
                color: white !important;
                transform: translateX(4px);
            }

            /* 討論卡片優化 */
            .discussions-container {
                max-height: 500px;
                overflow-y: auto;
            }

            @media (max-width: 768px) {
                .discussions-container {
                    max-height: 400px;
                }
                
                .project-item {
                    padding: 0.75rem 1rem;
                    text-align: center;
                }
                
                #discussions .col-lg-3 {
                    order: 2;
                    margin-top: 1rem;
                }
                
                #discussions .col-lg-9 {
                    order: 1;
                }
            }

            @media (max-width: 576px) {
                .project-item {
                    padding: 0.5rem;
                    font-size: 0.875rem;
                }
                
                #current-project-name {
                    font-size: 0.875rem;
                }
            }

            /* 工作報告 Modal RWD */
            .modal-dialog {
                margin: 1rem;
            }

            @media (max-width: 576px) {
                .modal-dialog {
                    margin: 0.5rem;
                }
                
                .modal-body {
                    padding: 1rem;
                }
                
                .form-check-label {
                    font-size: 0.875rem;
                }
                
                .alert {
                    padding: 0.75rem;
                    font-size: 0.875rem;
                }
                
                .history-content-responsive .card {
                    margin-bottom: 0.75rem;
                }
                
                .history-content-responsive .card-body {
                    padding: 0.75rem;
                }
                
                .history-content-responsive .badge {
                    font-size: 0.75rem;
                }
            }

            /* 任務卡片 RWD 優化 */
            .task-card .btn-group {
                flex-wrap: wrap;
                gap: 0.25rem;
            }

            @media (max-width: 768px) {
                .task-card .btn-group .btn {
                    font-size: 0.75rem;
                    padding: 0.25rem 0.5rem;
                }
                
                .task-card .btn-group .btn span.d-none.d-sm-inline {
                    display: none !important;
                }
            }

            /* 公告卡片樣式 */
            .announcement-card {
                transition: all 0.3s ease;
                border-left: 4px solid transparent;
            }

            .announcement-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }

            .announcement-preview {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            @media (max-width: 768px) {
                .announcement-card .card-body {
                    padding: 0.75rem !important;
                }
                
                .announcement-card .card-title {
                    font-size: 0.9rem;
                }
                
                .announcement-preview {
                    font-size: 0.8rem;
                    height: 2rem !important;
                }
            }
        }
        
        /* 平板橫向優化 */
        @media (min-width: 768px) and (max-width: 1024px) {
            .col-md-6 {
                margin-bottom: 1rem;
            }
            
            .nav-pills .nav-link {
                font-size: 0.9rem;
            }
        }
        
        /* 打印樣式 */
        @media print {
            .navbar, .nav-pills, .btn, .modal {
                display: none !important;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
        }
    </style>
</head>
<body>
    <!-- 導航列 -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-clipboard-list me-2"></i><span class="d-none d-sm-inline">WorkLog Manager</span><span class="d-sm-none">WLM</span>
            </a>
            
            <!-- 手機版切換按鈕 -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <!-- 打卡狀態顯示 -->
                    <div class="nav-item me-3">
                        <div class="nav-link text-white" id="attendance-status">
                            <div id="attendance-display">
                                <i class="fas fa-clock me-1"></i>
                                <span id="status-text">檢查中...</span>
                                <div id="work-time" class="small" style="display: none;">
                                    工作時間: <span id="work-duration">00:00:00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i><span class="d-none d-md-inline"><?php echo $_SESSION['user_name']; ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if (isAdmin()): ?>
                            <li><a class="dropdown-item" href="admin.php">
                                <i class="fas fa-cog me-2"></i>系統管理
                            </a></li>
                            <li><a class="dropdown-item" href="attendance_admin.php">
                                <i class="fas fa-chart-line me-2"></i>出勤管理
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>登出
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- 最新公告區塊 -->
        <?php if (!empty($announcements)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bullhorn me-2"></i>最新公告
                        </h5>
                        <?php if (count($announcements) > 3): ?>
                            <button class="btn btn-outline-light btn-sm" onclick="document.getElementById('announcements-tab').click()">
                                <i class="fas fa-arrow-right me-1"></i>查看全部
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-2">
                        <div class="row g-2">
                            <?php 
                            $displayAnnouncements = array_slice($announcements, 0, 3);
                            foreach ($displayAnnouncements as $index => $announcement): 
                                $priorityClass = '';
                                $priorityIcon = '';
                                switch ($announcement['priority']) {
                                    case 'urgent':
                                        $priorityClass = 'border-danger bg-danger bg-opacity-10';
                                        $priorityIcon = 'fas fa-exclamation-triangle text-danger';
                                        break;
                                    case 'high':
                                        $priorityClass = 'border-warning bg-warning bg-opacity-10';
                                        $priorityIcon = 'fas fa-exclamation-circle text-warning';
                                        break;
                                    case 'normal':
                                        $priorityClass = 'border-info bg-info bg-opacity-10';
                                        $priorityIcon = 'fas fa-info-circle text-info';
                                        break;
                                    case 'low':
                                        $priorityClass = 'border-secondary bg-light';
                                        $priorityIcon = 'fas fa-info text-secondary';
                                        break;
                                }
                            ?>
                            <div class="col-lg-4 col-md-6 col-12">
                                <div class="card h-100 <?php echo $priorityClass; ?> announcement-card" style="cursor: pointer;" 
                                     onclick="showAnnouncementModal(<?php echo htmlspecialchars(json_encode($announcement)); ?>)">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-start mb-2">
                                            <i class="<?php echo $priorityIcon; ?> me-2 mt-1"></i>
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1 text-truncate"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                <div class="announcement-preview text-muted small" style="height: 2.4rem; overflow: hidden; line-height: 1.2;">
                                                    <?php 
                                                    if ($announcement['is_rich_text']) {
                                                        echo mb_substr(strip_tags($announcement['content']), 0, 60) . '...';
                                                    } else {
                                                        echo mb_substr(htmlspecialchars($announcement['content']), 0, 60) . '...';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i><?php echo date('m/d H:i', strtotime($announcement['created_at'])); ?>
                                            </small>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($announcement['created_by_name']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 歡迎區塊 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-primary">歡迎回來，<?php echo $_SESSION['user_name']; ?>！</h3>
                        <p class="text-muted">今天是 <?php echo date('Y年m月d日'); ?>，開始您的工作回報吧！</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 功能導航 -->
        <ul class="nav nav-pills nav-justified mb-4" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tasks-tab" data-bs-toggle="pill" data-bs-target="#tasks" type="button">
                    <i class="fas fa-tasks me-2"></i><span class="d-none d-md-inline">我的任務</span><span class="d-md-none">任務</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="announcements-tab" data-bs-toggle="pill" data-bs-target="#announcements" type="button">
                    <i class="fas fa-bullhorn me-2"></i><span class="d-none d-md-inline">最新公告</span><span class="d-md-none">公告</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="attendance-tab" data-bs-toggle="pill" data-bs-target="#attendance" type="button">
                    <i class="fas fa-clock me-2"></i><span class="d-none d-md-inline">打卡系統</span><span class="d-md-none">打卡</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="discussions-tab" data-bs-toggle="pill" data-bs-target="#discussions" type="button">
                    <i class="fas fa-comments me-2"></i><span class="d-none d-md-inline">專案討論</span><span class="d-md-none">討論</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="income-tab" data-bs-toggle="pill" data-bs-target="#income" type="button">
                    <i class="fas fa-money-bill-wave me-2"></i><span class="d-none d-md-inline">收入明細</span><span class="d-md-none">收入</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="creation-tab" data-bs-toggle="pill" data-bs-target="#creation" type="button">
                    <i class="fas fa-plus-circle me-2"></i><span class="d-none d-md-inline">快速新增</span><span class="d-md-none">新增</span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="mainTabContent">
            <!-- 任務面板 -->
            <div class="tab-pane fade show active" id="tasks" role="tabpanel">
                <!-- 統計卡片 -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title">總任務數</h5>
                                        <h2 class="mb-0"><?php echo count($tasks); ?></h2>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-tasks fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title">進行中</h5>
                                        <h2 class="mb-0"><?php echo count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress')); ?></h2>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-clock fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title">已完成</h5>
                                        <h2 class="mb-0"><?php echo count(array_filter($tasks, fn($t) => $t['status'] === 'completed')); ?></h2>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 任務列表 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list me-2"></i>我的任務列表
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($tasks)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">目前沒有指派給您的任務</h5>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($tasks as $task): ?>
                                            <div class="col-md-6 mb-4">
                                                <div class="card task-card status-<?php echo $task['status']; ?>">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                                            <h6 class="card-title mb-0"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                            <span class="<?php echo getStatusBadge($task['status']); ?>">
                                                                <?php echo getStatusText($task['status']); ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <?php if ($task['project_name']): ?>
                                                            <p class="text-muted mb-2">
                                                                <i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($task['project_name']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($task['description']): ?>
                                                            <p class="card-text small text-muted mb-3"><?php echo htmlspecialchars($task['description']); ?></p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($task['due_date']): ?>
                                                            <p class="text-muted mb-3">
                                                                <i class="fas fa-calendar me-1"></i>截止日期：<?php echo $task['due_date']; ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button class="btn btn-primary btn-sm" onclick="openReportModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>')">
                                                                <i class="fas fa-plus me-1"></i>
                                                                <span class="d-none d-sm-inline">新增回報</span>
                                                                <span class="d-sm-none">回報</span>
                                                            </button>
                                                            <button class="btn btn-outline-secondary btn-sm" onclick="viewReports(<?php echo $task['id']; ?>)">
                                                                <i class="fas fa-history me-1"></i>
                                                                <span class="d-none d-sm-inline">查看紀錄</span>
                                                                <span class="d-sm-none">紀錄</span>
                                                            </button>
                                                            <?php if ($task['project_id']): ?>
                                                                <button class="btn btn-outline-info btn-sm" onclick="switchToDiscussion(<?php echo $task['project_id']; ?>)">
                                                                    <i class="fas fa-comments me-1"></i>
                                                                    <span class="d-none d-sm-inline">專案討論</span>
                                                                    <span class="d-sm-none">討論</span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 公告面板 -->
            <div class="tab-pane fade" id="announcements" role="tabpanel">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-bullhorn me-2"></i>最新公告
                                    <span class="badge bg-primary ms-2"><?php echo count($announcements); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($announcements)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">目前沒有公告</h5>
                                        <p class="text-muted">管理員尚未發布任何公告</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($announcements as $announcement): ?>
                                            <?php
                                            $priorityClass = '';
                                            $priorityText = '';
                                            switch ($announcement['priority']) {
                                                case 'urgent':
                                                    $priorityClass = 'border-danger';
                                                    $priorityText = '緊急';
                                                    break;
                                                case 'high':
                                                    $priorityClass = 'border-warning';
                                                    $priorityText = '高';
                                                    break;
                                                case 'normal':
                                                    $priorityClass = 'border-primary';
                                                    $priorityText = '普通';
                                                    break;
                                                case 'low':
                                                    $priorityClass = 'border-secondary';
                                                    $priorityText = '低';
                                                    break;
                                            }
                                            ?>
                                            <div class="col-lg-6 col-md-12 mb-4">
                                                <div class="card h-100 <?php echo $priorityClass; ?>" style="border-left: 4px solid;">
                                                    <div class="card-header d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0 text-truncate me-2"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                        <span class="badge 
                                                            <?php 
                                                            echo $announcement['priority'] === 'urgent' ? 'bg-danger' : 
                                                                ($announcement['priority'] === 'high' ? 'bg-warning text-dark' : 
                                                                ($announcement['priority'] === 'normal' ? 'bg-primary' : 'bg-secondary')); 
                                                            ?>">
                                                            <?php echo $priorityText; ?>
                                                        </span>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="announcement-content" style="max-height: 200px; overflow-y: auto;">
                                                            <?php if ($announcement['is_rich_text']): ?>
                                                                <?php echo $announcement['content']; ?>
                                                            <?php else: ?>
                                                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <hr>
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($announcement['created_by_name']); ?>
                                                            <i class="fas fa-clock ms-3 me-1"></i><?php echo date('Y/m/d H:i', strtotime($announcement['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 打卡系統面板 -->
            <div class="tab-pane fade" id="attendance" role="tabpanel">
                <!-- 今日打卡狀態 -->
                <div class="row mb-4">
                    <div class="col-lg-8 col-md-12 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-day me-2"></i>今日打卡
                                    <span class="text-muted small ms-2 d-none d-md-inline"><?php echo date('Y年m月d日 l'); ?></span>
                                    <span class="text-muted small ms-2 d-md-none"><?php echo date('m/d'); ?></span>
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <div id="attendance-info" class="mb-4">
                                    <!-- 動態載入打卡信息 -->
                                </div>
                                <div class="d-flex gap-3 justify-content-center flex-column flex-md-row">
                                    <button class="btn btn-success btn-lg" id="check-in-btn" onclick="performCheckIn()">
                                        <i class="fas fa-sign-in-alt me-2"></i><span class="d-none d-sm-inline">上班打卡</span><span class="d-sm-none">上班</span>
                                    </button>
                                    <button class="btn btn-danger btn-lg" id="check-out-btn" onclick="performCheckOut()" disabled>
                                        <i class="fas fa-sign-out-alt me-2"></i><span class="d-none d-sm-inline">下班打卡</span><span class="d-sm-none">下班</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-12">
                        <div class="row">
                            <div class="col-6 col-lg-12 mb-3">
                                <div class="card text-white bg-info">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">本月出勤天數</h6>
                                        <h3 class="mb-0" id="monthly-days">-</h3>
                                        <small>天</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-12">
                                <div class="card text-white bg-warning">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">本月總工時</h6>
                                        <h3 class="mb-0" id="monthly-hours">-</h3>
                                        <small>小時</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 加班系統 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-moon me-2"></i>加班管理
                                    <span class="text-muted small ms-2 d-none d-md-inline">今日加班記錄</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="overtime-info" class="mb-3">
                                    <!-- 動態載入加班信息 -->
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="overtime-content" class="form-label">加班工作內容 <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="overtime-content" rows="3" 
                                                placeholder="請詳細描述加班工作內容..." required></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-flex gap-2 flex-column">
                                            <button class="btn btn-warning btn-lg" id="start-overtime-btn" onclick="startOvertime()">
                                                <i class="fas fa-play me-2"></i>開始加班
                                            </button>
                                            <button class="btn btn-secondary btn-lg" id="end-overtime-btn" onclick="endOvertime()" disabled>
                                                <i class="fas fa-stop me-2"></i>結束加班
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="card text-white bg-dark">
                                            <div class="card-body text-center py-2">
                                                <h6 class="card-title mb-1">今日加班次數</h6>
                                                <h4 class="mb-0" id="today-overtime-sessions">0</h4>
                                                <small>次</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card text-white bg-warning">
                                            <div class="card-body text-center py-2">
                                                <h6 class="card-title mb-1">今日加班時數</h6>
                                                <h4 class="mb-0" id="today-overtime-hours">0</h4>
                                                <small>小時</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 打卡與加班記錄 -->
                <div class="row">
                    <div class="col-lg-4 col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-column flex-md-row">
                                <h6 class="mb-2 mb-md-0"><i class="fas fa-list me-2"></i>本月打卡記錄</h6>
                                <input type="month" class="form-control form-control-sm" id="attendance-month-select" 
                                       value="<?php echo date('Y-m'); ?>" onchange="loadAttendanceData()" style="width: auto; min-width: 150px;">
                            </div>
                            <div class="card-body">
                                <div id="attendance-records-content" style="max-height: 400px; overflow-y: auto;">
                                    <div class="text-center py-3">
                                        <i class="fas fa-spinner fa-spin"></i> 載入中...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-column flex-md-row">
                                <h6 class="mb-2 mb-md-0"><i class="fas fa-moon me-2"></i>本月加班記錄</h6>
                                <input type="month" class="form-control form-control-sm" id="overtime-month-select" 
                                       value="<?php echo date('Y-m'); ?>" onchange="loadOvertimeData()" style="width: auto; min-width: 150px;">
                            </div>
                            <div class="card-body">
                                <div id="overtime-records-content" style="max-height: 400px; overflow-y: auto;">
                                    <div class="text-center py-3">
                                        <i class="fas fa-spinner fa-spin"></i> 載入中...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>統計圖表</h6>
                            </div>
                            <div class="card-body">
                                <div id="attendance-stats">
                                    <!-- 統計數據將在這裡顯示 -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 專案討論面板 -->
            <div class="tab-pane fade" id="discussions" role="tabpanel">
                <div class="row">
                    <div class="col-lg-3 col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">我的專案</h6>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($userProjects as $project): ?>
                                    <a href="#" class="list-group-item list-group-item-action project-item" 
                                       data-project-id="<?php echo $project['id']; ?>"
                                       data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                                        <i class="fas fa-folder me-2"></i>
                                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($project['name']); ?></span>
                                        <span class="d-md-none"><?php echo mb_substr(htmlspecialchars($project['name']), 0, 8) . (mb_strlen($project['name']) > 8 ? '...' : ''); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-9 col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-column flex-md-row">
                                <h6 class="mb-2 mb-md-0">
                                    <i class="fas fa-comments me-2"></i>
                                    <span id="current-project-name">請選擇專案</span>
                                </h6>
                                <button class="btn btn-primary btn-sm" id="new-discussion-btn" style="display:none;" data-bs-toggle="modal" data-bs-target="#discussionModal">
                                    <i class="fas fa-plus me-2"></i><span class="d-none d-sm-inline">發布討論</span><span class="d-sm-none">發布</span>
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="discussions-content">
                                    <div class="text-center py-5">
                                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">選擇左側專案開始討論</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 收入明細面板 -->
            <div class="tab-pane fade" id="income" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card income-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">本月收入</h5>
                                <h2 class="mb-0" id="current-month-income">NT$ 0</h2>
                                <small id="current-month-text"><?php echo date('Y年m月'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <label for="income-month-select" class="form-label">選擇月份</label>
                                <input type="month" class="form-control" id="income-month-select" value="<?php echo date('Y-m'); ?>" onchange="loadIncomeData()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 分成設定 -->
                <?php if (!empty($commissions)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-percentage me-2"></i>我的分成設定</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>專案名稱</th>
                                                <th>分成比例</th>
                                                <th>基本金額</th>
                                                <th>獎金</th>
                                                <th>備註</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($commissions as $commission): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($commission['project_name']); ?></td>
                                                    <td><?php echo $commission['commission_percentage']; ?>%</td>
                                                    <td>NT$ <?php echo number_format($commission['base_amount']); ?></td>
                                                    <td>NT$ <?php echo number_format($commission['bonus_amount']); ?></td>
                                                    <td><?php echo htmlspecialchars($commission['notes'] ?? ''); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 收入明細 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-list me-2"></i>收入明細</h6>
                            </div>
                            <div class="card-body">
                                <div id="income-details-content">
                                    <div class="text-center py-3">
                                        <i class="fas fa-spinner fa-spin"></i> 載入中...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 快速新增面板 -->
            <div class="tab-pane fade" id="creation" role="tabpanel">
                <div class="row">
                    <!-- 新增專案 -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-folder-plus me-2"></i>新增專案</h6>
                            </div>
                            <div class="card-body">
                                <form id="userProjectForm">
                                    <div class="mb-3">
                                        <label for="userProjectName" class="form-label">專案名稱 *</label>
                                        <input type="text" class="form-control" id="userProjectName" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="userProjectDescription" class="form-label">專案描述</label>
                                        <textarea class="form-control" id="userProjectDescription" name="description" rows="3"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">建立專案</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- 新增任務 -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>新增任務</h6>
                            </div>
                            <div class="card-body">
                                <form id="userTaskForm">
                                    <div class="mb-3">
                                        <label for="userTaskTitle" class="form-label">任務標題 *</label>
                                        <input type="text" class="form-control" id="userTaskTitle" name="title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="userTaskDescription" class="form-label">任務描述</label>
                                        <textarea class="form-control" id="userTaskDescription" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="userAssignedUser" class="form-label">指派同事 *</label>
                                        <select class="form-select" id="userAssignedUser" name="assigned_user_id" required>
                                            <option value="">正在載入同事列表...</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="userTaskProject" class="form-label">所屬專案</label>
                                        <select class="form-select" id="userTaskProject" name="project_id">
                                            <option value="">無專案</option>
                                            <?php foreach ($userProjects as $project): ?>
                                                <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="userDueDate" class="form-label">截止日期</label>
                                        <input type="date" class="form-control" id="userDueDate" name="due_date">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">建立任務</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 工作回報 Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>工作回報
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reportForm">
                    <div class="modal-body">
                        <input type="hidden" id="reportTaskId" name="task_id">
                        
                        <div class="mb-3">
                            <label class="form-label">任務名稱</label>
                            <input type="text" class="form-control" id="reportTaskTitle" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reportContent" class="form-label">工作內容 *</label>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="useRichText" onchange="toggleEditor()">
                                <label class="form-check-label" for="useRichText">
                                    <span class="d-none d-sm-inline">使用富文本編輯器</span>
                                    <span class="d-sm-none">富文本</span>
                                </label>
                            </div>
                            <textarea class="form-control" id="reportContent" name="content" rows="6" required 
                                placeholder="請描述工作進度、遇到的問題或完成的成果..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reportStatus" class="form-label">狀態 *</label>
                            <select class="form-select" id="reportStatus" name="status" required>
                                <option value="pending">未開始</option>
                                <option value="in_progress">進行中</option>
                                <option value="completed">已完成</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <span class="d-none d-md-inline">每次提交都會建立新的工作回報記錄，您可以針對同一個任務提交多次回報來記錄工作進度</span>
                            <span class="d-md-none">可針對同一任務多次提交工作回報</span>
                        </div>
                    </div>
                    <div class="modal-footer flex-column flex-sm-row">
                        <button type="button" class="btn btn-secondary w-100 w-sm-auto mb-2 mb-sm-0" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary w-100 w-sm-auto">
                            <i class="fas fa-save me-2"></i>提交回報
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 歷史回報 Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2"></i>
                        <span class="d-none d-sm-inline">歷史回報紀錄</span>
                        <span class="d-sm-none">歷史回報</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="historyContent" class="history-content-responsive">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                            <p class="mt-2">載入中...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 公告詳情 Modal -->
    <div class="modal fade" id="announcementDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-bullhorn me-2"></i>
                        <span id="modalAnnouncementTitle">公告詳情</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="badge" id="modalAnnouncementPriority">普通</span>
                        </div>
                        <div class="text-muted small">
                            <i class="fas fa-user me-1"></i><span id="modalAnnouncementAuthor"></span>
                            <i class="fas fa-clock ms-3 me-1"></i><span id="modalAnnouncementDate"></span>
                        </div>
                    </div>
                    <div id="modalAnnouncementContent" class="announcement-content">
                        <!-- 公告內容將在這裡顯示 -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 專案討論 Modal -->
    <div class="modal fade" id="discussionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>發布討論
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="discussionForm">
                    <div class="modal-body">
                        <input type="hidden" id="discussionProjectId" name="project_id">
                        
                        <div class="mb-3">
                            <label for="discussionContent" class="form-label">討論內容 *</label>
                            <textarea class="form-control" id="discussionContent" name="content" rows="5" required 
                                placeholder="分享想法、提出問題、討論進度..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>發布
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        let currentProjectId = null;
        let richTextEditor = null;
        let attendanceTimer = null;
        let attendanceStartTime = null;
        
        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 載入當月收入
            loadIncomeData();
            loadAllUsers();
            
            // 初始化打卡系統
            initializeAttendance();
            
            // 專案選擇事件
            document.querySelectorAll('.project-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const projectId = this.dataset.projectId;
                    const projectName = this.dataset.projectName;
                    selectProject(projectId, projectName);
                });
            });

            // 員工新增專案表單
            document.getElementById('userProjectForm').addEventListener('submit', function(e) {
                ajaxSubmit(e, 'create_project', () => {
                    Swal.fire('成功', '專案已建立', 'success').then(() => location.reload());
                });
            });

            // 員工新增任務表單
            document.getElementById('userTaskForm').addEventListener('submit', function(e) {
                ajaxSubmit(e, 'create_task', () => {
                    Swal.fire('成功', '任務已建立並指派', 'success').then(() => location.reload());
                });
            });
        });

        // 初始化打卡系統
        function initializeAttendance() {
            loadAttendanceStatus();
            loadAttendanceData();
            loadOvertimeStatus();
            loadOvertimeData();
            
            // 每30秒更新一次狀態
            setInterval(loadAttendanceStatus, 30000);
            setInterval(loadOvertimeStatus, 30000);
        }

        // 載入打卡狀態
        function loadAttendanceStatus() {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_attendance_status');
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateAttendanceUI(data.data);
                }
            })
            .catch(error => console.error('載入打卡狀態失敗:', error));
        }

        // 更新打卡界面
        function updateAttendanceUI(record) {
            const statusText = document.getElementById('status-text');
            const workTime = document.getElementById('work-time');
            const workDuration = document.getElementById('work-duration');
            const checkInBtn = document.getElementById('check-in-btn');
            const checkOutBtn = document.getElementById('check-out-btn');
            const attendanceInfo = document.getElementById('attendance-info');
            
            if (!record) {
                // 未打卡
                statusText.innerHTML = '<span class="text-warning">未打卡</span>';
                workTime.style.display = 'none';
                checkInBtn.disabled = false;
                checkOutBtn.disabled = true;
                attendanceInfo.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        您今天還沒有打卡，請點擊上班打卡開始工作
                    </div>
                `;
                
                // 停止計時器
                if (attendanceTimer) {
                    clearInterval(attendanceTimer);
                    attendanceTimer = null;
                }
            } else if (record.status === 'checked_in') {
                // 已上班
                statusText.innerHTML = '<span class="text-success">已上班</span>';
                workTime.style.display = 'block';
                checkInBtn.disabled = true;
                checkOutBtn.disabled = false;
                
                const checkInTime = new Date(record.check_in_time);
                attendanceStartTime = checkInTime;
                
                attendanceInfo.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>上班時間：</strong>${checkInTime.toLocaleString('zh-TW')}
                        <br>
                        <small class="text-muted">請記得下班時打卡</small>
                    </div>
                `;
                
                // 開始計時器
                startWorkTimer();
            } else if (record.status === 'checked_out') {
                // 已下班
                statusText.innerHTML = '<span class="text-secondary">已下班</span>';
                workTime.style.display = 'none';
                checkInBtn.disabled = true;
                checkOutBtn.disabled = true;
                
                const checkInTime = new Date(record.check_in_time);
                const checkOutTime = new Date(record.check_out_time);
                
                attendanceInfo.innerHTML = `
                    <div class="alert alert-secondary">
                        <i class="fas fa-check-double me-2"></i>
                        <strong>上班時間：</strong>${checkInTime.toLocaleString('zh-TW')}
                        <br>
                        <strong>下班時間：</strong>${checkOutTime.toLocaleString('zh-TW')}
                        <br>
                        <strong>工作時數：</strong>${record.work_hours} 小時
                    </div>
                `;
                
                // 停止計時器
                if (attendanceTimer) {
                    clearInterval(attendanceTimer);
                    attendanceTimer = null;
                }
            }
        }

        // 開始工作計時器
        function startWorkTimer() {
            if (attendanceTimer) {
                clearInterval(attendanceTimer);
            }
            
            attendanceTimer = setInterval(function() {
                if (attendanceStartTime) {
                    const now = new Date();
                    const diff = now - attendanceStartTime;
                    const hours = Math.floor(diff / 3600000);
                    const minutes = Math.floor((diff % 3600000) / 60000);
                    const seconds = Math.floor((diff % 60000) / 1000);
                    
                    const timeString = String(hours).padStart(2, '0') + ':' + 
                                     String(minutes).padStart(2, '0') + ':' + 
                                     String(seconds).padStart(2, '0');
                    
                    document.getElementById('work-duration').textContent = timeString;
                }
            }, 1000);
        }

        // 上班打卡
        function performCheckIn() {
            Swal.fire({
                title: '確認上班打卡',
                text: '確定要進行上班打卡嗎？',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '確定打卡',
                cancelButtonText: '取消'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'check_in');
                    
                    fetch('dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '上班打卡成功！',
                                text: '祝您今天工作順利',
                                confirmButtonColor: '#667eea'
                            });
                            loadAttendanceStatus();
                            loadAttendanceData();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '打卡失敗',
                                text: data.message,
                                confirmButtonColor: '#667eea'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('打卡錯誤:', error);
                        Swal.fire({
                            icon: 'error',
                            title: '網路錯誤',
                            text: '打卡失敗，請重試',
                            confirmButtonColor: '#667eea'
                        });
                    });
                }
            });
        }

        // 下班打卡
        function performCheckOut() {
            Swal.fire({
                title: '確認下班打卡',
                text: '確定要進行下班打卡嗎？',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '確定打卡',
                cancelButtonText: '取消'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'check_out');
                    
                    fetch('dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '下班打卡成功！',
                                html: `今日工作時數：<strong>${data.data.work_hours}</strong> 小時<br>辛苦了！`,
                                confirmButtonColor: '#667eea'
                            });
                            loadAttendanceStatus();
                            loadAttendanceData();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '打卡失敗',
                                text: data.message,
                                confirmButtonColor: '#667eea'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('打卡錯誤:', error);
                        Swal.fire({
                            icon: 'error',
                            title: '網路錯誤',
                            text: '打卡失敗，請重試',
                            confirmButtonColor: '#667eea'
                        });
                    });
                }
            });
        }

        // 載入打卡數據
        function loadAttendanceData() {
            const month = document.getElementById('attendance-month-select').value;
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_attendance_summary');
            formData.append('month', month);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayAttendanceData(data.data);
                } else {
                    console.error('載入打卡數據失敗:', data.message);
                }
            })
            .catch(error => console.error('載入打卡數據失敗:', error));
        }

        // 顯示打卡數據
        function displayAttendanceData(data) {
            // 更新統計數字
            document.getElementById('monthly-days').textContent = data.stats.checked_in_days;
            document.getElementById('monthly-hours').textContent = data.stats.total_hours;
            
            // 顯示記錄列表
            const content = document.getElementById('attendance-records-content');
            
            if (data.records.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">本月暫無打卡記錄</h5>
                    </div>
                `;
                return;
            }
            
            let html = '';
            data.records.forEach(record => {
                const statusIcon = getAttendanceStatusIcon(record.status);
                const statusText = getAttendanceStatusText(record.status);
                const statusClass = getAttendanceStatusClass(record.status);
                
                const checkInTime = record.check_in_time ? new Date(record.check_in_time).toLocaleTimeString('zh-TW', { hour12: false }) : '-';
                const checkOutTime = record.check_out_time ? new Date(record.check_out_time).toLocaleTimeString('zh-TW', { hour12: false }) : '-';
                
                html += `
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${record.work_date}</strong>
                                    <span class="badge ${statusClass} ms-2">${statusIcon} ${statusText}</span>
                                </div>
                                <div class="text-end small">
                                    <div>上班: ${checkInTime}</div>
                                    <div>下班: ${checkOutTime}</div>
                                    ${record.work_hours ? `<div class="text-primary"><strong>${record.work_hours}h</strong></div>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            content.innerHTML = html;
            
            // 更新統計圖表
            displayAttendanceStats(data.stats);
        }

        // 顯示統計圖表
        function displayAttendanceStats(stats) {
            const statsContent = document.getElementById('attendance-stats');
            
            statsContent.innerHTML = `
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <h6 class="text-muted mb-1">出勤率</h6>
                            <h4 class="text-primary mb-0">${Math.round((stats.checked_in_days / new Date().getDate()) * 100)}%</h4>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <h6 class="text-muted mb-1">平均工時</h6>
                            <h4 class="text-warning mb-0">${stats.avg_hours}h</h4>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <h6 class="text-muted">本月概況</h6>
                    <div class="progress mb-2" style="height: 20px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: ${(stats.checked_in_days / new Date().getDate()) * 100}%">
                            ${stats.checked_in_days} 天
                        </div>
                    </div>
                    <small class="text-muted">
                        總計 ${stats.total_hours} 小時 / ${stats.checked_in_days} 天
                    </small>
                </div>
            `;
        }

        // 獲取打卡狀態圖標
        function getAttendanceStatusIcon(status) {
            const iconMap = {
                'checked_in': '<i class="fas fa-play"></i>',
                'checked_out': '<i class="fas fa-stop"></i>',
                'absent': '<i class="fas fa-times"></i>'
            };
            return iconMap[status] || '<i class="fas fa-question"></i>';
        }

        // 獲取打卡狀態文字
        function getAttendanceStatusText(status) {
            const textMap = {
                'checked_in': '上班中',
                'checked_out': '正常',
                'absent': '缺席'
            };
            return textMap[status] || '未知';
        }

        // 獲取打卡狀態樣式
        function getAttendanceStatusClass(status) {
            const classMap = {
                'checked_in': 'bg-warning',
                'checked_out': 'bg-success',
                'absent': 'bg-danger'
            };
            return classMap[status] || 'bg-secondary';
        }

        // === 加班系統函數 ===
        
        // 開始加班
        function startOvertime() {
            const workContent = document.getElementById('overtime-content').value.trim();
            
            if (!workContent) {
                Swal.fire({
                    icon: 'warning',
                    title: '請填寫工作內容',
                    text: '加班需要填寫工作內容',
                    confirmButtonColor: '#667eea'
                });
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'start_overtime');
            formData.append('work_content', workContent);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '加班開始',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    });
                    
                    // 清空工作內容
                    document.getElementById('overtime-content').value = '';
                    
                    // 重新載入加班狀態和數據
                    loadOvertimeStatus();
                    loadOvertimeData();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '加班開始失敗',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: '網路錯誤',
                    text: '加班開始失敗，請重試',
                    confirmButtonColor: '#667eea'
                });
            });
        }
        
        // 結束加班
        function endOvertime() {
            const activeOvertimeId = document.getElementById('end-overtime-btn').dataset.overtimeId;
            
            if (!activeOvertimeId) {
                Swal.fire({
                    icon: 'warning',
                    title: '沒有進行中的加班',
                    text: '請先開始加班',
                    confirmButtonColor: '#667eea'
                });
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'end_overtime');
            formData.append('overtime_id', activeOvertimeId);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '加班結束',
                        text: `${data.message}\\n加班時數: ${data.data.overtime_hours} 小時`,
                        confirmButtonColor: '#667eea'
                    });
                    
                    // 重新載入加班狀態和數據
                    loadOvertimeStatus();
                    loadOvertimeData();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '加班結束失敗',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: '網路錯誤',
                    text: '加班結束失敗，請重試',
                    confirmButtonColor: '#667eea'
                });
            });
        }
        
        // 載入加班狀態
        function loadOvertimeStatus() {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_overtime_status');
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayOvertimeStatus(data.data);
                }
            })
            .catch(error => {
                console.error('Error loading overtime status:', error);
            });
        }
        
        // 顯示加班狀態
        function displayOvertimeStatus(records) {
            const infoDiv = document.getElementById('overtime-info');
            const startBtn = document.getElementById('start-overtime-btn');
            const endBtn = document.getElementById('end-overtime-btn');
            const todaySessions = document.getElementById('today-overtime-sessions');
            const todayHours = document.getElementById('today-overtime-hours');
            
            // 統計今日數據
            const totalSessions = records.length;
            const totalHours = records.reduce((sum, r) => sum + parseFloat(r.overtime_hours || 0), 0);
            const activeRecord = records.find(r => r.status === 'started');
            
            todaySessions.textContent = totalSessions;
            todayHours.textContent = totalHours.toFixed(1);
            
            if (activeRecord) {
                // 有進行中的加班
                const startTime = new Date(activeRecord.start_time);
                const now = new Date();
                const duration = Math.floor((now - startTime) / 1000);
                const hours = Math.floor(duration / 3600);
                const minutes = Math.floor((duration % 3600) / 60);
                const seconds = duration % 60;
                
                infoDiv.innerHTML = `
                    <div class="alert alert-warning mb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-play me-2"></i>
                                <strong>加班進行中</strong>
                                <div class="small mt-1">
                                    開始時間: ${startTime.toLocaleTimeString('zh-TW', { hour12: false })}
                                </div>
                                <div class="small">
                                    工作內容: ${activeRecord.work_content}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="h5 mb-0" id="overtime-timer">
                                    ${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}
                                </div>
                                <small>進行時間</small>
                            </div>
                        </div>
                    </div>
                `;
                
                startBtn.disabled = true;
                endBtn.disabled = false;
                endBtn.dataset.overtimeId = activeRecord.id;
                
                // 啟動計時器
                if (window.overtimeTimer) clearInterval(window.overtimeTimer);
                window.overtimeTimer = setInterval(() => {
                    const now = new Date();
                    const duration = Math.floor((now - startTime) / 1000);
                    const h = Math.floor(duration / 3600);
                    const m = Math.floor((duration % 3600) / 60);
                    const s = duration % 60;
                    
                    const timerElement = document.getElementById('overtime-timer');
                    if (timerElement) {
                        timerElement.textContent = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                    }
                }, 1000);
                
            } else {
                // 沒有進行中的加班
                infoDiv.innerHTML = `
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        今日尚未開始加班，填寫工作內容後可開始加班記錄
                    </div>
                `;
                
                startBtn.disabled = false;
                endBtn.disabled = true;
                endBtn.dataset.overtimeId = '';
                
                // 清除計時器
                if (window.overtimeTimer) {
                    clearInterval(window.overtimeTimer);
                    window.overtimeTimer = null;
                }
            }
        }
        
        // 載入加班數據
        function loadOvertimeData() {
            const month = document.getElementById('overtime-month-select').value;
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_overtime_summary');
            formData.append('month', month);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayOvertimeRecords(data.data);
                }
            })
            .catch(error => {
                console.error('Error loading overtime data:', error);
            });
        }
        
        // 顯示加班記錄
        function displayOvertimeRecords(data) {
            const content = document.getElementById('overtime-records-content');
            
            if (!data.records || data.records.length === 0) {
                content.innerHTML = '<div class="text-center py-3 text-muted">本月沒有加班記錄</div>';
                return;
            }
            
            let html = '';
            data.records.forEach(record => {
                const startTime = new Date(record.start_time).toLocaleTimeString('zh-TW', { hour12: false });
                const endTime = record.end_time ? new Date(record.end_time).toLocaleTimeString('zh-TW', { hour12: false }) : '進行中';
                const status = record.status === 'started' ? '進行中' : '已結束';
                const statusClass = record.status === 'started' ? 'bg-warning' : 'bg-success';
                
                html += `
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong>${record.work_date}</strong>
                                        <span class="badge ${statusClass}">${status}</span>
                                    </div>
                                    <div class="small text-muted mb-1">
                                        ${startTime} - ${endTime}
                                        ${record.overtime_hours ? `(${record.overtime_hours}h)` : ''}
                                    </div>
                                    <div class="small">
                                        <strong>工作內容:</strong> ${record.work_content}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            content.innerHTML = html;
        }

        function ajaxSubmit(event, action, callback) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('action', action);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (callback) callback(data.data);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '錯誤',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    });
                }
            });
        }

        // 載入所有使用者 (同事)
        function loadAllUsers() {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_all_users');
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('userAssignedUser');
                    select.innerHTML = '<option value="">選擇一位同事</option>';
                    data.data.forEach(user => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = user.name;
                        select.appendChild(option);
                    });
                }
            });
        }

        // 切換富文本編輯器
        function toggleEditor() {
            const useRichText = document.getElementById('useRichText').checked;
            const textarea = document.getElementById('reportContent');
            
            console.log('toggleEditor called, useRichText:', useRichText);
            
            if (useRichText) {
                // 移除 required 屬性，避免瀏覽器驗證錯誤
                textarea.removeAttribute('required');
                
                // 初始化 TinyMCE
                tinymce.init({
                    selector: '#reportContent',
                    height: 300,
                    menubar: false,
                    plugins: [
                        'advlist autolink lists link image charmap print preview anchor',
                        'searchreplace visualblocks code fullscreen',
                        'insertdatetime media table paste code help wordcount'
                    ],
                    toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
                    setup: function (editor) {
                        console.log('TinyMCE editor setup completed');
                        richTextEditor = editor;
                    },
                    init_instance_callback: function (editor) {
                        console.log('TinyMCE editor initialized:', editor.id);
                    }
                });
            } else {
                // 移除 TinyMCE
                if (richTextEditor) {
                    console.log('Removing TinyMCE editor');
                    tinymce.remove('#reportContent');
                    richTextEditor = null;
                }
                
                // 恢復 required 屬性
                textarea.setAttribute('required', '');
            }
        }

        // 開啟回報 Modal
        function openReportModal(taskId, taskTitle) {
            // 重置編輯器
            if (richTextEditor) {
                tinymce.remove('#reportContent');
                richTextEditor = null;
            }
            
            // 設置表單值
            document.getElementById('reportTaskId').value = taskId;
            document.getElementById('reportTaskTitle').value = taskTitle;
            document.getElementById('reportContent').value = '';
            document.getElementById('reportStatus').value = 'in_progress';
            document.getElementById('useRichText').checked = false;
            
            // 確保 textarea 有 required 屬性
            document.getElementById('reportContent').setAttribute('required', '');
            
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        }

        // 提交回報
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            let content = '';
            const useRichText = document.getElementById('useRichText').checked;
            
            console.log('Submitting report, useRichText:', useRichText);
            console.log('richTextEditor exists:', !!richTextEditor);
            
            // 根據是否使用富文本來獲取內容
            if (useRichText && richTextEditor) {
                content = richTextEditor.getContent();
                console.log('Got content from TinyMCE:', content);
            } else {
                content = document.getElementById('reportContent').value;
                console.log('Got content from textarea:', content);
            }
            
            // 檢查內容是否為空
            const contentToCheck = useRichText ? content.replace(/<[^>]*>/g, '').trim() : content.trim();
            console.log('Content to check:', contentToCheck);
            
            if (!contentToCheck) {
                Swal.fire({
                    icon: 'warning',
                    title: '提醒',
                    text: '請填寫工作內容',
                    confirmButtonColor: '#667eea'
                });
                return;
            }
            
            // 顯示提交中的訊息
            Swal.fire({
                title: '提交中...',
                text: '正在提交工作回報',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'submit_report');
            formData.append('task_id', document.getElementById('reportTaskId').value);
            formData.append('content', content);
            formData.append('status', document.getElementById('reportStatus').value);
            formData.append('is_rich_text', useRichText ? 'true' : 'false');
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close(); // 關閉載入訊息
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '成功',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    }).then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
                        // 可以選擇性重新載入頁面
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '錯誤',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    });
                }
            })
            .catch(error => {
                Swal.close(); // 關閉載入訊息
                console.error('提交錯誤:', error);
                Swal.fire({
                    icon: 'error',
                    title: '網路錯誤',
                    text: '提交失敗，請檢查網路連線後重試',
                    confirmButtonColor: '#667eea'
                });
            });
        });

        // 查看歷史回報
        function viewReports(taskId) {
            const modal = new bootstrap.Modal(document.getElementById('historyModal'));
            modal.show();
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_reports');
            formData.append('task_id', taskId);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReports(data.data);
                } else {
                    document.getElementById('historyContent').innerHTML = 
                        '<div class="text-center text-danger">載入失敗</div>';
                }
            });
        }

        // 顯示歷史回報
        function displayReports(reports) {
            const content = document.getElementById('historyContent');
            
            if (reports.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">尚無回報紀錄</h5>
                    </div>
                `;
                return;
            }
            
            let html = '';
            reports.forEach(report => {
                const statusText = getStatusText(report.status);
                const statusBadge = getStatusBadge(report.status);
                const isRichText = report.is_rich_text == 1;
                
                html += `
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0">${report.report_date}</h6>
                                <span class="${statusBadge}">${statusText}</span>
                            </div>
                            <div class="card-text ${isRichText ? 'rich-content' : ''}">
                                ${isRichText ? report.content : report.content.replace(/\n/g, '<br>')}
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                ${new Date(report.created_at).toLocaleString('zh-TW')}
                                ${isRichText ? '<i class="fas fa-code ms-3" title="富文本內容"></i>' : ''}
                            </small>
                        </div>
                    </div>
                `;
            });
            
            content.innerHTML = html;
        }

        // 選擇專案
        function selectProject(projectId, projectName) {
            currentProjectId = projectId;
            document.getElementById('current-project-name').textContent = projectName;
            document.getElementById('new-discussion-btn').style.display = 'block';
            document.getElementById('discussionProjectId').value = projectId;
            
            // 高亮選中的專案
            document.querySelectorAll('.project-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-project-id="${projectId}"]`).classList.add('active');
            
            loadDiscussions(projectId);
        }

        // 載入討論
        function loadDiscussions(projectId) {
            const content = document.getElementById('discussions-content');
            content.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> 載入中...</div>';
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_discussions');
            formData.append('project_id', projectId);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDiscussions(data.data);
                } else {
                    content.innerHTML = '<div class="text-center text-danger">載入失敗</div>';
                }
            });
        }

        // 顯示討論
        function displayDiscussions(discussions) {
            const content = document.getElementById('discussions-content');
            
            if (discussions.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">還沒有討論，開始第一個話題吧！</h5>
                    </div>
                `;
                return;
            }
            
            let html = '<div style="max-height: 400px; overflow-y: auto;">';
            discussions.forEach(discussion => {
                html += `
                    <div class="discussion-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <strong>${discussion.user_name}</strong>
                            <small class="text-muted">${new Date(discussion.created_at).toLocaleString('zh-TW')}</small>
                        </div>
                        <div>${discussion.content.replace(/\n/g, '<br>')}</div>
                    </div>
                `;
            });
            html += '</div>';
            
            content.innerHTML = html;
        }

        // 發布討論
        document.getElementById('discussionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'post_discussion');
            formData.append('project_id', document.getElementById('discussionProjectId').value);
            formData.append('content', document.getElementById('discussionContent').value);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '成功',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    }).then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('discussionModal')).hide();
                        document.getElementById('discussionContent').value = '';
                        loadDiscussions(currentProjectId);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '錯誤',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    });
                }
            });
        });

        // 切換到討論區
        function switchToDiscussion(projectId) {
            // 切換到討論標籤
            const discussionsTab = new bootstrap.Tab(document.getElementById('discussions-tab'));
            discussionsTab.show();
            
            // 找到對應專案並選中
            const projectElement = document.querySelector(`[data-project-id="${projectId}"]`);
            if (projectElement) {
                const projectName = projectElement.dataset.projectName;
                selectProject(projectId, projectName);
            }
        }

        // 載入收入資料
        function loadIncomeData() {
            const month = document.getElementById('income-month-select').value;
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_income_summary');
            formData.append('month', month);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayIncomeData(data.data);
                } else {
                    document.getElementById('income-details-content').innerHTML = 
                        '<div class="text-center text-danger">載入失敗</div>';
                }
            });
        }

        // 顯示收入資料
        function displayIncomeData(data) {
            // 更新總金額
            document.getElementById('current-month-income').textContent = 
                'NT$ ' + new Intl.NumberFormat().format(data.total);
            
            const date = new Date(data.month + '-01');
            document.getElementById('current-month-text').textContent = 
                date.getFullYear() + '年' + (date.getMonth() + 1) + '月';
            
            // 顯示明細
            const content = document.getElementById('income-details-content');
            
            if (data.details.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">本月暫無收入紀錄</h5>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr>';
            html += '<th>日期</th><th>專案</th><th>類型</th><th>金額</th><th>說明</th>';
            html += '</tr></thead><tbody>';
            
            data.details.forEach(record => {
                const typeMap = {
                    'commission': '分成',
                    'bonus': '獎金',
                    'adjustment': '調整'
                };
                
                html += `
                    <tr>
                        <td>${new Date(record.created_at).toLocaleDateString('zh-TW')}</td>
                        <td>${record.project_name}</td>
                        <td><span class="badge bg-info">${typeMap[record.income_type] || record.income_type}</span></td>
                        <td class="text-success fw-bold">NT$ ${new Intl.NumberFormat().format(record.amount)}</td>
                        <td>${record.description || '-'}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            content.innerHTML = html;
        }

        // 狀態相關函數
        function getStatusText(status) {
            const statusMap = {
                'pending': '未開始',
                'in_progress': '進行中',
                'completed': '已完成'
            };
            return statusMap[status] || '未知';
        }

        function getStatusBadge(status) {
            const badgeMap = {
                'pending': 'badge bg-secondary',
                'in_progress': 'badge bg-warning',
                'completed': 'badge bg-success'
            };
            return badgeMap[status] || 'badge bg-secondary';
        }

        // 公告相關函數
        function showAnnouncementModal(announcement) {
            // 設置標題
            document.getElementById('modalAnnouncementTitle').textContent = announcement.title;
            
            // 設置優先級徽章
            const priorityBadge = document.getElementById('modalAnnouncementPriority');
            const priorityTexts = {
                'urgent': '緊急',
                'high': '高',
                'normal': '普通',
                'low': '低'
            };
            const priorityClasses = {
                'urgent': 'bg-danger',
                'high': 'bg-warning text-dark',
                'normal': 'bg-primary',
                'low': 'bg-secondary'
            };
            
            priorityBadge.textContent = priorityTexts[announcement.priority] || '普通';
            priorityBadge.className = 'badge ' + (priorityClasses[announcement.priority] || 'bg-primary');
            
            // 設置作者和日期
            document.getElementById('modalAnnouncementAuthor').textContent = announcement.created_by_name;
            document.getElementById('modalAnnouncementDate').textContent = new Date(announcement.created_at).toLocaleString('zh-TW');
            
            // 設置內容
            const contentDiv = document.getElementById('modalAnnouncementContent');
            if (announcement.is_rich_text == 1) {
                contentDiv.innerHTML = announcement.content;
            } else {
                contentDiv.innerHTML = announcement.content.replace(/\n/g, '<br>');
            }
            
            // 顯示 Modal
            const modal = new bootstrap.Modal(document.getElementById('announcementDetailModal'));
            modal.show();
        }

        // 頁面載入時的初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 為公告卡片添加動畫效果
            const announcementCards = document.querySelectorAll('.announcement-card');
            announcementCards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('animate__animated', 'animate__fadeInUp');
            });
        });
    </script>
</body>
</html>
