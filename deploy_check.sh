#!/bin/bash
# WorkLog Manager 部署檢查腳本
# 用於檢查系統是否已準備就緒

echo "=== WorkLog Manager 部署檢查 ==="
echo "日期: $(date)"
echo

# 檢查PHP版本
echo "1. 檢查PHP版本..."
php_version=$(php -v | head -n1)
echo "   $php_version"

# 檢查必要的PHP擴展
echo
echo "2. 檢查PHP擴展..."
extensions=("pdo" "pdo_mysql" "session" "json" "mbstring")
for ext in "${extensions[@]}"; do
    if php -m | grep -q "^$ext$"; then
        echo "   ✓ $ext"
    else
        echo "   ✗ $ext (缺少)"
    fi
done

# 檢查檔案權限
echo
echo "3. 檢查檔案權限..."
if [ -d "logs" ]; then
    if [ -w "logs" ]; then
        echo "   ✓ logs/ 目錄可寫"
    else
        echo "   ✗ logs/ 目錄不可寫"
    fi
else
    echo "   ✗ logs/ 目錄不存在"
fi

if [ -f ".htaccess" ]; then
    echo "   ✓ .htaccess 檔案存在"
else
    echo "   ✗ .htaccess 檔案不存在"
fi

# 檢查配置檔案
echo
echo "4. 檢查配置檔案..."
config_files=("config/database.php" "config/functions.php" "config/security.php" "config/production.php")
for file in "${config_files[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✓ $file"
    else
        echo "   ✗ $file (缺少)"
    fi
done

# 檢查核心檔案
echo
echo "5. 檢查核心檔案..."
core_files=("index.php" "dashboard.php" "admin.php" "logout.php")
for file in "${core_files[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✓ $file"
    else
        echo "   ✗ $file (缺少)"
    fi
done

# 語法檢查
echo
echo "6. 進行語法檢查..."
for file in *.php config/*.php; do
    if [ -f "$file" ]; then
        result=$(php -l "$file" 2>&1)
        if [[ $result == *"No syntax errors"* ]]; then
            echo "   ✓ $file"
        else
            echo "   ✗ $file: $result"
        fi
    fi
done

# 檢查數據庫連接（需要數據庫資訊）
echo
echo "7. 數據庫表檢查提示..."
echo "   請手動執行以下命令檢查數據庫表："
echo "   mysql -u staf_db -p staf_db -e 'SHOW TABLES;'"

# 安全檢查提示
echo
echo "8. 安全檢查提示..."
echo "   - 確保已更改數據庫密碼"
echo "   - 確認 HTTPS 已啟用"
echo "   - 檢查防火牆設置"
echo "   - 定期更新系統"

echo
echo "=== 檢查完成 ==="
echo "如果所有項目都顯示 ✓，系統已準備就緒"
echo "如有 ✗ 項目，請先修復後再部署"
