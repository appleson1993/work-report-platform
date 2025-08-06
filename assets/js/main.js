// 全域JavaScript功能

// 即時時間顯示
function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    });
    
    const timeDisplay = document.getElementById('current-time');
    if (timeDisplay) {
        timeDisplay.textContent = timeString;
    }
}

// 每秒更新時間
if (document.getElementById('current-time')) {
    setInterval(updateCurrentTime, 1000);
    updateCurrentTime(); // 立即顯示
}

// 打卡功能
function clockIn() {
    const button = document.getElementById('clock-in-btn');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="loading"></span> 打卡中...';
    }
    
    fetch('../api/clock_in.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('上班打卡成功！', 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert(data.message || '打卡失敗，請重試', 'error');
            if (button) {
                button.disabled = false;
                button.innerHTML = '上班打卡';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('系統錯誤，請重試', 'error');
        if (button) {
            button.disabled = false;
            button.innerHTML = '上班打卡';
        }
    });
}

function clockOut() {
    const button = document.getElementById('clock-out-btn');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="loading"></span> 打卡中...';
    }
    
    fetch('../api/clock_out.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('下班打卡成功！', 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert(data.message || '打卡失敗，請重試', 'error');
            if (button) {
                button.disabled = false;
                button.innerHTML = '下班打卡';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('系統錯誤，請重試', 'error');
        if (button) {
            button.disabled = false;
            button.innerHTML = '下班打卡';
        }
    });
}

// 休息開始功能
function startBreak(breakType = 'other') {
    const button = document.getElementById('break-start-btn');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="loading"></span> 記錄中...';
    }
    
    fetch('../api/break_start.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ break_type: breakType })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`${data.break_type}開始記錄成功！`, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert(data.message || '休息記錄失敗，請重試', 'error');
            if (button) {
                button.disabled = false;
                button.innerHTML = '開始休息';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('系統錯誤，請重試', 'error');
        if (button) {
            button.disabled = false;
            button.innerHTML = '開始休息';
        }
    });
}

// 休息結束功能
function endBreak() {
    const button = document.getElementById('break-end-btn');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="loading"></span> 記錄中...';
    }
    
    fetch('../api/break_end.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`${data.break_type}結束！休息時長：${data.break_duration}`, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert(data.message || '休息結束記錄失敗，請重試', 'error');
            if (button) {
                button.disabled = false;
                button.innerHTML = '結束休息';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('系統錯誤，請重試', 'error');
        if (button) {
            button.disabled = false;
            button.innerHTML = '結束休息';
        }
    });
}

// 顯示休息類型選擇器
function showBreakTypeModal() {
    const modal = document.getElementById('break-type-modal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

// 隱藏休息類型選擇器
function hideBreakTypeModal() {
    const modal = document.getElementById('break-type-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// 顯示提示訊息
function showAlert(message, type = 'info') {
    // 移除現有的alert
    const existingAlert = document.querySelector('.alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    
    // 插入到container的最上方
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alert, container.firstChild);
    }
    
    // 3秒後自動消失
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 3000);
}

// 確認對話框
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// 表格排序功能
function sortTable(columnIndex, tableId = 'data-table') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    
    const isNumeric = !isNaN(parseFloat(rows[0].cells[columnIndex].textContent));
    
    rows.sort((a, b) => {
        const aVal = a.cells[columnIndex].textContent.trim();
        const bVal = b.cells[columnIndex].textContent.trim();
        
        if (isNumeric) {
            return parseFloat(aVal) - parseFloat(bVal);
        } else {
            return aVal.localeCompare(bVal, 'zh-TW');
        }
    });
    
    // 重新插入排序後的行
    rows.forEach(row => tbody.appendChild(row));
}

// 搜尋功能
function searchTable(searchTerm, tableId = 'data-table') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = tbody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let found = false;
        
        for (let j = 0; j < cells.length; j++) {
            const cellText = cells[j].textContent.toLowerCase();
            if (cellText.includes(searchTerm.toLowerCase())) {
                found = true;
                break;
            }
        }
        
        row.style.display = found ? '' : 'none';
    }
}

// 日期範圍篩選
function filterByDateRange(startDate, endDate, dateColumnIndex = 2, tableId = 'data-table') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = tbody.getElementsByTagName('tr');
    
    const start = startDate ? new Date(startDate) : null;
    const end = endDate ? new Date(endDate) : null;
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const dateCell = row.cells[dateColumnIndex];
        if (!dateCell) continue;
        
        const rowDate = new Date(dateCell.textContent.trim());
        let show = true;
        
        if (start && rowDate < start) show = false;
        if (end && rowDate > end) show = false;
        
        row.style.display = show ? '' : 'none';
    }
}

// 表單驗證
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#ef4444';
            isValid = false;
        } else {
            field.style.borderColor = '#555';
        }
    });
    
    return isValid;
}

// 頁面載入完成後執行
document.addEventListener('DOMContentLoaded', function() {
    // 為搜尋框添加事件監聽器
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            searchTable(this.value);
        });
    }
    
    // 為日期篩選器添加事件監聽器
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');
    
    if (startDateInput && endDateInput) {
        function handleDateFilter() {
            filterByDateRange(startDateInput.value, endDateInput.value);
        }
        
        startDateInput.addEventListener('change', handleDateFilter);
        endDateInput.addEventListener('change', handleDateFilter);
    }
    
    // 自動聚焦第一個輸入框
    const firstInput = document.querySelector('input[type="text"], input[type="password"]');
    if (firstInput) {
        firstInput.focus();
    }
});

// 公告功能
function markAnnouncementRead(announcementId) {
    fetch('../api/mark_announcement_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `announcement_id=${announcementId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 隱藏公告
            const announcementElement = document.querySelector(`[data-id="${announcementId}"]`);
            if (announcementElement) {
                announcementElement.style.animation = 'slideOutToTop 0.3s ease-in forwards';
                setTimeout(() => {
                    announcementElement.remove();
                    
                    // 如果沒有更多公告，隱藏整個公告區域
                    const announcementsSection = document.querySelector('.announcements-section');
                    if (announcementsSection && announcementsSection.children.length === 0) {
                        announcementsSection.remove();
                    }
                }, 300);
            }
        } else {
            showAlert(data.message || '標記已讀失敗', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('網路錯誤，請重試', 'error');
    });
}

// 新增 CSS 動畫
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutToTop {
        from {
            opacity: 1;
            transform: translateY(0);
            max-height: 200px;
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
            max-height: 0;
            margin-bottom: 0;
            padding-top: 0;
            padding-bottom: 0;
        }
    }
`;
document.head.appendChild(style);
