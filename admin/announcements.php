<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
$current_user = getCurrentUser();

// 處理新增/編輯公告
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $start_date = $_POST['start_date'] ?? date('Y-m-d H:i');
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        if (empty($title) || empty($content)) {
            $error = '標題和內容不能為空';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("
                        INSERT INTO announcements (title, content, type, is_active, start_date, end_date, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$title, $content, $type, $is_active, $start_date, $end_date, $current_user['staff_id']]);
                    $success = '公告新增成功';
                } else {
                    $id = $_POST['id'] ?? 0;
                    $stmt = $pdo->prepare("
                        UPDATE announcements 
                        SET title = ?, content = ?, type = ?, is_active = ?, start_date = ?, end_date = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $content, $type, $is_active, $start_date, $end_date, $id]);
                    $success = '公告更新成功';
                }
            } catch (PDOException $e) {
                $error = '操作失敗：' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            $success = '公告刪除成功';
        } catch (PDOException $e) {
            $error = '刪除失敗：' . $e->getMessage();
        }
    }
}

// 取得編輯的公告
$edit_announcement = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_announcement = $stmt->fetch();
}

// 取得公告列表
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT a.*, s.name as creator_name,
           (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) as read_count
    FROM announcements a 
    LEFT JOIN staff s ON a.created_by = s.staff_id 
    WHERE $where_clause 
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// 取得員工總數
$total_staff_stmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE is_admin = 0");
$total_staff_stmt->execute();
$total_staff = $total_staff_stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>公告管理 - 打卡系統</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- 導航欄 -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo">員工打卡系統 - 管理後台</a>
            <div class="nav-links">
                <a href="dashboard.php">控制台</a>
                <a href="attendance_report.php">出勤報表</a>
                <a href="staff_management.php">員工管理</a>
                <a href="announcements.php" style="color: #ffd700;">公告管理</a>
                <span style="color: #ccc;">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= escape($success) ?></div>
        <?php endif; ?>

        <!-- 公告表單 -->
        <div class="card">
            <h2><?= $edit_announcement ? '編輯公告' : '新增公告' ?></h2>
            <form method="POST" class="form">
                <input type="hidden" name="action" value="<?= $edit_announcement ? 'update' : 'create' ?>">
                <?php if ($edit_announcement): ?>
                    <input type="hidden" name="id" value="<?= $edit_announcement['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">公告標題</label>
                    <input type="text" id="title" name="title" required 
                           value="<?= escape($edit_announcement['title'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="content">公告內容</label>
                    <textarea id="content" name="content" rows="5" required><?= escape($edit_announcement['content'] ?? '') ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">公告類型</label>
                        <select id="type" name="type">
                            <option value="info" <?= ($edit_announcement['type'] ?? '') === 'info' ? 'selected' : '' ?>>資訊</option>
                            <option value="warning" <?= ($edit_announcement['type'] ?? '') === 'warning' ? 'selected' : '' ?>>警告</option>
                            <option value="urgent" <?= ($edit_announcement['type'] ?? '') === 'urgent' ? 'selected' : '' ?>>緊急</option>
                            <option value="success" <?= ($edit_announcement['type'] ?? '') === 'success' ? 'selected' : '' ?>>成功</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" 
                                   <?= ($edit_announcement['is_active'] ?? 1) ? 'checked' : '' ?>>
                            啟用公告
                        </label>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">開始時間</label>
                        <input type="datetime-local" id="start_date" name="start_date" 
                               value="<?= $edit_announcement ? date('Y-m-d\TH:i', strtotime($edit_announcement['start_date'])) : date('Y-m-d\TH:i') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">結束時間（可選）</label>
                        <input type="datetime-local" id="end_date" name="end_date"
                               value="<?= $edit_announcement && $edit_announcement['end_date'] ? date('Y-m-d\TH:i', strtotime($edit_announcement['end_date'])) : '' ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_announcement ? '更新公告' : '新增公告' ?>
                    </button>
                    <?php if ($edit_announcement): ?>
                        <a href="announcements.php" class="btn btn-secondary">取消編輯</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- 搜尋和篩選 -->
        <div class="card">
            <h2>公告列表</h2>
            <form method="GET" class="search-form">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="search" placeholder="搜尋標題或內容..." 
                               value="<?= escape($search) ?>">
                    </div>
                    <div class="form-group">
                        <select name="type">
                            <option value="">所有類型</option>
                            <option value="info" <?= $type_filter === 'info' ? 'selected' : '' ?>>資訊</option>
                            <option value="warning" <?= $type_filter === 'warning' ? 'selected' : '' ?>>警告</option>
                            <option value="urgent" <?= $type_filter === 'urgent' ? 'selected' : '' ?>>緊急</option>
                            <option value="success" <?= $type_filter === 'success' ? 'selected' : '' ?>>成功</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="status">
                            <option value="">所有狀態</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>啟用</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>停用</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">搜尋</button>
                    <a href="announcements.php" class="btn btn-secondary">重置</a>
                </div>
            </form>
        </div>

        <!-- 公告列表 -->
        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>標題</th>
                            <th>類型</th>
                            <th>狀態</th>
                            <th>開始時間</th>
                            <th>結束時間</th>
                            <th>已讀人數</th>
                            <th>建立者</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $announcement): ?>
                            <tr>
                                <td>
                                    <div class="announcement-title"><?= escape($announcement['title']) ?></div>
                                    <div class="announcement-content" style="font-size: 0.9rem; color: #ccc; margin-top: 0.5rem;">
                                        <?= escape(mb_substr($announcement['content'], 0, 100)) ?>
                                        <?= mb_strlen($announcement['content']) > 100 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="announcement-type announcement-type-<?= $announcement['type'] ?>">
                                        <?php
                                        $type_text = [
                                            'info' => '資訊',
                                            'warning' => '警告', 
                                            'urgent' => '緊急',
                                            'success' => '成功'
                                        ];
                                        echo $type_text[$announcement['type']] ?? $announcement['type'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status <?= $announcement['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $announcement['is_active'] ? '啟用' : '停用' ?>
                                    </span>
                                </td>
                                <td><?= date('m/d H:i', strtotime($announcement['start_date'])) ?></td>
                                <td><?= $announcement['end_date'] ? date('m/d H:i', strtotime($announcement['end_date'])) : '-' ?></td>
                                <td>
                                    <?= $announcement['read_count'] ?> / <?= $total_staff ?>
                                    <small style="color: #ccc;">
                                        (<?= $total_staff > 0 ? round($announcement['read_count'] / $total_staff * 100) : 0 ?>%)
                                    </small>
                                </td>
                                <td><?= escape($announcement['creator_name']) ?></td>
                                <td>
                                    <a href="?edit=<?= $announcement['id'] ?>" class="btn btn-sm btn-primary">編輯</a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('確定要刪除此公告嗎？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $announcement['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">刪除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($announcements)): ?>
                            <tr>
                                <td colspan="8" class="text-center" style="color: #ccc; padding: 2rem;">
                                    沒有找到公告
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
