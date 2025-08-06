<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
$current_user = getCurrentUser();

// 處理表單提交
if ($_POST) {
    try {
        if (isset($_POST['add_record'])) {
            $staff_id = $_POST['staff_id'];
            $category_id = $_POST['category_id'];
            $project_name = $_POST['project_name'];
            $amount = $_POST['amount'];
            $record_date = $_POST['record_date'];
            $description = $_POST['description'] ?? '';
            
            $stmt = $pdo->prepare("
                INSERT INTO salary_records (staff_id, category_id, project_name, amount, record_date, description, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$staff_id, $category_id, $project_name, $amount, $record_date, $description, $current_user['staff_id']]);
            
            // 更新月度統計
            updateMonthlySalaryStats($pdo, $staff_id, $record_date);
            
            $success_message = "薪資記錄新增成功！";
        }
        
        if (isset($_POST['update_status'])) {
            $record_id = $_POST['record_id'];
            $new_status = $_POST['new_status'];
            
            $update_fields = "status = ?";
            $params = [$new_status, $record_id];
            
            if ($new_status === 'approved') {
                $update_fields .= ", approved_by = ?, approved_at = NOW()";
                array_splice($params, 1, 0, [$current_user['staff_id']]);
            } elseif ($new_status === 'paid') {
                $update_fields .= ", paid_at = NOW()";
            }
            
            $stmt = $pdo->prepare("UPDATE salary_records SET $update_fields WHERE id = ?");
            $stmt->execute($params);
            
            $success_message = "狀態更新成功！";
        }
        
    } catch (Exception $e) {
        $error_message = "操作失敗：" . $e->getMessage();
    }
}

// 獲取篩選參數
$filter_staff = $_GET['staff_id'] ?? '';
$filter_category = $_GET['category_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 建立查詢條件
$where_conditions = ["1=1"];
$params = [];

if ($filter_staff) {
    $where_conditions[] = "sr.staff_id = ?";
    $params[] = $filter_staff;
}

if ($filter_category) {
    $where_conditions[] = "sr.category_id = ?";
    $params[] = $filter_category;
}

if ($filter_status) {
    $where_conditions[] = "sr.status = ?";
    $params[] = $filter_status;
}

if ($filter_month) {
    $where_conditions[] = "DATE_FORMAT(sr.record_date, '%Y-%m') = ?";
    $params[] = $filter_month;
}

$where_clause = implode(' AND ', $where_conditions);

// 獲取薪資記錄
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM salary_records sr 
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("
    SELECT sr.*, s.name as staff_name, sc.name as category_name, sc.color as category_color,
           cb.name as created_by_name, ab.name as approved_by_name
    FROM salary_records sr
    LEFT JOIN staff s ON sr.staff_id = s.staff_id
    LEFT JOIN salary_categories sc ON sr.category_id = sc.id
    LEFT JOIN staff cb ON sr.created_by = cb.staff_id
    LEFT JOIN staff ab ON sr.approved_by = ab.staff_id
    WHERE $where_clause
    ORDER BY sr.record_date DESC, sr.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$salary_records = $stmt->fetchAll();

// 獲取員工列表
$staff_stmt = $pdo->prepare("SELECT staff_id, name FROM staff WHERE is_admin = 0 ORDER BY name");
$staff_stmt->execute();
$staff_list = $staff_stmt->fetchAll();

// 獲取薪資類別
$categories_stmt = $pdo->prepare("SELECT * FROM salary_categories WHERE is_active = 1 ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll();

// 獲取當月統計
$monthly_stats = [];
if ($filter_month) {
    list($year, $month) = explode('-', $filter_month);
    $stats_stmt = $pdo->prepare("
        SELECT sr.staff_id, s.name as staff_name,
               COUNT(*) as record_count,
               SUM(sr.amount) as total_amount,
               AVG(sr.amount) as avg_amount,
               SUM(CASE WHEN sr.status = 'paid' THEN sr.amount ELSE 0 END) as paid_amount
        FROM salary_records sr
        LEFT JOIN staff s ON sr.staff_id = s.staff_id
        WHERE YEAR(sr.record_date) = ? AND MONTH(sr.record_date) = ?
        " . ($filter_staff ? "AND sr.staff_id = '$filter_staff'" : "") . "
        GROUP BY sr.staff_id
        ORDER BY total_amount DESC
    ");
    $stats_stmt->execute([$year, $month]);
    $monthly_stats = $stats_stmt->fetchAll();
}

// 更新月度統計的函數
function updateMonthlySalaryStats($pdo, $staff_id, $record_date) {
    $year = date('Y', strtotime($record_date));
    $month = date('n', strtotime($record_date));
    
    $stats_stmt = $pdo->prepare("
        SELECT COUNT(*) as total_records,
               SUM(amount) as total_amount,
               AVG(amount) as avg_amount
        FROM salary_records 
        WHERE staff_id = ? AND YEAR(record_date) = ? AND MONTH(record_date) = ?
    ");
    $stats_stmt->execute([$staff_id, $year, $month]);
    $stats = $stats_stmt->fetch();
    
    $pdo->prepare("
        INSERT INTO salary_monthly_stats (staff_id, year, month, total_amount, total_records, avg_amount)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        total_amount = VALUES(total_amount),
        total_records = VALUES(total_records),
        avg_amount = VALUES(avg_amount)
    ")->execute([
        $staff_id, $year, $month,
        $stats['total_amount'] ?: 0,
        $stats['total_records'] ?: 0,
        $stats['avg_amount'] ?: 0
    ]);
}

function getStatusBadge($status) {
    $badges = [
        'pending' => ['class' => 'badge-warning', 'text' => '待處理'],
        'approved' => ['class' => 'badge-info', 'text' => '已批准'],
        'paid' => ['class' => 'badge-success', 'text' => '已支付'],
        'cancelled' => ['class' => 'badge-danger', 'text' => '已取消']
    ];
    
    $badge = $badges[$status] ?? ['class' => 'badge-secondary', 'text' => $status];
    return "<span class='badge {$badge['class']}'>{$badge['text']}</span>";
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>薪資管理 - 員工打卡系統</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .salary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .salary-stat-card {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            border-left: 4px solid;
        }
        
        .salary-stat-card.total { border-left-color: #28a745; }
        .salary-stat-card.records { border-left-color: #17a2b8; }
        .salary-stat-card.average { border-left-color: #ffc107; }
        .salary-stat-card.paid { border-left-color: #007bff; }
        
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
        
        .category-color {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .amount-cell {
            font-weight: bold;
            color: #28a745;
        }
        
        .status-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .quick-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .quick-filters {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .salary-stats {
                grid-template-columns: 1fr;
            }
            
            .status-actions {
                flex-direction: column;
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
                <a href="salary_management.php" class="nav-link active">薪資管理</a>
                <a href="salary_reports.php" class="nav-link">薪資報表</a>
                <a href="work_reports.php" class="nav-link">工作報告</a>
                <a href="announcements.php" class="nav-link">公告管理</a>
                <span class="nav-user">歡迎，<?= escape($current_user['name']) ?></span>
                <a href="../auth/logout.php" class="nav-link logout">登出</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- 訊息提示 -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?= escape($success_message) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?= escape($error_message) ?></div>
        <?php endif; ?>

        <!-- 月度統計 -->
        <?php if (!empty($monthly_stats)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📊 <?= escape($filter_month) ?> 月度薪資統計</h2>
                </div>
                
                <div class="salary-stats">
                    <?php 
                    $total_amount = array_sum(array_column($monthly_stats, 'total_amount'));
                    $total_records = array_sum(array_column($monthly_stats, 'record_count'));
                    $total_paid = array_sum(array_column($monthly_stats, 'paid_amount'));
                    $avg_amount = $total_records > 0 ? $total_amount / $total_records : 0;
                    ?>
                    
                    <div class="salary-stat-card total">
                        <div class="stat-value">$<?= number_format($total_amount) ?></div>
                        <div class="stat-label">總薪資金額</div>
                    </div>
                    
                    <div class="salary-stat-card records">
                        <div class="stat-value"><?= $total_records ?></div>
                        <div class="stat-label">薪資記錄數</div>
                    </div>
                    
                    <div class="salary-stat-card average">
                        <div class="stat-value">$<?= number_format($avg_amount) ?></div>
                        <div class="stat-label">平均金額</div>
                    </div>
                    
                    <div class="salary-stat-card paid">
                        <div class="stat-value">$<?= number_format($total_paid) ?></div>
                        <div class="stat-label">已支付金額</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 新增薪資記錄 -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">💰 新增薪資記錄</h2>
            </div>
            
            <form method="POST" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">員工 *</label>
                        <select name="staff_id" class="form-input" required>
                            <option value="">請選擇員工</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?= escape($staff['staff_id']) ?>">
                                    <?= escape($staff['name']) ?> (<?= escape($staff['staff_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">薪資類別 *</label>
                        <select name="category_id" class="form-input" required>
                            <option value="">請選擇類別</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>">
                                    <?= escape($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">項目名稱 *</label>
                        <input type="text" name="project_name" class="form-input" 
                               placeholder="例如：水晶網站升級案子" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">金額 *</label>
                        <input type="number" name="amount" class="form-input" 
                               placeholder="2000" min="0" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">記錄日期 *</label>
                        <input type="date" name="record_date" class="form-input" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">詳細描述</label>
                    <textarea name="description" class="form-input" rows="3" 
                              placeholder="項目詳細說明..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="add_record" class="btn btn-primary">
                        💰 新增薪資記錄
                    </button>
                </div>
            </form>
        </div>

        <!-- 篩選器 -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">🔍 篩選薪資記錄</h2>
            </div>
            
            <form method="GET" class="form">
                <div class="quick-filters">
                    <div class="form-group">
                        <select name="staff_id" class="form-input">
                            <option value="">所有員工</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?= escape($staff['staff_id']) ?>" 
                                        <?= $filter_staff === $staff['staff_id'] ? 'selected' : '' ?>>
                                    <?= escape($staff['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <select name="category_id" class="form-input">
                            <option value="">所有類別</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" 
                                        <?= $filter_category == $category['id'] ? 'selected' : '' ?>>
                                    <?= escape($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <select name="status" class="form-input">
                            <option value="">所有狀態</option>
                            <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>待處理</option>
                            <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>已批准</option>
                            <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>已支付</option>
                            <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>已取消</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <input type="month" name="month" class="form-input" value="<?= escape($filter_month) ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">搜尋</button>
                        <a href="salary_management.php" class="btn btn-secondary">重置</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- 薪資記錄列表 -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📋 薪資記錄列表</h2>
                <div class="card-actions">
                    <a href="salary_reports.php" class="btn btn-primary">📊 詳細報表</a>
                </div>
            </div>
            
            <?php if (!empty($salary_records)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>員工</th>
                                <th>類別</th>
                                <th>項目名稱</th>
                                <th>金額</th>
                                <th>狀態</th>
                                <th>創建者</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salary_records as $record): ?>
                                <tr>
                                    <td><?= date('Y/m/d', strtotime($record['record_date'])) ?></td>
                                    <td><?= escape($record['staff_name']) ?></td>
                                    <td>
                                        <span class="category-color" style="background-color: <?= escape($record['category_color']) ?>"></span>
                                        <?= escape($record['category_name']) ?>
                                    </td>
                                    <td><?= escape($record['project_name']) ?></td>
                                    <td class="amount-cell">$<?= number_format($record['amount']) ?></td>
                                    <td><?= getStatusBadge($record['status']) ?></td>
                                    <td><?= escape($record['created_by_name']) ?></td>
                                    <td>
                                        <div class="status-actions">
                                            <?php if ($record['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('確定要批准這筆薪資記錄嗎？')">
                                                    <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                                                    <input type="hidden" name="new_status" value="approved">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-success">
                                                        ✅ 批准
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('確定要取消這筆薪資記錄嗎？')">
                                                    <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                                                    <input type="hidden" name="new_status" value="cancelled">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-danger">
                                                        ❌ 取消
                                                    </button>
                                                </form>
                                            <?php elseif ($record['status'] === 'approved'): ?>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('確定要標記為已支付嗎？')">
                                                    <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                                                    <input type="hidden" name="new_status" value="paid">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                                        💵 標記已支付
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">無可用操作</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- 分頁 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>" 
                               class="btn btn-sm btn-secondary">上一頁</a>
                        <?php endif; ?>
                        
                        <span style="color: #ccc; padding: 0 1rem;">
                            第 <?= $page ?> 頁，共 <?= $total_pages ?> 頁 (<?= $total_records ?> 筆記錄)
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>" 
                               class="btn btn-sm btn-secondary">下一頁</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center" style="padding: 2rem;">
                    <p style="color: #ccc;">目前沒有薪資記錄</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../includes/responsive_nav.js"></script>
</body>
</html>
