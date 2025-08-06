// 每日工作報告相關功能
let reportFormVisible = false;

// 頁面載入完成後初始化
document.addEventListener('DOMContentLoaded', function() {
    console.log('報告功能腳本已載入');
    checkTodayReportStatus();
    setupFormListener();
});

// 檢查今日報告狀態
function checkTodayReportStatus() {
    try {
        const today = new Date().toISOString().split('T')[0];
        const staffId = document.body.getAttribute('data-staff-id');
        const statusKey = `daily_report_${today}_${staffId}`;
        const completed = localStorage.getItem(statusKey);
        
        const statusIndicator = document.getElementById('statusIndicator');
        const statusText = document.getElementById('statusText');
        const toggleBtn = document.getElementById('toggleBtn');
        
        if (!statusIndicator || !statusText || !toggleBtn) {
            console.log('報告狀態元素未找到，可能不在報告頁面');
            return;
        }
        
        if (completed === 'true') {
            statusIndicator.textContent = '✅';
            statusIndicator.className = 'status-indicator completed';
            statusText.textContent = '今日報告已完成';
            statusText.style.color = '#28a745';
            toggleBtn.textContent = '📋 查看今日報告';
        } else {
            statusIndicator.textContent = '⏳';
            statusIndicator.className = 'status-indicator pending';
            statusText.textContent = '今日報告待填寫';
            statusText.style.color = '#ffc107';
            toggleBtn.textContent = '📋 填寫今日報告';
            
            // 如果還沒填寫且已經下午，顯示提醒動畫
            const currentHour = new Date().getHours();
            if (currentHour >= 14) {
                const container = document.querySelector('.daily-report-container');
                if (container) {
                    container.classList.add('report-reminder');
                    setTimeout(() => {
                        container.classList.remove('report-reminder');
                    }, 500);
                }
            }
        }
    } catch (error) {
        console.error('檢查報告狀態時發生錯誤:', error);
    }
}

// 切換表單顯示
function toggleReportForm() {
    console.log('toggleReportForm 被調用');
    
    try {
        const wrapper = document.getElementById('reportFormWrapper');
        const toggleBtn = document.getElementById('toggleBtn');
        
        console.log('wrapper:', wrapper);
        console.log('toggleBtn:', toggleBtn);
        console.log('reportFormVisible:', reportFormVisible);
        
        if (!wrapper || !toggleBtn) {
            console.error('找不到必要的元素');
            alert('錯誤：找不到必要的頁面元素');
            return;
        }
        
        if (!reportFormVisible) {
            wrapper.style.display = 'block';
            wrapper.classList.add('show');
            toggleBtn.textContent = '🔼 收起表單';
            reportFormVisible = true;
            
            // 記錄表單開啟時間
            window.formOpenTime = new Date().getTime();
            
            // 更新iframe src以包含當前時間戳，確保表單是最新的
            const iframe = document.getElementById('dailyReportForm');
            if (iframe) {
                const baseUrl = iframe.getAttribute('src').split('&timestamp=')[0];
                iframe.src = baseUrl + '&timestamp=' + Date.now();
                console.log('已更新iframe URL');
            } else {
                console.error('找不到iframe元素');
            }
            
            showNotification('工作報告表單已展開', 'info');
        } else {
            wrapper.style.display = 'none';
            wrapper.classList.remove('show');
            toggleBtn.textContent = '📋 填寫今日報告';
            reportFormVisible = false;
            
            showNotification('工作報告表單已收起', 'info');
        }
    } catch (error) {
        console.error('切換表單時發生錯誤:', error);
        alert('操作失敗：' + error.message);
    }
}

// 開啟完整表單（新視窗）
function openFullForm() {
    console.log('openFullForm 被調用');
    
    try {
        const staffName = document.body.getAttribute('data-staff-name');
        const staffId = document.body.getAttribute('data-staff-id');
        const today = new Date().toISOString().split('T')[0];
        
        const formUrl = `https://docs.google.com/forms/d/e/1FAIpQLSeccnsf6UQuG31A6cxNpjI8ez5ATvVE7YxJ5-GREh8sSJg8Dg/viewform?usp=pp_url&entry.1234567890=${encodeURIComponent(staffName)}&entry.0987654321=${encodeURIComponent(staffId)}&entry.1111111111=${today}`;
        
        console.log('準備開啟URL:', formUrl);
        
        const newWindow = window.open(formUrl, '_blank', 'width=800,height=900,scrollbars=yes,resizable=yes');
        
        if (!newWindow) {
            alert('請允許彈出式視窗以開啟表單，或檢查您的瀏覽器設定');
        } else {
            showNotification('表單已在新視窗中開啟', 'success');
        }
    } catch (error) {
        console.error('開啟表單時發生錯誤:', error);
        alert('開啟表單時發生錯誤：' + error.message);
    }
}

// 標記報告為已完成
function markReportCompleted() {
    console.log('markReportCompleted 被調用');
    
    try {
        const today = new Date().toISOString().split('T')[0];
        const staffId = document.body.getAttribute('data-staff-id');
        const statusKey = `daily_report_${today}_${staffId}`;
        
        localStorage.setItem(statusKey, 'true');
        checkTodayReportStatus();
        
        showNotification('✅ 今日工作報告已完成！感謝您的配合。', 'success');
    } catch (error) {
        console.error('標記完成時發生錯誤:', error);
        alert('操作失敗：' + error.message);
    }
}

// 顯示通知函數
function showNotification(message, type = 'info') {
    try {
        // 創建通知元素
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            max-width: 300px;
            word-wrap: break-word;
            animation: slideInRight 0.3s ease;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // 3秒後自動移除
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    } catch (error) {
        console.error('顯示通知時發生錯誤:', error);
        // 備用提示
        alert(message);
    }
}

// 監聽iframe中的表單提交
function setupFormListener() {
    try {
        const iframe = document.getElementById('dailyReportForm');
        if (!iframe) {
            console.log('未找到iframe，跳過表單監聽設置');
            return;
        }
        
        // 設置定時器檢查表單狀態
        let formCheckInterval = setInterval(() => {
            try {
                // 如果iframe內容載入完成且使用者已填寫表單一段時間，提示標記為完成
                if (reportFormVisible) {
                    const currentTime = new Date().getTime();
                    const formOpenTime = window.formOpenTime || currentTime;
                    
                    if (currentTime - formOpenTime > 60000) { // 1分鐘後
                        clearInterval(formCheckInterval);
                        
                        if (confirm('您是否已完成今日工作報告的填寫？')) {
                            markReportCompleted();
                            toggleReportForm();
                        }
                    }
                }
            } catch (e) {
                // 跨域限制，無法直接檢測
                console.log('跨域限制，無法檢測表單狀態');
            }
        }, 30000); // 每30秒檢查一次
    } catch (error) {
        console.error('設置表單監聽時發生錯誤:', error);
    }
}

// 添加CSS動畫樣式
function addAnimationStyles() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .report-reminder {
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    `;
    document.head.appendChild(style);
}

// 立即添加樣式
addAnimationStyles();

console.log('報告功能腳本載入完成');
