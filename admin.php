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

            case 'add_commission':
                $userId = getPostValueInt('user_id');
                $projectId = getPostValueInt('project_id');
                $percentage = getPostValue('commission_percentage');
                $base = getPostValue('base_amount');
                $bonus = getPostValue('bonus_amount');
                $notes = getPostValueSanitized('notes');

                if (!$userId || !$projectId || !is_numeric($percentage)) {
                    throw new Exception('請填寫所有必要欄位');
                }

                // 檢查是否已存在，存在則更新，否則新增
                $existing = $db->fetch(
                    'SELECT id FROM project_commissions WHERE user_id = ? AND project_id = ?',
                    [$userId, $projectId]
                );

                if ($existing) {
                    $db->execute(
                        'UPDATE project_commissions SET commission_percentage = ?, base_amount = ?, bonus_amount = ?, notes = ? WHERE id = ?',
                        [$percentage, $base, $bonus, $notes, $existing['id']]
                    );
                } else {
                    $db->execute(
                        'INSERT INTO project_commissions (user_id, project_id, commission_percentage, base_amount, bonus_amount, notes) VALUES (?, ?, ?, ?, ?, ?)',
                        [$userId, $projectId, $percentage, $base, $bonus, $notes]
                    );
                }
                jsonResponse(true, '分成設定儲存成功！', ['success' => true]);
                break;

            case 'delete_commission':
                $commissionId = getPostValueInt('commission_id');
                $db->execute('DELETE FROM project_commissions WHERE id = ?', [$commissionId]);
                jsonResponse(true, '分成設定刪除成功！');
                break;

            case 'update_project':
                $projectId = getPostValueInt('project_id');
                $name = getPostValueSanitized('name');
                $description = getPostValueSanitized('description');
                if (!$projectId || empty($name)) {
                    throw new Exception('缺少必要參數');
                }
                $db->execute(
                    'UPDATE projects SET name = ?, description = ? WHERE id = ?',
                    [$name, $description, $projectId]
                );
                jsonResponse(true, '專案更新成功！');
                break;

            case 'delete_project':
                $projectId = getPostValueInt('project_id');
                if (!$projectId) {
                    throw new Exception('缺少專案ID');
                }
                // 安全起見，先將關聯任務的 project_id 設為 NULL
                $db->execute('UPDATE tasks SET project_id = NULL WHERE project_id = ?', [$projectId]);
                // 刪除專案
                $db->execute('DELETE FROM projects WHERE id = ?', [$projectId]);
                jsonResponse(true, '專案已刪除！');
                break;

            case 'add_income_record':
                $userId = getPostValueInt('user_id');
                $projectId = getPostValueInt('project_id');
                $incomeType = getPostValueSanitized('income_type');
                $amount = getPostValue('amount');
                $incomeMonth = getPostValue('income_month');
                $description = getPostValueSanitized('description');

                if (!$userId || !$projectId || !$incomeType || !is_numeric($amount) || !$incomeMonth) {
                    throw new Exception('請填寫所有必要欄位');
                }

                $db->execute(
                    'INSERT INTO income_records (user_id, project_id, income_type, amount, income_month, description) VALUES (?, ?, ?, ?, ?, ?)',
                    [$userId, $projectId, $incomeType, $amount, $incomeMonth, $description]
                );
                jsonResponse(true, '收入紀錄新增成功！');
                break;

            case 'get_income_records':
                $userId = getPostValueInt('user_id') ?: null;
                $projectId = getPostValueInt('project_id') ?: null;
                $month = getPostValue('month') ?: null;

                $sql = "SELECT ir.*, u.name as user_name, p.name as project_name
                        FROM income_records ir
                        JOIN users u ON ir.user_id = u.id
                        JOIN projects p ON ir.project_id = p.id
                        WHERE 1=1";
                $params = [];

                if ($userId) {
                    $sql .= " AND ir.user_id = ?";
                    $params[] = $userId;
                }
                if ($projectId) {
                    $sql .= " AND ir.project_id = ?";
                    $params[] = $projectId;
                }
                if ($month) {
                    $sql .= " AND ir.income_month = ?";
                    $params[] = $month;
                }

                $sql .= " ORDER BY ir.created_at DESC LIMIT 100";
                
                $records = $db->fetchAll($sql, $params);
                jsonResponse(true, '取得收入紀錄成功', $records);
                break;

            case 'get_commissions':
                 $commissionsData = $db->fetchAll(
                    'SELECT pc.*, u.name as user_name, p.name as project_name 
                     FROM project_commissions pc
                     JOIN users u ON pc.user_id = u.id
                     JOIN projects p ON pc.project_id = p.id
                     ORDER BY p.name, u.name'
                );
                jsonResponse(true, '取得分成設定成功', $commissionsData);
                break;

            case 'add_project_income':
                $projectId = getPostValueInt('project_id');
                $totalAmount = getPostValue('total_amount');
                $incomeMonth = getPostValue('income_month');
                $description = getPostValueSanitized('description');

                if (!$projectId || !is_numeric($totalAmount) || !$incomeMonth) {
                    throw new Exception('請填寫所有必要欄位');
                }

                // 獲取該專案的分成設定
                $commissions = $db->fetchAll(
                    'SELECT * FROM project_commissions WHERE project_id = ?',
                    [$projectId]
                );

                if (empty($commissions)) {
                    throw new Exception('該專案尚未設定分成比例');
                }

                // 為每個有分成的員工計算並新增收入紀錄
                foreach ($commissions as $commission) {
                    $commissionAmount = ($totalAmount * $commission['commission_percentage'] / 100) + $commission['base_amount'] + $commission['bonus_amount'];
                    
                    $db->execute(
                        'INSERT INTO income_records (user_id, project_id, income_type, amount, income_month, description) VALUES (?, ?, ?, ?, ?, ?)',
                        [$commission['user_id'], $projectId, 'commission', $commissionAmount, $incomeMonth, $description]
                    );
                }

                jsonResponse(true, '專案收入分配成功！', ['commission_count' => count($commissions)]);
                break;

            case 'get_project_incomes':
                $projectIncomes = $db->fetchAll(
                    'SELECT p.name as project_name, 
                            ir.income_month,
                            SUM(ir.amount) as total_distributed,
                            COUNT(DISTINCT ir.user_id) as employee_count,
                            GROUP_CONCAT(DISTINCT u.name) as employees
                     FROM income_records ir
                     JOIN projects p ON ir.project_id = p.id
                     JOIN users u ON ir.user_id = u.id
                     WHERE ir.income_type = "commission"
                     GROUP BY ir.project_id, ir.income_month
                     ORDER BY ir.income_month DESC, p.name'
                );
                jsonResponse(true, '取得專案收入分配紀錄成功', $projectIncomes);
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

// 取得所有分成設定
$commissions = $db->fetchAll(
    'SELECT pc.*, u.name as user_name, p.name as project_name 
     FROM project_commissions pc
     JOIN users u ON pc.user_id = u.id
     JOIN projects p ON pc.project_id = p.id
     ORDER BY p.name, u.name'
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
        .nav-pills .nav-link.active, .nav-pills .show>.nav-link {
            background-color: #667eea;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                <i class="fas fa-user-shield me-2"></i>WorkLog Manager - 後台
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['user_name']; ?>
                </span>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
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
                                <button class="nav-link active" id="dashboard-tab" data-bs-toggle="pill" data-bs-target="#dashboard" type="button" role="tab">
                                    <i class="fas fa-tachometer-alt me-2"></i>儀表板
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="users-tab" data-bs-toggle="pill" data-bs-target="#users" type="button" role="tab">
                                    <i class="fas fa-users me-2"></i>使用者管理
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="projects-tab" data-bs-toggle="pill" data-bs-target="#projects" type="button" role="tab">
                                    <i class="fas fa-folder me-2"></i>專案管理
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tasks-tab" data-bs-toggle="pill" data-bs-target="#tasks" type="button" role="tab">
                                    <i class="fas fa-tasks me-2"></i>任務管理
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reports-tab" data-bs-toggle="pill" data-bs-target="#reports" type="button" role="tab">
                                    <i class="fas fa-file-alt me-2"></i>工作報告
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="finance-tab" data-bs-toggle="pill" data-bs-target="#finance" type="button" role="tab">
                                    <i class="fas fa-coins me-2"></i>財務管理
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" href="attendance_admin.php">
                                    <i class="fas fa-clock me-2"></i>打卡管理
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content" id="adminTabContent">
                            <!-- 儀表板 -->
                            <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">近期任務</h6>
                                            </div>
                                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>任務標題</th>
                                                                <th>指派員工</th>
                                                                <th>專案</th>
                                                                <th>截止日期</th>
                                                                <th>狀態</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($tasks as $task): ?>
                                                                <tr>
                                                                    <td><?php echo $task['id']; ?></td>
                                                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                                    <td><?php echo htmlspecialchars($task['user_name']); ?></td>
                                                                    <td><?php echo $task['project_name'] ? htmlspecialchars($task['project_name']) : '-'; ?></td>
                                                                    <td><?php echo $task['due_date'] ?: '-'; ?></td>
                                                                    <td>
                                                                        <span class="<?php echo getStatusBadge($task['status']); ?>">
                                                                            <?php echo getStatusText($task['status']); ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">近期回報</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>任務</th>
                                                                <th>員工</th>
                                                                <th>回報日期</th>
                                                                <th>狀態</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            // 取得近期回報
                                                            $reports = $db->fetchAll(
                                                                "SELECT wr.*, t.title as task_title, u.name as user_name
                                                                 FROM work_reports wr
                                                                 JOIN tasks t ON wr.task_id = t.id
                                                                 JOIN users u ON wr.user_id = u.id
                                                                 ORDER BY wr.report_date DESC
                                                                 LIMIT 5"
                                                            );
                                                            
                                                            foreach ($reports as $report): ?>
                                                                <tr>
                                                                    <td><?php echo $report['id']; ?></td>
                                                                    <td><?php echo htmlspecialchars($report['task_title']); ?></td>
                                                                    <td><?php echo htmlspecialchars($report['user_name']); ?></td>
                                                                    <td><?php echo $report['report_date']; ?></td>
                                                                    <td>
                                                                        <span class="<?php echo getStatusBadge($report['status']); ?>">
                                                                            <?php echo getStatusText($report['status']); ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 使用者管理 -->
                            <div class="tab-pane fade" id="users" role="tabpanel">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">使用者管理</h6>
                                    </div>
                                    <div class="card-body">
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
                                                <th>任務數</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($projects as $project): ?>
                                                <tr id="project-row-<?php echo $project['id']; ?>">
                                                    <td><?php echo $project['id']; ?></td>
                                                    <td class="project-name-<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></td>
                                                    <td class="project-desc-<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['description']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($project['created_at'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        $taskCount = $db->fetch('SELECT COUNT(*) as count FROM tasks WHERE project_id = ?', [$project['id']])['count'];
                                                        echo $taskCount;
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="openEditProjectModal(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($project['description'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProject(<?php echo $project['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
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

                            <!-- 工作報告 -->
                            <div class="tab-pane fade" id="reports" role="tabpanel">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">工作報告</h6>
                                    </div>
                                    <div class="card-body">
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
                                                <button class="btn btn-primary w-100" id="filterReportsBtn">
                                                    <i class="fas fa-search me-2"></i>搜尋
                                                </button>
                                            </div>
                                        </div>
                                        <div id="reports-content">
                                            <div class="text-center py-5">
                                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                                <h5 class="text-muted">請使用篩選器查詢報告</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 財務管理 -->
                            <div class="tab-pane fade" id="finance" role="tabpanel">
                                <ul class="nav nav-tabs mb-3">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#commission-settings">分成設定</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#project-income">專案收入</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#income-records">收入紀錄</a>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                    <!-- 分成設定 -->
                                    <div class="tab-pane container active" id="commission-settings">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h6 class="mb-0">新增/編輯分成</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <form id="commissionForm">
                                                            <div class="mb-3">
                                                                <label for="commissionUser" class="form-label">員工</label>
                                                                <select class="form-select" id="commissionUser" name="user_id" required>
                                                                    <option value="">選擇員工</option>
                                                                    <?php foreach ($employees as $employee): ?>
                                                                        <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="commissionProject" class="form-label">專案</label>
                                                                <select class="form-select" id="commissionProject" name="project_id" required>
                                                                    <option value="">選擇專案</option>
                                                                    <?php foreach ($projects as $project): ?>
                                                                        <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="commissionPercentage" class="form-label">分成比例 (%)</label>
                                                                <input type="number" class="form-control" id="commissionPercentage" name="commission_percentage" step="0.01" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="commissionBase" class="form-label">基本金額</label>
                                                                <input type="number" class="form-control" id="commissionBase" name="base_amount" value="0">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="commissionBonus" class="form-label">獎金</label>
                                                                <input type="number" class="form-control" id="commissionBonus" name="bonus_amount" value="0">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="commissionNotes" class="form-label">備註</label>
                                                                <textarea class="form-control" id="commissionNotes" name="notes" rows="2"></textarea>
                                                            </div>
                                                            <button type="submit" class="btn btn-primary w-100">儲存設定</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h6 class="mb-0">現有分成設定</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="table-responsive">
                                                            <table class="table table-hover">
                                                                <thead>
                                                                    <tr>
                                                                        <th>專案</th>
                                                                        <th>員工</th>
                                                                        <th>分成比例</th>
                                                                        <th>操作</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="commissionsTableBody">
                                                                    <?php foreach ($commissions as $commission): ?>
                                                                        <tr id="commission-row-<?php echo $commission['id']; ?>">
                                                                            <td><?php echo htmlspecialchars($commission['project_name']); ?></td>
                                                                            <td><?php echo htmlspecialchars($commission['user_name']); ?></td>
                                                                            <td><?php echo $commission['commission_percentage']; ?>%</td>
                                                                            <td>
                                                                                <button class="btn btn-sm btn-danger" onclick="deleteCommission(<?php echo $commission['id']; ?>)">
                                                                                    <i class="fas fa-trash"></i>
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 專案收入 -->
                                    <div class="tab-pane container fade" id="project-income">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h6 class="mb-0">新增專案收入</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <form id="projectIncomeForm">
                                                            <div class="mb-3">
                                                                <label for="incomeProjectSelect" class="form-label">選擇專案</label>
                                                                <select class="form-select" id="incomeProjectSelect" name="project_id" required>
                                                                    <option value="">選擇專案</option>
                                                                    <?php foreach ($projects as $project): ?>
                                                                        <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="totalAmount" class="form-label">專案總收入</label>
                                                                <input type="number" class="form-control" id="totalAmount" name="total_amount" step="0.01" required>
                                                                <small class="form-text text-muted">系統將自動根據分成設定分配給相關員工</small>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="projectIncomeMonth" class="form-label">收入月份</label>
                                                                <input type="month" class="form-control" id="projectIncomeMonth" name="income_month" value="<?php echo date('Y-m'); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="projectIncomeDescription" class="form-label">說明</label>
                                                                <textarea class="form-control" id="projectIncomeDescription" name="description" rows="2" placeholder="收入來源說明..."></textarea>
                                                            </div>
                                                            <button type="submit" class="btn btn-success w-100">
                                                                <i class="fas fa-calculator me-2"></i>計算並分配收入
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h6 class="mb-0">專案收入分配紀錄</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="mb-3">
                                                            <button class="btn btn-primary" onclick="loadProjectIncomes()">
                                                                <i class="fas fa-refresh me-2"></i>重新載入
                                                            </button>
                                                        </div>
                                                        <div id="project-incomes-content">
                                                            <div class="text-center py-5">
                                                                <i class="fas fa-coins fa-3x text-muted mb-3"></i>
                                                                <h5 class="text-muted">點擊重新載入查看專案收入分配紀錄</h5>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 收入紀錄 -->
                                    <div class="tab-pane container fade" id="income-records">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h6 class="mb-0">新增收入紀錄</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <form id="incomeRecordForm">
                                                            <div class="mb-3">
                                                                <label for="incomeUser" class="form-label">員工</label>
                                                                <select class="form-select" id="incomeUser" name="user_id" required>
                                                                    <option value="">選擇員工</option>
                                                                    <?php foreach ($employees as $employee): ?>
                                                                        <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="incomeProject" class="form-label">專案</label>
                                                                <select class="form-select" id="incomeProject" name="project_id" required>
                                                                    <option value="">選擇專案</option>
                                                                    <?php foreach ($projects as $project): ?>
                                                                        <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="incomeType" class="form-label">類型</label>
                                                                <select class="form-select" id="incomeType" name="income_type" required>
                                                                    <option value="commission">分成</option>
                                                                    <option value="bonus">獎金</option>
                                                                    <option value="adjustment">調整</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="incomeAmount" class="form-label">金額</label>
                                                                <input type="number" class="form-control" id="incomeAmount" name="amount" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="incomeMonth" class="form-label">歸屬月份</label>
                                                                <input type="month" class="form-control" id="incomeMonth" name="income_month" value="<?php echo date('Y-m'); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="incomeDescription" class="form-label">說明</label>
                                                                <textarea class="form-control" id="incomeDescription" name="description" rows="2"></textarea>
                                                            </div>
                                                            <button type="submit" class="btn btn-primary w-100">新增紀錄</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h6 class="mb-0">收入紀錄查詢</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <form id="incomeFilterForm" class="row g-3 align-items-end mb-3">
                                                            <div class="col-md-4">
                                                                <label for="filterIncomeUser" class="form-label">員工</label>
                                                                <select class="form-select" id="filterIncomeUser">
                                                                    <option value="">所有員工</option>
                                                                    <?php foreach ($employees as $employee): ?>
                                                                        <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label for="filterIncomeProject" class="form-label">專案</label>
                                                                <select class="form-select" id="filterIncomeProject">
                                                                    <option value="">所有專案</option>
                                                                    <?php foreach ($projects as $project): ?>
                                                                        <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="filterIncomeMonth" class="form-label">月份</label>
                                                                <input type="month" class="form-control" id="filterIncomeMonth">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <button type="submit" class="btn btn-primary w-100">
                                                                    <i class="fas fa-search"></i>
                                                                </button>
                                                            </div>
                                                        </form>
                                                        <div id="income-records-content">
                                                            <div class="text-center py-5">
                                                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                                                <h5 class="text-muted">請使用篩選器查詢收入紀錄</h5>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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

    <!-- 編輯專案 Modal -->
    <div class="modal fade" id="editProjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>編輯專案</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editProjectForm">
                    <input type="hidden" id="editProjectId" name="project_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editProjectName" class="form-label">專案名稱 *</label>
                            <input type="text" class="form-control" id="editProjectName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editProjectDescription" class="form-label">專案描述</label>
                            <textarea class="form-control" id="editProjectDescription" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">儲存變更</button>
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
        document.addEventListener('DOMContentLoaded', function () {
            // 添加全局錯誤處理
            window.addEventListener('error', function(e) {
                console.error('JavaScript Error:', e.error);
            });

            // 篩選報告
            document.getElementById('filterReportsBtn').addEventListener('click', function() {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('action', 'get_reports');
                formData.append('task_id', document.getElementById('filterTask').value);
                formData.append('user_id', document.getElementById('filterUser').value);
                formData.append('start_date', document.getElementById('startDate').value);
                formData.append('end_date', document.getElementById('endDate').value);
                
                fetchWithSpinner('reports-content', formData, displayReports);
            });

            // 提交專案表單
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
                            document.getElementById('projectForm').reset();
                            const modal = bootstrap.Modal.getInstance(document.getElementById('projectModal'));
                            modal.hide();
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
                    Swal.fire('錯誤', '請求失敗: ' + error, 'error');
                });
            });

            // 提交任務表單
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
                            document.getElementById('taskForm').reset();
                            const modal = bootstrap.Modal.getInstance(document.getElementById('taskModal'));
                            modal.hide();
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
                    Swal.fire('錯誤', '請求失敗: ' + error, 'error');
                });
            });

            // 提交分成設定表單
            document.getElementById('commissionForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('action', 'add_commission');
                formData.append('user_id', document.getElementById('commissionUser').value);
                formData.append('project_id', document.getElementById('commissionProject').value);
                formData.append('commission_percentage', document.getElementById('commissionPercentage').value);
                formData.append('base_amount', document.getElementById('commissionBase').value);
                formData.append('bonus_amount', document.getElementById('commissionBonus').value);
                formData.append('notes', document.getElementById('commissionNotes').value);
                
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
                            document.getElementById('commissionForm').reset();
                            loadCommissions(); // 重新載入列表
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
                    Swal.fire('錯誤', '請求失敗: ' + error, 'error');
                });
            });

            // 提交收入紀錄表單
            document.getElementById('incomeRecordForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('action', 'add_income_record');
                formData.append('user_id', document.getElementById('incomeUser').value);
                formData.append('project_id', document.getElementById('incomeProject').value);
                formData.append('income_type', document.getElementById('incomeType').value);
                formData.append('amount', document.getElementById('incomeAmount').value);
                formData.append('income_month', document.getElementById('incomeMonth').value);
                formData.append('description', document.getElementById('incomeDescription').value);
                
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

            // 篩選收入紀錄
            document.getElementById('incomeFilterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('action', 'get_income_records');
                formData.append('user_id', document.getElementById('filterIncomeUser').value);
                formData.append('project_id', document.getElementById('filterIncomeProject').value);
                formData.append('month', document.getElementById('filterIncomeMonth').value);
                
                fetchWithSpinner('income-records-content', formData, displayIncomeRecords);
            });

            // 提交專案收入表單
            document.getElementById('projectIncomeForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('action', 'add_project_income');
                formData.append('project_id', document.getElementById('incomeProjectSelect').value);
                formData.append('total_amount', document.getElementById('totalAmount').value);
                formData.append('income_month', document.getElementById('projectIncomeMonth').value);
                formData.append('description', document.getElementById('projectIncomeDescription').value);
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '分配完成',
                            text: `${data.message} 共為 ${data.data.commission_count} 位員工分配收入`,
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            document.getElementById('projectIncomeForm').reset();
                            loadProjectIncomes();
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
                    Swal.fire('錯誤', '請求失敗: ' + error, 'error');
                });
            });

            // 編輯專案表單
            document.getElementById('editProjectForm').addEventListener('submit', function(e) {
                ajaxSubmit(e, 'update_project', () => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editProjectModal'));
                    modal.hide();
                    const projectId = document.getElementById('editProjectId').value;
                    const newName = document.getElementById('editProjectName').value;
                    const newDesc = document.getElementById('editProjectDescription').value;
                    document.querySelector(`.project-name-${projectId}`).textContent = newName;
                    document.querySelector(`.project-desc-${projectId}`).textContent = newDesc;
                    Swal.fire('成功', '專案已更新', 'success');
                });
            });
        });

        function loadCommissions() {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_commissions');
            fetchWithSpinner('commissionsTableBody', formData, (commissions) => {
                const tableBody = document.getElementById('commissionsTableBody');
                let html = '';
                commissions.forEach(c => {
                    html += `
                        <tr id="commission-row-${c.id}">
                            <td>${c.project_name}</td>
                            <td>${c.user_name}</td>
                            <td>${c.commission_percentage}%</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="deleteCommission(${c.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                tableBody.innerHTML = html;
            });
        }

        function loadProjectIncomes() {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_project_incomes');
            fetchWithSpinner('project-incomes-content', formData, displayProjectIncomes);
        }

        function displayProjectIncomes(incomes) {
            const contentDiv = document.getElementById('project-incomes-content');
            if (!incomes || incomes.length === 0) {
                contentDiv.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">尚無專案收入分配紀錄</h5>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>專案名稱</th>
                                <th>收入月份</th>
                                <th>分配總額</th>
                                <th>受益員工數</th>
                                <th>受益員工</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            incomes.forEach(income => {
                html += `
                    <tr>
                        <td><span class="badge bg-primary">${income.project_name}</span></td>
                        <td>${income.income_month}</td>
                        <td class="text-success fw-bold">NT$ ${new Intl.NumberFormat().format(income.total_distributed)}</td>
                        <td><span class="badge bg-info">${income.employee_count} 人</span></td>
                        <td><small>${income.employees}</small></td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            contentDiv.innerHTML = html;
        }

        function ajaxSubmit(event, action, callback) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('action', action);

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (callback) {
                        callback(data.data);
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: '成功',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '錯誤',
                        text: data.message
                    });
                }
            }).catch(error => {
                Swal.fire('錯誤', '請求失敗: ' + error, 'error');
            });
        }

        function fetchWithSpinner(contentId, formData, displayFunction) {
            const contentDiv = document.getElementById(contentId);
            contentDiv.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>`;

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayFunction(data.data);
                } else {
                    contentDiv.innerHTML = `<div class="text-center py-5 text-danger">${data.message}</div>`;
                }
            }).catch(error => {
                contentDiv.innerHTML = `<div class="text-center py-5 text-danger">請求錯誤: ${error}</div>`;
            });
        }

        function displayIncomeRecords(records) {
            const contentDiv = document.getElementById('income-records-content');
            if (!records || records.length === 0) {
                contentDiv.innerHTML = `<div class="text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><h5 class="text-muted">找不到符合條件的收入紀錄</h5></div>`;
                return;
            }

            const typeMap = {
                'commission': { text: '分成', class: 'bg-success' },
                'bonus': { text: '獎金', class: 'bg-info' },
                'adjustment': { text: '調整', class: 'bg-warning text-dark' }
            };

            let html = '<div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>歸屬月份</th><th>專案</th><th>員工</th><th>類型</th><th>金額</th><th>說明</th><th>紀錄時間</th></tr></thead><tbody>';
            records.forEach(record => {
                const typeInfo = typeMap[record.income_type] || { text: record.income_type, class: 'bg-secondary' };
                html += `
                    <tr>
                        <td>${record.income_month}</td>
                        <td>${record.project_name}</td>
                        <td>${record.user_name}</td>
                        <td><span class="badge ${typeInfo.class}">${typeInfo.text}</span></td>
                        <td class="text-success fw-bold">NT$ ${new Intl.NumberFormat().format(record.amount)}</td>
                        <td>${record.description || '-'}</td>
                        <td>${new Date(record.created_at).toLocaleDateString()}</td>
                    </tr>
                `;
            });
            html += '</tbody></table></div>';
            contentDiv.innerHTML = html;
        }

        function displayReports(reports) {
            const contentDiv = document.getElementById('reports-content');
            if (!reports || reports.length === 0) {
                contentDiv.innerHTML = `<div class="text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><h5 class="text-muted">找不到符合條件的報告</h5></div>`;
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>日期</th><th>專案</th><th>任務</th><th>員工</th><th>狀態</th><th>內容</th></tr></thead><tbody>';
            reports.forEach(report => {
                const isRichText = report.is_rich_text == 1;
                let content = report.content;
                if (!isRichText) {
                    content = content.replace(/\n/g, '<br>');
                }
                html += `
                    <tr>
                        <td>${report.report_date}</td>
                        <td>${report.project_name || '-'}</td>
                        <td>${report.task_title}</td>
                        <td>${report.user_name}</td>
                        <td><span class="badge ${getStatusBadge(report.status)}">${getStatusText(report.status)}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="showReportContent('${btoa(unescape(encodeURIComponent(content)))}', ${isRichText})">
                                <i class="fas fa-eye"></i> 查看
                            </button>
                        </td>
                    </tr>
                `;
            });
            html += '</tbody></table></div>';
            contentDiv.innerHTML = html;
        }

        function openEditProjectModal(id, name, description) {
            document.getElementById('editProjectId').value = id;
            document.getElementById('editProjectName').value = name;
            document.getElementById('editProjectDescription').value = description;
            const modal = new bootstrap.Modal(document.getElementById('editProjectModal'));
            modal.show();
        }

        function deleteProject(projectId) {
            Swal.fire({
                title: '確定要刪除嗎？',
                text: "這將會刪除專案，但相關任務會被保留並標示為無專案。此操作無法復原！",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '是的，刪除它！',
                cancelButtonText: '取消'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'delete_project');
                    formData.append('project_id', projectId);
                    fetch('admin.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if(data.success) {
                                document.getElementById(`project-row-${projectId}`).remove();
                                Swal.fire('已刪除！', '專案已被刪除。', 'success');
                            } else {
                                Swal.fire('錯誤', data.message, 'error');
                            }
                        });
                }
            });
        }

        function showReportContent(encodedContent, isRichText) {
            const decodedContent = decodeURIComponent(escape(atob(encodedContent)));
            Swal.fire({
                title: '報告內容',
                html: `<div style="text-align: left; max-height: 400px; overflow-y: auto;">${decodedContent}</div>`,
                width: '800px',
                confirmButtonText: '關閉'
            });
        }

        function updateUserRole(userId, selectElement) {
            const role = selectElement.value;
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'update_user_role');
            formData.append('user_id', userId);
            formData.append('role', role);

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('成功', data.message, 'success');
                } else {
                    Swal.fire('錯誤', data.message, 'error');
                }
            });
        }

        function deleteTask(taskId) {
            Swal.fire({
                title: '確定要刪除嗎？',
                text: "這個操作無法復原！",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '是的，刪除它！',
                cancelButtonText: '取消'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'delete_task');
                    formData.append('task_id', taskId);
                    fetch('admin.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if(data.success) {
                                document.getElementById(`task-row-${taskId}`).remove();
                                Swal.fire('已刪除！', '任務已被刪除。', 'success');
                            } else {
                                Swal.fire('錯誤', data.message, 'error');
                            }
                        });
                }
            });
        }

        function deleteCommission(commissionId) {
            Swal.fire({
                title: '確定要刪除嗎？',
                text: "將會刪除此項分成設定！",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '是的，刪除！',
                cancelButtonText: '取消'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'delete_commission');
                    formData.append('commission_id', commissionId);
                    fetch('admin.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if(data.success) {
                                document.getElementById(`commission-row-${commissionId}`).remove();
                                Swal.fire('已刪除！', '分成設定已被刪除。', 'success');
                            } else {
                                Swal.fire('錯誤', data.message, 'error');
                            }
                        });
                }
            });
        }

        function loadReports() {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_reports');
            formData.append('task_id', document.getElementById('filterTask').value);
            formData.append('user_id', document.getElementById('filterUser').value);
            formData.append('start_date', document.getElementById('startDate').value);
            formData.append('end_date', document.getElementById('endDate').value);
            
            fetchWithSpinner('reports-content', formData, displayReports);
        }

        function loadProjectIncomes() {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_project_incomes');
            
            fetchWithSpinner('project-incomes-content', formData, displayProjectIncomes);
        }

        function displayProjectIncomes(projectIncomes) {
            const contentDiv = document.getElementById('project-incomes-content');
            if (!projectIncomes || projectIncomes.length === 0) {
                contentDiv.innerHTML = `<div class="text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><h5 class="text-muted">尚無專案收入分配紀錄</h5></div>`;
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>專案名稱</th><th>分配月份</th><th>分配總額</th><th>受益員工數</th><th>受益員工</th></tr></thead><tbody>';
            projectIncomes.forEach(income => {
                html += `
                    <tr>
                        <td><strong>${income.project_name}</strong></td>
                        <td>${income.income_month}</td>
                        <td class="text-success fw-bold">NT$ ${new Intl.NumberFormat().format(income.total_distributed)}</td>
                        <td><span class="badge bg-info">${income.employee_count} 人</span></td>
                        <td><small>${income.employees}</small></td>
                    </tr>
                `;
            });
            html += '</tbody></table></div>';
            contentDiv.innerHTML = html;
        }

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
                'pending': 'bg-secondary',
                'in_progress': 'bg-warning text-dark',
                'completed': 'bg-success'
            };
            return badgeMap[status] || 'bg-dark';
        }
    </script>
</body>
</html>
