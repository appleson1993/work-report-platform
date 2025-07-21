<?php
// 安全配置檔案
// 防止直接訪問
if (!defined('SECURITY_CHECK')) {
    die('Direct access not allowed');
}

// CSRF 防護
class CSRFProtection {
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function verifyToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function getTokenInput() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

// SQL 注入防護 - 已在Database類中實現預處理語句

// XSS 防護增強
function sanitizeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// 檔案上傳安全檢查
function validateFileUpload($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('不允許的檔案類型');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('檔案大小超過限制（5MB）');
    }
    
    return true;
}

// 密碼強度檢查
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return '密碼長度至少需要8個字符';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return '密碼需要包含至少一個大寫字母';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return '密碼需要包含至少一個小寫字母';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return '密碼需要包含至少一個數字';
    }
    
    return true;
}

// 登入嘗試限制
class LoginAttemptLimiter {
    private static $maxAttempts = 5;
    private static $lockoutTime = 900; // 15分鐘
    
    public static function recordAttempt($email) {
        $key = 'login_attempts_' . md5($email);
        $attempts = $_SESSION[$key] ?? [];
        $attempts[] = time();
        
        // 清理過期的嘗試
        $attempts = array_filter($attempts, function($time) {
            return (time() - $time) < self::$lockoutTime;
        });
        
        $_SESSION[$key] = $attempts;
        return count($attempts);
    }
    
    public static function isLocked($email) {
        $key = 'login_attempts_' . md5($email);
        $attempts = $_SESSION[$key] ?? [];
        
        // 清理過期的嘗試
        $attempts = array_filter($attempts, function($time) {
            return (time() - $time) < self::$lockoutTime;
        });
        
        return count($attempts) >= self::$maxAttempts;
    }
    
    public static function clearAttempts($email) {
        $key = 'login_attempts_' . md5($email);
        unset($_SESSION[$key]);
    }
    
    public static function getRemainingLockTime($email) {
        $key = 'login_attempts_' . md5($email);
        $attempts = $_SESSION[$key] ?? [];
        
        if (count($attempts) >= self::$maxAttempts) {
            $oldestAttempt = min($attempts);
            $unlockTime = $oldestAttempt + self::$lockoutTime;
            return max(0, $unlockTime - time());
        }
        
        return 0;
    }
}

// 操作日誌記錄
class AuditLogger {
    private static $db = null;
    
    private static function getDB() {
        if (self::$db === null) {
            self::$db = new Database();
        }
        return self::$db;
    }
    
    public static function log($action, $details = '', $userId = null) {
        try {
            $userId = $userId ?? ($_SESSION['user_id'] ?? null);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            self::getDB()->execute(
                'INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
                [$userId, $action, $details, $ipAddress, $userAgent]
            );
        } catch (Exception $e) {
            // 記錄失敗不應該影響主要功能
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}

// Session 安全配置
function secureSession() {
    // 設置安全的 session 配置
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.cookie_samesite', 'Strict');
    
    // 定期重新生成 session ID
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5分鐘
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// 輸入過濾增強
function advancedSanitize($input, $type = 'string') {
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

// 檢查管理員權限的增強版本
function requireStrictAdmin() {
    requireLogin();
    if (!isAdmin()) {
        AuditLogger::log('unauthorized_admin_access', 'Attempted to access admin function');
        header('Location: dashboard.php');
        exit;
    }
    
    // 檢查 CSRF token（如果是 POST 請求）
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
        if (!isset($_POST['csrf_token']) || !CSRFProtection::verifyToken($_POST['csrf_token'])) {
            AuditLogger::log('csrf_token_failure', 'Invalid CSRF token on admin action');
            jsonResponse(false, 'Security token invalid');
        }
    }
}
?>
