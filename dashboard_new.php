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
            
            if (empty(strip_tags($content))) {
                throw new Exception('請填寫工作內容');
            }
            
            // 新增回報（不再限制每日一次）
            $db->execute(
                'INSERT INTO work_reports (task_id, user_id, report_date, content, status, is_rich_text) VALUES (?, ?, ?, ?, ?, ?)',
                [$taskId, $userId, date('Y-m-d'), $content, $status, $isRichText]
            );
            
            // 更新任務狀態
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
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>員工面板 - WorkLog Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- 富文本編輯器 -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
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
    </style>
</head>
<body>
    <!-- 導航列 -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-clipboard-list me-2"></i>WorkLog Manager
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i><?php echo $_SESSION['user_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>登出
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
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
                    <i class="fas fa-tasks me-2"></i>我的任務
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="discussions-tab" data-bs-toggle="pill" data-bs-target="#discussions" type="button">
                    <i class="fas fa-comments me-2"></i>專案討論
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="income-tab" data-bs-toggle="pill" data-bs-target="#income" type="button">
                    <i class="fas fa-money-bill-wave me-2"></i>收入明細
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
                                                        
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-primary btn-sm" onclick="openReportModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>')">
                                                                <i class="fas fa-plus me-1"></i>新增回報
                                                            </button>
                                                            <button class="btn btn-outline-secondary btn-sm" onclick="viewReports(<?php echo $task['id']; ?>)">
                                                                <i class="fas fa-history me-1"></i>查看紀錄
                                                            </button>
                                                            <?php if ($task['project_id']): ?>
                                                                <button class="btn btn-outline-info btn-sm" onclick="switchToDiscussion(<?php echo $task['project_id']; ?>)">
                                                                    <i class="fas fa-comments me-1"></i>專案討論
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

            <!-- 專案討論面板 -->
            <div class="tab-pane fade" id="discussions" role="tabpanel">
                <div class="row">
                    <div class="col-md-3 mb-4">
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
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-comments me-2"></i>
                                    <span id="current-project-name">請選擇專案</span>
                                </h6>
                                <button class="btn btn-primary btn-sm" id="new-discussion-btn" style="display:none;" data-bs-toggle="modal" data-bs-target="#discussionModal">
                                    <i class="fas fa-plus me-2"></i>發布討論
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
                                    使用富文本編輯器
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
                            現在可以多次提交工作回報，每次提交都會建立新的紀錄
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">
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
                        <i class="fas fa-history me-2"></i>歷史回報紀錄
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="historyContent">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                            <p class="mt-2">載入中...</p>
                        </div>
                    </div>
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
        
        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 載入當月收入
            loadIncomeData();
            
            // 專案選擇事件
            document.querySelectorAll('.project-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const projectId = this.dataset.projectId;
                    const projectName = this.dataset.projectName;
                    selectProject(projectId, projectName);
                });
            });
        });

        // 切換富文本編輯器
        function toggleEditor() {
            const useRichText = document.getElementById('useRichText').checked;
            const textarea = document.getElementById('reportContent');
            
            if (useRichText) {
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
                        richTextEditor = editor;
                    }
                });
            } else {
                // 移除 TinyMCE
                if (richTextEditor) {
                    tinymce.remove('#reportContent');
                    richTextEditor = null;
                }
            }
        }

        // 開啟回報 Modal
        function openReportModal(taskId, taskTitle) {
            document.getElementById('reportTaskId').value = taskId;
            document.getElementById('reportTaskTitle').value = taskTitle;
            document.getElementById('reportContent').value = '';
            document.getElementById('reportStatus').value = 'in_progress';
            document.getElementById('useRichText').checked = false;
            
            // 重置編輯器
            if (richTextEditor) {
                tinymce.remove('#reportContent');
                richTextEditor = null;
            }
            
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        }

        // 提交回報
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            let content = document.getElementById('reportContent').value;
            
            // 如果使用富文本編輯器，獲取編輯器內容
            if (richTextEditor) {
                content = richTextEditor.getContent();
            }
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'submit_report');
            formData.append('task_id', document.getElementById('reportTaskId').value);
            formData.append('content', content);
            formData.append('status', document.getElementById('reportStatus').value);
            formData.append('is_rich_text', document.getElementById('useRichText').checked);
            
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
                        bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
                        // 可以選擇性重新載入頁面
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
    </script>
</body>
</html>
