<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
$current_user = getCurrentUser();

// 處理查詢參數
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// 建立查詢條件
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(staff_id LIKE ? OR name LIKE ? OR email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($department) {
    $where_conditions[] = "department = ?";
    $params[] = $department;
}

$where_clause = implode(' AND ', $where_conditions);

// 取得部門列表
$dept_stmt = $pdo->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL ORDER BY department");
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// 計算總記錄數
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM staff WHERE $where_clause");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// 取得員工列表
$stmt = $pdo->prepare("
    SELECT 
        s.*,
        COUNT(a.id) as attendance_count,
        MAX(a.work_date) as last_attendance
    FROM staff s
    LEFT JOIN attendance a ON s.staff_id = a.staff_id
    WHERE $where_clause
    GROUP BY s.id
    ORDER BY s.is_admin DESC, s.department, s.name
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$staff_list = $stmt->fetchAll();

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $staff_id = trim($_POST['staff_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        if ($staff_id && $name && $password) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $pdo->prepare("
                    INSERT INTO staff (staff_id, name, email, password, department, position, is_admin) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([$staff_id, $name, $email, $hashed_password, $department, $position, $is_admin]);
                $_SESSION['success_message'] = '員工新增成功！';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['error_message'] = '員工編號或Email已存在';
                } else {
                    $_SESSION['error_message'] = '新增失敗：' . $e->getMessage();
                }
            }
        } else {
            $_SESSION['error_message'] = '請填寫必要欄位';
        }
        header('Location: staff_management.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>員工管理 - 管理後台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- 導航欄 -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">員工打卡系統 - 管理後台</a>
            <div class="nav-links">
                <a href="dashboard.php">控制台</a>
                <a href="attendance_report.php">出勤報表</a>
                <a href="staff_management.php">員工管理</a>
                                <a href="announcements.php">公告管理</a>

                <span style="color: #ccc;">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        ?>
        
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">員工管理</h1>
            </div>
            
            <!-- 新增員工 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">新增員工</h2>
                
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label class="form-label" for="staff_id">員工編號 *</label>
                        <input type="text" 
                               id="staff_id" 
                               name="staff_id" 
                               class="form-input" 
                               placeholder="例：EMP002" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="name">姓名 *</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-input" 
                               placeholder="請輸入姓名" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               placeholder="請輸入Email">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">密碼 *</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="請輸入密碼" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="department">部門</label>
                        <input type="text" 
                               id="department" 
                               name="department" 
                               class="form-input" 
                               placeholder="請輸入部門">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="position">職位</label>
                        <input type="text" 
                               id="position" 
                               name="position" 
                               class="form-input" 
                               placeholder="請輸入職位">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" 
                                   id="is_admin" 
                                   name="is_admin" 
                                   style="width: auto;">
                            管理員權限
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-success">新增員工</button>
                    </div>
                </form>
            </div>
            
            <!-- 搜尋篩選 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">搜尋篩選</h2>
                
                <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="search">搜尋</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               class="form-input" 
                               placeholder="搜尋員工編號、姓名、Email..."
                               value="<?= escape($search) ?>"
                               style="width: 300px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="department_filter">部門</label>
                        <select id="department_filter" name="department" class="form-input">
                            <option value="">所有部門</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= escape($dept) ?>" 
                                        <?= $department === $dept ? 'selected' : '' ?>>
                                    <?= escape($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary">搜尋</button>
                        <a href="staff_management.php" class="btn btn-primary">重置</a>
                    </div>
                </form>
            </div>
            
            <!-- 員工列表 -->
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">
                    員工列表
                    <?php if ($total_records > 0): ?>
                        <span style="font-size: 1rem; color: #ccc;">(共 <?= $total_records ?> 人)</span>
                    <?php endif; ?>
                </h2>
                
                <?php if ($staff_list): ?>
                    <table class="table" id="data-table">
                        <thead>
                            <tr>
                                <th>員工編號</th>
                                <th>姓名</th>
                                <th>Email</th>
                                <th>部門</th>
                                <th>職位</th>
                                <th>權限</th>
                                <th>出勤記錄</th>
                                <th>最後打卡</th>
                                <th>註冊時間</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_list as $staff): ?>
                                <tr>
                                    <td><?= escape($staff['staff_id']) ?></td>
                                    <td><?= escape($staff['name']) ?></td>
                                    <td><?= escape($staff['email'] ?: '-') ?></td>
                                    <td><?= escape($staff['department'] ?: '-') ?></td>
                                    <td><?= escape($staff['position'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($staff['is_admin']): ?>
                                            <span class="status status-present">管理員</span>
                                        <?php else: ?>
                                            <span class="status">一般員工</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $staff['attendance_count'] ?> 筆</td>
                                    <td><?= $staff['last_attendance'] ? formatDate($staff['last_attendance']) : '-' ?></td>
                                    <td><?= formatDate($staff['created_at']) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <a href="edit_staff.php?id=<?= $staff['id'] ?>" 
                                               class="btn btn-primary btn-sm">編輯</a>
                                            <a href="attendance_report.php?staff_id=<?= urlencode($staff['staff_id']) ?>" 
                                               class="btn btn-primary btn-sm">查看出勤</a>
                                            <?php if ($staff['staff_id'] !== $current_user['staff_id']): ?>
                                                <button onclick="confirmAction('確定要刪除此員工嗎？', function() { deleteStaff(<?= $staff['id'] ?>); })" 
                                                        class="btn btn-danger btn-sm">刪除</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- 分頁 -->
                    <?php if ($total_pages > 1): ?>
                        <div class="text-center mt-3">
                            <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                <?php if ($page > 1): ?>
                                    <?php
                                    $prev_params = $_GET;
                                    $prev_params['page'] = $page - 1;
                                    $prev_url = '?' . http_build_query($prev_params);
                                    ?>
                                    <a href="<?= escape($prev_url) ?>" class="btn btn-primary btn-sm">上一頁</a>
                                <?php endif; ?>
                                
                                <span style="color: #ccc; padding: 0 1rem;">
                                    第 <?= $page ?> 頁，共 <?= $total_pages ?> 頁
                                </span>
                                
                                <?php if ($page < $total_pages): ?>
                                    <?php
                                    $next_params = $_GET;
                                    $next_params['page'] = $page + 1;
                                    $next_url = '?' . http_build_query($next_params);
                                    ?>
                                    <a href="<?= escape($next_url) ?>" class="btn btn-primary btn-sm">下一頁</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center">
                        <p style="color: #ccc;">暫無符合條件的員工資料</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function deleteStaff(staffId) {
            fetch('delete_staff.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: staffId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('員工刪除成功', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message || '刪除失敗', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('系統錯誤，請重試', 'error');
            });
        }
    </script>
</body>
</html>
