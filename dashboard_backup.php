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
        if (getPostValue('action') === 'submit_report') {
            $taskId = getPostValueInt('task_id');
            $content = getPostValueSanitized('content');
            $status = getPostValueSanitized('status');
            $reportDate = date('Y-m-d');
            
            if (empty($content)) {
                throw new Exception('請填寫工作內容');
            }
            
            // 檢查是否已有當日回報
            $existingReport = $db->fetch(
                'SELECT id FROM work_reports WHERE task_id = ? AND user_id = ? AND report_date = ?',
                [$taskId, $userId, $reportDate]
            );
            
            if ($existingReport) {
                // 更新現有回報
                $db->execute(
                    'UPDATE work_reports SET content = ?, status = ?, updated_at = NOW() WHERE id = ?',
                    [$content, $status, $existingReport['id']]
                );
            } else {
                // 新增回報
                $db->execute(
                    'INSERT INTO work_reports (task_id, user_id, report_date, content, status) VALUES (?, ?, ?, ?, ?)',
                    [$taskId, $userId, $reportDate, $content, $status]
                );
            }
            
            // 更新任務狀態
            $db->execute('UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $taskId]);
            
            jsonResponse(true, '工作回報提交成功！');
            
        } elseif (getPostValue('action') === 'get_reports') {
            $taskId = getPostValueInt('task_id');
            
            $reports = $db->fetchAll(
                'SELECT * FROM work_reports WHERE task_id = ? AND user_id = ? ORDER BY report_date DESC',
                [$taskId, $userId]
            );
            
            jsonResponse(true, '取得回報紀錄成功', $reports);
        }
        
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage());
    }
    
    exit;
}

// 取得使用者的任務
$tasks = $db->fetchAll(
    'SELECT t.*, p.name as project_name FROM tasks t 
     LEFT JOIN projects p ON t.project_id = p.id 
     WHERE t.assigned_user_id = ? 
     ORDER BY t.created_at DESC',
    [$userId]
);

// 取得今日回報
$todayReports = [];
foreach ($tasks as $task) {
    $report = $db->fetch(
        'SELECT * FROM work_reports WHERE task_id = ? AND user_id = ? AND report_date = ?',
        [$task['id'], $userId, date('Y-m-d')]
    );
    $todayReports[$task['id']] = $report;
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>員工面板 - WorkLog Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            transform: translateY(-5px);
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
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                            <i class="fas fa-user-edit me-2"></i>個人資料
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
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
                                <p class="text-muted">請聯繫管理員為您指派任務</p>
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
                                                        <i class="fas fa-plus me-1"></i>填寫回報
                                                    </button>
                                                    <button class="btn btn-outline-secondary btn-sm" onclick="viewReports(<?php echo $task['id']; ?>)">
                                                        <i class="fas fa-history me-1"></i>查看紀錄
                                                    </button>
                                                </div>
                                                
                                                <?php if (isset($todayReports[$task['id']])): ?>
                                                    <div class="mt-3 p-2 bg-light rounded">
                                                        <small class="text-success">
                                                            <i class="fas fa-check-circle me-1"></i>今日已回報
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
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

    <!-- 工作回報 Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog">
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
                            <textarea class="form-control" id="reportContent" name="content" rows="4" required 
                                placeholder="請描述今日的工作進度、遇到的問題或完成的成果..."></textarea>
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
                            每日限填寫一次回報，重複提交將覆蓋當日內容
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // 開啟回報 Modal
        function openReportModal(taskId, taskTitle) {
            document.getElementById('reportTaskId').value = taskId;
            document.getElementById('reportTaskTitle').value = taskTitle;
            document.getElementById('reportContent').value = '';
            document.getElementById('reportStatus').value = 'in_progress';
            
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        }

        // 提交回報
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'submit_report');
            formData.append('task_id', document.getElementById('reportTaskId').value);
            formData.append('content', document.getElementById('reportContent').value);
            formData.append('status', document.getElementById('reportStatus').value);
            
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
                Swal.fire({
                    icon: 'error',
                    title: '錯誤',
                    text: '提交失敗，請稍後再試',
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
            })
            .catch(error => {
                document.getElementById('historyContent').innerHTML = 
                    '<div class="text-center text-danger">載入失敗</div>';
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
                
                html += `
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0">${report.report_date}</h6>
                                <span class="${statusBadge}">${statusText}</span>
                            </div>
                            <p class="card-text">${report.content}</p>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                更新時間：${new Date(report.updated_at).toLocaleString('zh-TW')}
                            </small>
                        </div>
                    </div>
                `;
            });
            
            content.innerHTML = html;
        }

        // 狀態轉換函數
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
