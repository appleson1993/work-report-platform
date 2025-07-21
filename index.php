<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();

// 如果已登入，重導向到適當頁面
if (isLoggedIn()) {
    // 管理員可以選擇進入前台或後台，預設進入前台
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// 處理登入
if ($_SERVER['REQUEST_METHOD'] === 'POST' && getPostValue('action') === 'login') {
    $email = getPostValueSanitized('email');
    $password = getPostValue('password');
    
    if (empty($email) || empty($password)) {
        $error = '請填寫所有欄位';
    } elseif (LoginAttemptLimiter::isLocked($email)) {
        $remainingTime = LoginAttemptLimiter::getRemainingLockTime($email);
        $minutes = ceil($remainingTime / 60);
        $error = "登入失敗次數過多，請等待 {$minutes} 分鐘後再試";
        AuditLogger::log('login_attempt_blocked', "Email: $email, Remaining lock time: {$remainingTime}s");
    } else {
        $user = $db->fetch('SELECT * FROM users WHERE email = ?', [$email]);
        
        if ($user && verifyPassword($password, $user['password'])) {
            LoginAttemptLimiter::clearAttempts($email);
            loginUser($user);
            AuditLogger::log('user_login', "Successful login for user: {$user['name']} ({$user['email']})", $user['id']);
            
            // 所有用戶預設進入前台工作頁面
            header('Location: dashboard.php');
            exit;
        } else {
            $attempts = LoginAttemptLimiter::recordAttempt($email);
            $error = 'Email 或密碼錯誤';
            AuditLogger::log('login_failed', "Failed login attempt for email: $email (attempt #$attempts)");
            
            if ($attempts >= 3) {
                $error .= "（剩餘嘗試次數：" . (5 - $attempts) . "）";
            }
        }
    }
}

// 處理註冊
if ($_SERVER['REQUEST_METHOD'] === 'POST' && getPostValue('action') === 'register') {
    $name = getPostValueSanitized('name');
    $email = getPostValueSanitized('email');
    $password = getPostValue('password');
    $confirmPassword = getPostValue('confirm_password');
    
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = '請填寫所有欄位';
    } elseif (!validateEmail($email)) {
        $error = 'Email 格式不正確';
    } elseif ($password !== $confirmPassword) {
        $error = '密碼確認不一致';
    } elseif (strlen($password) < 6) {
        $error = '密碼至少需要 6 個字元';
    } else {
        // 檢查 Email 是否已存在
        $existingUser = $db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
        
        if ($existingUser) {
            $error = '此 Email 已被註冊';
        } else {
            // 建立新使用者
            $hashedPassword = hashPassword($password);
            
            try {
                $db->execute('INSERT INTO users (name, email, password) VALUES (?, ?, ?)', 
                    [$name, $email, $hashedPassword]);
                
                $success = '註冊成功！請登入';
            } catch (Exception $e) {
                $error = '註冊失敗，請稍後再試';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkLog Manager - 工作進度回報管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        .nav-pills .nav-link {
            border-radius: 50px;
            margin: 0 5px;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 50px;
        }
        .form-control {
            border-radius: 50px;
            border: 2px solid #e3e6f0;
            padding: 12px 20px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-clipboard-list fa-3x text-primary mb-3"></i>
                        <h2 class="fw-bold">WorkLog Manager</h2>
                        <p class="text-muted">工作進度回報管理系統</p>
                    </div>

                    <!-- 導航標籤 -->
                    <ul class="nav nav-pills nav-justified mb-4" id="authTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="pill" data-bs-target="#login" type="button" role="tab">
                                <i class="fas fa-sign-in-alt me-2"></i>登入
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="pill" data-bs-target="#register" type="button" role="tab">
                                <i class="fas fa-user-plus me-2"></i>註冊
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="authTabContent">
                        <!-- 登入表單 -->
                        <div class="tab-pane fade show active" id="login" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="login">
                                
                                <div class="mb-3">
                                    <label for="loginEmail" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email
                                    </label>
                                    <input type="email" class="form-control" id="loginEmail" name="email" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="loginPassword" class="form-label">
                                        <i class="fas fa-lock me-2"></i>密碼
                                    </label>
                                    <input type="password" class="form-control" id="loginPassword" name="password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>登入
                                </button>
                            </form>
                        </div>

                        <!-- 註冊表單 -->
                        <div class="tab-pane fade" id="register" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="register">
                                
                                <div class="mb-3">
                                    <label for="registerName" class="form-label">
                                        <i class="fas fa-user me-2"></i>姓名
                                    </label>
                                    <input type="text" class="form-control" id="registerName" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="registerEmail" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email
                                    </label>
                                    <input type="email" class="form-control" id="registerEmail" name="email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="registerPassword" class="form-label">
                                        <i class="fas fa-lock me-2"></i>密碼
                                    </label>
                                    <input type="password" class="form-control" id="registerPassword" name="password" required minlength="6">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirmPassword" class="form-label">
                                        <i class="fas fa-lock me-2"></i>確認密碼
                                    </label>
                                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-user-plus me-2"></i>註冊
                                </button>
                            </form>
                        </div>
                    </div>


                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // 顯示錯誤訊息
        <?php if ($error): ?>
            Swal.fire({
                icon: 'error',
                title: '錯誤',
                text: '<?php echo $error; ?>',
                confirmButtonColor: '#667eea'
            });
        <?php endif; ?>

        // 顯示成功訊息
        <?php if ($success): ?>
            Swal.fire({
                icon: 'success',
                title: '成功',
                text: '<?php echo $success; ?>',
                confirmButtonColor: '#667eea'
            });
        <?php endif; ?>
    </script>
</body>
</html>
