<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>員工系統 - 登入</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div style="max-width: 400px; margin: 50px auto;">
            <div class="card">
                <div class="card-header text-center">
                    <h1 class="card-title">來網頁資訊有限公司<br> 員工管理系統</h1>
                </div>
                
                <?php
                session_start();
                if (isset($_SESSION['error_message'])) {
                    echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                    unset($_SESSION['error_message']);
                }
                if (isset($_SESSION['success_message'])) {
                    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                    unset($_SESSION['success_message']);
                }
                ?>
                
                <form method="POST" action="auth/login_process.php" id="login-form">
                    <div class="form-group">
                        <label class="form-label" for="staff_id">員工編號</label>
                        <input type="text" 
                               id="staff_id" 
                               name="staff_id" 
                               class="form-input" 
                               placeholder="請輸入員工編號" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">密碼</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="請輸入密碼" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            登入
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p style="color: #999; font-size: 0.9rem;">
                        編號格式：<br>
                        EMP00X
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        document.getElementById('login-form').addEventListener('submit', function(e) {
            if (!validateForm('login-form')) {
                e.preventDefault();
                showAlert('請填寫所有必填欄位', 'error');
            }
        });
    </script>
</body>
</html>
