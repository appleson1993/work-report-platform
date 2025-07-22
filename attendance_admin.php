<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// 檢查管理員權限（使用安全增強版本）
requireStrictAdmin();

$db = new Database();

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $action = getPostValue('action');
        
        if ($action === 'get_all_attendance') {
            $month = getPostValue('month', date('Y-m'));
            $userId = getPostValueInt('user_id', 0);
            
            $whereClause = 'WHERE ar.work_date >= ? AND ar.work_date < ? + INTERVAL 1 MONTH';
            $params = [$month . '-01', $month . '-01'];
            
            if ($userId > 0) {
                $whereClause .= ' AND ar.user_id = ?';
                $params[] = $userId;
            }
            
            $records = $db->fetchAll(
                "SELECT ar.*, u.name as user_name,
                        COALESCE(SUM(ot.overtime_hours), 0) as overtime_hours,
                        (ar.work_hours + COALESCE(SUM(ot.overtime_hours), 0)) as total_work_hours
                 FROM attendance_records ar
                 JOIN users u ON ar.user_id = u.id
                 LEFT JOIN overtime_records ot ON ar.user_id = ot.user_id 
                                                AND ar.work_date = ot.work_date 
                                                AND ot.overtime_hours IS NOT NULL
                                                AND ot.status = 'ended'
                 $whereClause
                 GROUP BY ar.id, ar.user_id, ar.work_date, ar.work_hours, u.name
                 ORDER BY ar.work_date DESC, u.name",
                $params
            );
            
            // 計算統計數據（使用總工時包含加班）
            $stats = [
                'total_records' => count($records),
                'total_users' => count(array_unique(array_column($records, 'user_id'))),
                'total_hours' => array_sum(array_column($records, 'total_work_hours')),
                'total_overtime' => array_sum(array_column($records, 'overtime_hours')),
                'avg_hours' => 0
            ];
            
            if ($stats['total_records'] > 0) {
                $stats['avg_hours'] = round($stats['total_hours'] / $stats['total_records'], 2);
            }
            
            jsonResponse(true, '取得打卡數據成功', [
                'records' => $records,
                'stats' => $stats,
                'month' => $month
            ]);
            
        } elseif ($action === 'export_attendance') {
            $month = getPostValue('month', date('Y-m'));
            
            $records = $db->fetchAll(
                'SELECT ar.*, u.name as user_name,
                        COALESCE(SUM(ot.overtime_hours), 0) as overtime_hours,
                        (ar.work_hours + COALESCE(SUM(ot.overtime_hours), 0)) as total_work_hours
                 FROM attendance_records ar
                 JOIN users u ON ar.user_id = u.id
                 LEFT JOIN overtime_records ot ON ar.user_id = ot.user_id 
                                                AND ar.work_date = ot.work_date 
                                                AND ot.overtime_hours IS NOT NULL
                                                AND ot.status = "ended"
                 WHERE ar.work_date >= ? AND ar.work_date < ? + INTERVAL 1 MONTH
                 GROUP BY ar.id, ar.user_id, ar.work_date, ar.work_hours, u.name
                 ORDER BY ar.work_date DESC, u.name',
                [$month . '-01', $month . '-01']
            );
            
            jsonResponse(true, '匯出數據準備完成', $records);
        }
        
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage());
    }
    
    exit;
}

// 取得所有使用者
$users = $db->fetchAll('SELECT id, name FROM users ORDER BY name');
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>打卡系統管理 - WorkLog Manager</title>
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
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .table th {
            border-top: none;
            background-color: #f8f9fc;
        }

        /* RWD 優化 */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1rem;
            }
            
            .navbar-nav .nav-link {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }
            
            .stats-card .card-body {
                padding: 1rem;
            }
            
            .stats-card h5 {
                font-size: 0.875rem;
            }
            
            .stats-card h2 {
                font-size: 1.5rem;
            }
            
            .container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand i {
                display: none;
            }
            
            .navbar-nav .nav-link i {
                margin-right: 0.25rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .card-body {
                padding: 0.75rem;
            }
        }

        /* 表格響應式優化 */
        .table-responsive {
            border-radius: 15px;
        }

        @media (max-width: 768px) {
            .table td, .table th {
                padding: 0.5rem 0.25rem;
                font-size: 0.875rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- 導航列 -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i>
                <span class="d-none d-sm-inline">打卡系統管理</span>
                <span class="d-sm-none">打卡管理</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="admin.php">
                        <i class="fas fa-arrow-left me-1"></i>
                        <span class="d-none d-md-inline">回到管理面板</span>
                        <span class="d-md-none">返回</span>
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i>登出
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- 統計概覽 -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">總記錄數</h5>
                        <h2 class="mb-0" id="total-records">-</h2>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">總員工數</h5>
                        <h2 class="mb-0" id="total-users">-</h2>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">總工時</h5>
                        <h2 class="mb-0" id="total-hours">-</h2>
                        <small class="text-light">含加班時數</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">加班時數</h5>
                        <h2 class="mb-0" id="total-overtime">-</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- 篩選控制 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>篩選條件
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-end g-3">
                            <div class="col-lg-3 col-md-6">
                                <label for="month-select" class="form-label">選擇月份</label>
                                <input type="month" class="form-control" id="month-select" value="<?php echo date('Y-m'); ?>">
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label for="user-select" class="form-label">選擇員工</label>
                                <select class="form-select" id="user-select">
                                    <option value="0">所有員工</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <button class="btn btn-primary w-100" onclick="loadAttendanceData()">
                                    <i class="fas fa-search me-2"></i>
                                    <span class="d-none d-sm-inline">查詢</span>
                                    <span class="d-sm-none">查詢</span>
                                </button>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <button class="btn btn-success w-100" onclick="exportData()">
                                    <i class="fas fa-download me-2"></i>
                                    <span class="d-none d-sm-inline">匯出 CSV</span>
                                    <span class="d-sm-none">匯出</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 打卡記錄表格 -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>打卡記錄
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>日期</th>
                                        <th class="d-none d-md-table-cell">員工姓名</th>
                                        <th class="d-md-none">姓名</th>
                                        <th class="d-none d-lg-table-cell">上班時間</th>
                                        <th class="d-none d-lg-table-cell">下班時間</th>
                                        <th>基本工時</th>
                                        <th class="d-none d-sm-table-cell">加班時數</th>
                                        <th>總工時</th>
                                        <th class="d-none d-sm-table-cell">狀態</th>
                                        <th class="d-none d-xl-table-cell">備註</th>
                                    </tr>
                                </thead>
                                <tbody id="attendance-table-body">
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="fas fa-spinner fa-spin"></i> 載入中...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            loadAttendanceData();
        });

        // 載入打卡數據
        function loadAttendanceData() {
            const month = document.getElementById('month-select').value;
            const userId = document.getElementById('user-select').value;
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_all_attendance');
            formData.append('month', month);
            formData.append('user_id', userId);
            
            fetch('attendance_admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayAttendanceData(data.data);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '載入失敗',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('載入數據失敗:', error);
                Swal.fire({
                    icon: 'error',
                    title: '網路錯誤',
                    text: '載入數據失敗，請重試'
                });
            });
        }

        // 顯示打卡數據
        function displayAttendanceData(data) {
            // 更新統計數字
            document.getElementById('total-records').textContent = data.stats.total_records;
            document.getElementById('total-users').textContent = data.stats.total_users;
            document.getElementById('total-hours').textContent = data.stats.total_hours;
            document.getElementById('total-overtime').textContent = data.stats.total_overtime || 0;
            
            // 更新表格
            const tbody = document.getElementById('attendance-table-body');
            
            if (data.records.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <br>暫無打卡記錄
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            data.records.forEach(record => {
                const checkInTime = record.check_in_time ? 
                    new Date(record.check_in_time).toLocaleTimeString('zh-TW', { hour12: false }) : '-';
                const checkOutTime = record.check_out_time ? 
                    new Date(record.check_out_time).toLocaleTimeString('zh-TW', { hour12: false }) : '-';
                
                const statusBadge = getStatusBadge(record.status);
                const statusText = getStatusText(record.status);
                
                const baseHours = record.work_hours || 0;
                const overtimeHours = record.overtime_hours || 0;
                const totalHours = record.total_work_hours || baseHours;
                
                html += `
                    <tr>
                        <td>${record.work_date}</td>
                        <td class="d-none d-md-table-cell">${record.user_name}</td>
                        <td class="d-md-none">${record.user_name.substring(0, 4)}</td>
                        <td class="d-none d-lg-table-cell">${checkInTime}</td>
                        <td class="d-none d-lg-table-cell">${checkOutTime}</td>
                        <td>${baseHours}<span class="d-none d-sm-inline"> 小時</span><span class="d-sm-none">h</span></td>
                        <td class="d-none d-sm-table-cell">
                            <span class="${overtimeHours > 0 ? 'text-warning fw-bold' : ''}">${overtimeHours}</span>
                            <span class="d-none d-sm-inline"> 小時</span><span class="d-sm-none">h</span>
                        </td>
                        <td>
                            <span class="fw-bold text-primary">${totalHours}</span>
                            <span class="d-none d-sm-inline"> 小時</span><span class="d-sm-none">h</span>
                        </td>
                        <td class="d-none d-sm-table-cell"><span class="badge ${statusBadge}">${statusText}</span></td>
                        <td class="d-none d-xl-table-cell">${record.notes || '-'}</td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        // 獲取狀態徽章樣式
        function getStatusBadge(status) {
            const badgeMap = {
                'checked_in': 'bg-warning',
                'checked_out': 'bg-success',
                'absent': 'bg-danger'
            };
            return badgeMap[status] || 'bg-secondary';
        }

        // 獲取狀態文字
        function getStatusText(status) {
            const textMap = {
                'checked_in': '上班中',
                'checked_out': '正常',
                'absent': '缺席'
            };
            return textMap[status] || '未知';
        }

        // 匯出數據
        function exportData() {
            const month = document.getElementById('month-select').value;
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'export_attendance');
            formData.append('month', month);
            
            fetch('attendance_admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    exportToCSV(data.data, month);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '匯出失敗',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('匯出失敗:', error);
                Swal.fire({
                    icon: 'error',
                    title: '網路錯誤',
                    text: '匯出失敗，請重試'
                });
            });
        }

        // 匯出為 CSV
        function exportToCSV(records, month) {
            let csv = '日期,員工姓名,上班時間,下班時間,基本工時,加班時數,總工時,狀態,備註\n';
            
            records.forEach(record => {
                const checkInTime = record.check_in_time ? 
                    new Date(record.check_in_time).toLocaleString('zh-TW') : '';
                const checkOutTime = record.check_out_time ? 
                    new Date(record.check_out_time).toLocaleString('zh-TW') : '';
                const statusText = getStatusText(record.status);
                const baseHours = record.work_hours || 0;
                const overtimeHours = record.overtime_hours || 0;
                const totalHours = record.total_work_hours || baseHours;
                
                csv += `${record.work_date},${record.user_name},${checkInTime},${checkOutTime},${baseHours},${overtimeHours},${totalHours},${statusText},${record.notes || ''}\n`;
            });
            
            // 下載檔案
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `打卡記錄_${month}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            Swal.fire({
                icon: 'success',
                title: '匯出成功',
                text: '檔案已下載'
            });
        }
    </script>
</body>
</html>
