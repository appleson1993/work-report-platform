<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
$current_user = getCurrentUser();

$staff_id = $_GET['id'] ?? '';
if (!$staff_id) {
    header('Location: staff_management.php');
    exit;
}

// 取得員工資料
$stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->execute([$staff_id]);
$staff = $stmt->fetch();

if (!$staff) {
    $_SESSION['error_message'] = '找不到該員工';
    header('Location: staff_management.php');
    exit;
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $new_password = trim($_POST['new_password'] ?? '');
    
    if ($name) {
        try {
            $update_fields = ["name = ?", "email = ?", "department = ?", "position = ?", "is_admin = ?"];
            $params = [$name, $email, $department, $position, $is_admin];
            
            // 如果有新密碼，加入更新
            if ($new_password) {
                $update_fields[] = "password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            $params[] = $staff_id;
            
            $update_stmt = $pdo->prepare("
                UPDATE staff 
                SET " . implode(', ', $update_fields) . ", updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update_stmt->execute($params);
            
            $_SESSION['success_message'] = '員工資料更新成功！';
            header('Location: staff_management.php');
            exit;
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $_SESSION['error_message'] = 'Email已被使用';
            } else {
                $_SESSION['error_message'] = '更新失敗：' . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_message'] = '請填寫必要欄位';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯員工 - 管理後台</title>
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
        ?>
        
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">編輯員工 - <?= escape($staff['name']) ?></h1>
            </div>
            
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2 style="color: #fff; margin: 0;">基本資料</h2>
                    <a href="staff_management.php" class="btn btn-primary">返回列表</a>
                </div>
                
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label" for="staff_id">員工編號</label>
                        <input type="text" 
                               id="staff_id" 
                               class="form-input" 
                               value="<?= escape($staff['staff_id']) ?>" 
                               disabled>
                        <small style="color: #999;">員工編號無法修改</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="name">姓名 *</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-input" 
                               value="<?= escape($staff['name']) ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               value="<?= escape($staff['email']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="department">部門</label>
                        <input type="text" 
                               id="department" 
                               name="department" 
                               class="form-input" 
                               value="<?= escape($staff['department']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="position">職位</label>
                        <input type="text" 
                               id="position" 
                               name="position" 
                               class="form-input" 
                               value="<?= escape($staff['position']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="new_password">新密碼</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-input" 
                               placeholder="如不修改密碼請留空">
                        <small style="color: #999;">留空表示不修改密碼</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" 
                                   id="is_admin" 
                                   name="is_admin" 
                                   style="width: auto;"
                                   <?= $staff['is_admin'] ? 'checked' : '' ?>>
                            管理員權限
                        </label>
                        <?php if ($staff['staff_id'] === $current_user['staff_id']): ?>
                            <small style="color: #ff9090;">注意：取消自己的管理員權限將無法再訪問管理後台</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-success">更新資料</button>
                    </div>
                </form>
            </div>
            
            <!-- 員工統計 -->
            <?php
            // 取得該員工的統計資料
            $stats_stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = 'early_leave' THEN 1 ELSE 0 END) as early_leave_days,
                    AVG(total_hours) as avg_hours,
                    SUM(total_hours) as total_hours,
                    MAX(work_date) as last_attendance,
                    MIN(work_date) as first_attendance
                FROM attendance 
                WHERE staff_id = ?
            ");
            $stats_stmt->execute([$staff['staff_id']]);
            $stats = $stats_stmt->fetch();
            ?>
            
            <div class="card">
                <h2 style="color: #fff; margin-bottom: 1rem;">出勤統計</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_days'] ?: '0' ?></div>
                        <div class="stat-label">總出勤天數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['present_days'] ?: '0' ?></div>
                        <div class="stat-label">正常出勤</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['late_days'] ?: '0' ?></div>
                        <div class="stat-label">遲到次數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['early_leave_days'] ?: '0' ?></div>
                        <div class="stat-label">早退次數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= round($stats['total_hours'] ?: 0, 1) ?></div>
                        <div class="stat-label">總工作時數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= round($stats['avg_hours'] ?: 0, 1) ?></div>
                        <div class="stat-label">平均工時</div>
                    </div>
                </div>
                
                <?php if ($stats['first_attendance']): ?>
                    <div style="margin-top: 1rem; color: #ccc;">
                        <p>首次打卡：<?= formatDate($stats['first_attendance']) ?></p>
                        <p>最後打卡：<?= formatDate($stats['last_attendance']) ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="attendance_report.php?staff_id=<?= urlencode($staff['staff_id']) ?>" 
                       class="btn btn-primary">查看詳細出勤記錄</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
