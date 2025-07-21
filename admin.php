<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// 檢查管理員權限
requireAdmin();

$db = new Database();

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $action = getPostValue('action');
        if (empty($action)) {
            throw new Exception('缺少操作參數');
        }
        
        switch ($action) {
            case 'create_project':
                $name = getPostValueSanitized('name');
                $description = getPostValueSanitized('description');
                
                if (empty($name)) {
                    throw new Exception('專案名稱不能為空');
                }
                
                $db->execute('INSERT INTO projects (name, description) VALUES (?, ?)', [$name, $description]);
                jsonResponse(true, '專案建立成功！');
                break;
                
            case 'create_task':
                $title = getPostValueSanitized('title');
                $description = getPostValueSanitized('description');
                $assignedUserId = getPostValueInt('assigned_user_id');
                $projectId = getPostValueInt('project_id') ?: null;
                $dueDate = getPostValue('due_date') ?: null;
                
                if (empty($title) || !$assignedUserId) {
                    throw new Exception('請填寫必要欄位');
                }
                
                $db->execute(
                    'INSERT INTO tasks (title, description, assigned_user_id, project_id, due_date) VALUES (?, ?, ?, ?, ?)',
                    [$title, $description, $assignedUserId, $projectId, $dueDate]
                );
                jsonResponse(true, '任務建立成功！');
                break;
                
            case 'update_user_role':
                $userId = getPostValueInt('user_id');
                $role = getPostValueSanitized('role');
                
                if (!in_array($role, ['user', 'admin'])) {
                    throw new Exception('無效的角色');
                }
                
                $db->execute('UPDATE users SET role = ? WHERE id = ?', [$role, $userId]);
                jsonResponse(true, '使用者角色更新成功！');
                break;
                
            case 'delete_task':
                $taskId = getPostValueInt('task_id');
                $db->execute('DELETE FROM tasks WHERE id = ?', [$taskId]);
                jsonResponse(true, '任務刪除成功！');
                break;
                
            case 'get_reports':
                $taskId = getPostValueInt('task_id') ?: null;
                $userId = getPostValueInt('user_id') ?: null;
                $startDate = getPostValue('start_date') ?: null;
                $endDate = getPostValue('end_date') ?: null;
                
                $sql = "SELECT wr.*, t.title as task_title, u.name as user_name, p.name as project_name
                        FROM work_reports wr
                        JOIN tasks t ON wr.task_id = t.id
                        JOIN users u ON wr.user_id = u.id
                        LEFT JOIN projects p ON t.project_id = p.id
                        WHERE 1=1";
                $params = [];
                
                if ($taskId) {
                    $sql .= " AND wr.task_id = ?";
                    $params[] = $taskId;
                }
                
                if ($userId) {
                    $sql .= " AND wr.user_id = ?";
                    $params[] = $userId;
                }
                
                if ($startDate) {
                    $sql .= " AND wr.report_date >= ?";
                    $params[] = $startDate;
                }
                
                if ($endDate) {
                    $sql .= " AND wr.report_date <= ?";
                    $params[] = $endDate;
                }
                
                $sql .= " ORDER BY wr.report_date DESC, wr.created_at DESC";
                
                $reports = $db->fetchAll($sql, $params);
                jsonResponse(true, '取得回報資料成功', $reports);
                break;
                
            default:
                throw new Exception('無效的操作');
        }
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage());
    }
    exit;
}

// 取得統計資料
$stats = [
    'total_users' => $db->fetch('SELECT COUNT(*) as count FROM users WHERE role = "user"')['count'],
    'total_tasks' => $db->fetch('SELECT COUNT(*) as count FROM tasks')['count'],
    'total_projects' => $db->fetch('SELECT COUNT(*) as count FROM projects')['count'],
    'total_reports' => $db->fetch('SELECT COUNT(*) as count FROM work_reports')['count']
];

// 取得所有使用者
$users = $db->fetchAll('SELECT * FROM users ORDER BY created_at DESC');

// 取得所有專案
$projects = $db->fetchAll('SELECT * FROM projects ORDER BY created_at DESC');

// 取得所有任務
$tasks = $db->fetchAll(
    'SELECT t.*, u.name as user_name, p.name as project_name 
     FROM tasks t 
     JOIN users u ON t.assigned_user_id = u.id 
     LEFT JOIN projects p ON t.project_id = p.id 
     ORDER BY t.created_at DESC'
);

// 取得一般員工列表（用於任務指派）
$employees = $db->fetchAll('SELECT id, name, email FROM users WHERE role = "user" ORDER BY name');
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理員後台 - WorkLog Manager</title>
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
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- 導航列 -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-cog me-2"></i>管理員後台
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-2"></i><?php echo $_SESSION['user_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>員工面板
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

    <div class="container-fluid my-4">
        <!-- 統計卡片 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4><?php echo $stats['total_users']; ?></h4>
                        <p class="mb-0">員工人數</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-tasks fa-2x mb-2"></i>
                        <h4><?php echo $stats['total_tasks']; ?></h4>
                        <p class="mb-0">總任務數</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-folder fa-2x mb-2"></i>
                        <h4><?php echo $stats['total_projects']; ?></h4>
                        <p class="mb-0">專案數量</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-file-alt fa-2x mb-2"></i>
                        <h4><?php echo $stats['total_reports']; ?></h4>
                        <p class="mb-0">回報總數</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 主要內容區 -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <!-- 導航標籤 -->
                        <ul class="nav nav-pills nav-justified mb-4" id="adminTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="users-tab" data-bs-toggle="pill" data-bs-target="#users" type="button">
                                    <i class="fas fa-users me-2"></i>使用者管理
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="projects-tab" data-bs-toggle="pill" data-bs-target="#projects" type="button">
                                    <i class="fas fa-folder me-2"></i>專案管理
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tasks-tab" data-bs-toggle="pill" data-bs-target="#tasks" type="button">
                                    <i class="fas fa-tasks me-2"></i>任務管理
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reports-tab" data-bs-toggle="pill" data-bs-target="#reports" type="button">
                                    <i class="fas fa-chart-line me-2"></i>回報查看
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="adminTabContent">
                            <!-- 使用者管理 -->
                            <div class="tab-pane fade show active" id="users" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><i class="fas fa-users me-2"></i>使用者列表</h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>姓名</th>
                                                <th>Email</th>
                                                <th>角色</th>
                                                <th>註冊時間</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td><?php echo $user['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                                            <?php echo $user['role'] === 'admin' ? '管理員' : '員工'; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                                    <td>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="changeUserRole(<?php echo $user['id']; ?>, '<?php echo $user['role'] === 'admin' ? 'user' : 'admin'; ?>')">
                                                                <i class="fas fa-exchange-alt me-1"></i>
                                                                切換為<?php echo $user['role'] === 'admin' ? '員工' : '管理員'; ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 專案管理 -->
                            <div class="tab-pane fade" id="projects" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><i class="fas fa-folder me-2"></i>專案列表</h5>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projectModal">
                                        <i class="fas fa-plus me-2"></i>新增專案
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>專案名稱</th>
                                                <th>描述</th>
                                                <th>建立時間</th>
                                                <th>任務數量</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($projects as $project): ?>
                                                <tr>
                                                    <td><?php echo $project['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($project['description']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($project['created_at'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        $taskCount = $db->fetch('SELECT COUNT(*) as count FROM tasks WHERE project_id = ?', [$project['id']])['count'];
                                                        echo $taskCount;
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 任務管理 -->
                            <div class="tab-pane fade" id="tasks" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><i class="fas fa-tasks me-2"></i>任務列表</h5>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal">
                                        <i class="fas fa-plus me-2"></i>新增任務
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>任務標題</th>
                                                <th>指派員工</th>
                                                <th>專案</th>
                                                <th>狀態</th>
                                                <th>截止日期</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tasks as $task): ?>
                                                <tr>
                                                    <td><?php echo $task['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($task['user_name']); ?></td>
                                                    <td><?php echo $task['project_name'] ? htmlspecialchars($task['project_name']) : '-'; ?></td>
                                                    <td>
                                                        <span class="<?php echo getStatusBadge($task['status']); ?>">
                                                            <?php echo getStatusText($task['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $task['due_date'] ?: '-'; ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTask(<?php echo $task['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 回報查看 -->
                            <div class="tab-pane fade" id="reports" role="tabpanel">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">任務篩選</label>
                                        <select class="form-select" id="filterTask">
                                            <option value="">所有任務</option>
                                            <?php foreach ($tasks as $task): ?>
                                                <option value="<?php echo $task['id']; ?>"><?php echo htmlspecialchars($task['title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">員工篩選</label>
                                        <select class="form-select" id="filterUser">
                                            <option value="">所有員工</option>
                                            <?php foreach ($employees as $employee): ?>
                                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">開始日期</label>
                                        <input type="date" class="form-control" id="startDate">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">結束日期</label>
                                        <input type="date" class="form-control" id="endDate">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button class="btn btn-primary w-100" onclick="loadReports()">
                                            <i class="fas fa-search me-2"></i>搜尋
                                        </button>
                                    </div>
                                </div>
                                <div id="reportsContainer">
                                    <div class="text-center py-5">
                                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">請設定篩選條件後點擊搜尋</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 新增專案 Modal -->
    <div class="modal fade" id="projectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>新增專案</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="projectForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="projectName" class="form-label">專案名稱 *</label>
                            <input type="text" class="form-control" id="projectName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="projectDescription" class="form-label">專案描述</label>
                            <textarea class="form-control" id="projectDescription" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">建立專案</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 新增任務 Modal -->
    <div class="modal fade" id="taskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>新增任務</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="taskForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="taskTitle" class="form-label">任務標題 *</label>
                            <input type="text" class="form-control" id="taskTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="taskDescription" class="form-label">任務描述</label>
                            <textarea class="form-control" id="taskDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="assignedUser" class="form-label">指派員工 *</label>
                            <select class="form-select" id="assignedUser" name="assigned_user_id" required>
                                <option value="">請選擇員工</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['name']); ?> (<?php echo htmlspecialchars($employee['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="taskProject" class="form-label">所屬專案</label>
                            <select class="form-select" id="taskProject" name="project_id">
                                <option value="">無專案</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="dueDate" class="form-label">截止日期</label>
                            <input type="date" class="form-control" id="dueDate" name="due_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">建立任務</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // 新增專案
        document.getElementById('projectForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'create_project');
            formData.append('name', document.getElementById('projectName').value);
            formData.append('description', document.getElementById('projectDescription').value);
            
            fetch('admin.php', {
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
            });
        });

        // 新增任務
        document.getElementById('taskForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'create_task');
            formData.append('title', document.getElementById('taskTitle').value);
            formData.append('description', document.getElementById('taskDescription').value);
            formData.append('assigned_user_id', document.getElementById('assignedUser').value);
            formData.append('project_id', document.getElementById('taskProject').value);
            formData.append('due_date', document.getElementById('dueDate').value);
            
            fetch('admin.php', {
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
            });
        });

        // 變更使用者角色
        function changeUserRole(userId, newRole) {
            const roleText = newRole === 'admin' ? '管理員' : '員工';
            
            Swal.fire({
                title: '確認變更',
                text: `確定要將此使用者角色變更為${roleText}嗎？`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '確定',
                cancelButtonText: '取消'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'update_user_role');
                    formData.append('user_id', userId);
                    formData.append('role', newRole);
                    
                    fetch('admin.php', {
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
                    });
                }
            });
        }

        // 刪除任務
        function deleteTask(taskId) {
            Swal.fire({
                title: '確認刪除',
                text: '確定要刪除此任務嗎？此操作無法復原！',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '刪除',
                cancelButtonText: '取消'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'delete_task');
                    formData.append('task_id', taskId);
                    
                    fetch('admin.php', {
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
                    });
                }
            });
        }

        // 載入回報資料
        function loadReports() {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_reports');
            formData.append('task_id', document.getElementById('filterTask').value);
            formData.append('user_id', document.getElementById('filterUser').value);
            formData.append('start_date', document.getElementById('startDate').value);
            formData.append('end_date', document.getElementById('endDate').value);
            
            const container = document.getElementById('reportsContainer');
            container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">載入中...</p></div>';
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReports(data.data);
                } else {
                    container.innerHTML = '<div class="text-center text-danger">載入失敗</div>';
                }
            })
            .catch(error => {
                container.innerHTML = '<div class="text-center text-danger">載入失敗</div>';
            });
        }

        // 顯示回報資料
        function displayReports(reports) {
            const container = document.getElementById('reportsContainer');
            
            if (reports.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">沒有找到符合條件的回報</h5>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-hover"><thead class="table-light"><tr>';
            html += '<th>日期</th><th>員工</th><th>任務</th><th>專案</th><th>狀態</th><th>回報內容</th><th>更新時間</th>';
            html += '</tr></thead><tbody>';
            
            reports.forEach(report => {
                const statusText = getStatusText(report.status);
                const statusBadge = getStatusBadge(report.status);
                
                html += `
                    <tr>
                        <td>${report.report_date}</td>
                        <td>${report.user_name}</td>
                        <td>${report.task_title}</td>
                        <td>${report.project_name || '-'}</td>
                        <td><span class="${statusBadge}">${statusText}</span></td>
                        <td>${report.content}</td>
                        <td>${new Date(report.updated_at).toLocaleString('zh-TW')}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
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

        // 頁面載入時載入所有回報
        document.addEventListener('DOMContentLoaded', function() {
            // 等待標籤切換到回報頁面時才載入
            document.getElementById('reports-tab').addEventListener('shown.bs.tab', function() {
                loadReports();
            });
        });
    </script>
</body>
</html>
