<?php
// 檢查是否已登入
function isLoggedIn() {
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
}

// 檢查是否已登入（舊版本，保持相容性）
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

// 檢查管理員權限
function requireAdmin() {
    requireLogin();
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        header('Location: ../staff/dashboard.php');
        exit;
    }
}

// 檢查員工權限（非管理員）
function requireStaff() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        header('Location: ../admin/dashboard.php');
        exit;
    }
}

// 取得當前使用者資訊
function getCurrentUser() {
    return [
        'staff_id' => $_SESSION['staff_id'] ?? null,
        'name' => $_SESSION['staff_name'] ?? null,
        'is_admin' => $_SESSION['is_admin'] ?? false,
        'department' => $_SESSION['department'] ?? null,
        'position' => $_SESSION['position'] ?? null
    ];
}

// 格式化時間顯示
function formatDateTime($datetime) {
    if (!$datetime) return '-';
    return date('Y-m-d H:i:s', strtotime($datetime));
}

// 格式化日期顯示
function formatDate($date) {
    if (!$date) return '-';
    return date('Y-m-d', strtotime($date));
}

// 計算工作時數
function calculateWorkHours($check_in, $check_out) {
    if (!$check_in || !$check_out) return 0;
    
    $in_time = strtotime($check_in);
    $out_time = strtotime($check_out);
    
    if ($out_time <= $in_time) return 0;
    
    $hours = ($out_time - $in_time) / 3600;
    return round($hours, 2);
}

// 判斷出勤狀態
function getAttendanceStatus($check_in, $check_out, $work_date) {
    if (!$check_in) return 'absent';
    
    $check_in_time = date('H:i', strtotime($check_in));
    $standard_start = '09:00';
    
    if ($check_in_time > $standard_start) {
        return 'late';
    }
    
    if ($check_out) {
        $check_out_time = date('H:i', strtotime($check_out));
        $standard_end = '18:00';
        
        if ($check_out_time < $standard_end) {
            return 'early_leave';
        }
    }
    
    return 'present';
}

// 取得狀態顯示文字
function getStatusText($status) {
    $status_map = [
        'present' => '正常',
        'late' => '遲到',
        'absent' => '缺席',
        'early_leave' => '早退'
    ];
    
    return $status_map[$status] ?? '未知';
}

// 取得狀態CSS類別
function getStatusClass($status) {
    $class_map = [
        'present' => 'status-present',
        'late' => 'status-late',
        'absent' => 'status-absent',
        'early_leave' => 'status-late'
    ];
    
    return $class_map[$status] ?? '';
}

// 取得客戶端IP地址
function getClientIP() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // 處理多個IP的情況，取第一個
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // 驗證IP格式
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// 取得客戶端User Agent
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
}

// 計算休息時間（分鐘）
function calculateBreakMinutes($break_start, $break_end) {
    if (!$break_start || !$break_end) return 0;
    
    $start_time = strtotime($break_start);
    $end_time = strtotime($break_end);
    
    if ($end_time <= $start_time) return 0;
    
    $minutes = ($end_time - $start_time) / 60;
    return round($minutes);
}

// 格式化休息時間顯示
function formatBreakTime($minutes) {
    if ($minutes <= 0) return '0分鐘';
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($hours > 0) {
        return $hours . '小時' . ($mins > 0 ? $mins . '分鐘' : '');
    } else {
        return $mins . '分鐘';
    }
}

// 取得休息類型文字
function getBreakTypeText($type) {
    $type_map = [
        'lunch' => '午餐休息',
        'coffee' => '茶水休息',
        'personal' => '個人事務',
        'other' => '其他'
    ];
    
    return $type_map[$type] ?? '未知';
}

// 安全的HTML輸出
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
