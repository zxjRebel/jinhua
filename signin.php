<?php
// signin.php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的进化之路 - 每日签到</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        .back-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .signin-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            text-align: center;
        }
        .signin-title {
            font-size: 24px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 15px;
        }
        .streak-info {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .streak-count {
            font-size: 48px;
            font-weight: bold;
            color: #ffc107;
            margin: 10px 0;
        }
        .signin-btn {
            background: linear-gradient(135deg, #4ecca3, #3db393);
            border: none;
            border-radius: 12px;
            padding: 20px;
            color: #1a1a2e;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin: 20px 0;
        }
        .signin-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(78, 204, 163, 0.4);
        }
        .signin-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-top: 20px;
        }
        .calendar-day {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 12px 5px;
            text-align: center;
            font-size: 12px;
        }
        .calendar-day.signed {
            background: rgba(78, 204, 163, 0.3);
            border: 1px solid #4ecca3;
        }
        .calendar-day.today {
            border: 2px solid #ffc107;
        }
        .reward-preview {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        .reward-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .reward-item:last-child {
            border-bottom: none;
        }
        .message {
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px;
            font-size: 14px;
        }
        .message.success {
            background: rgba(78, 204, 163, 0.2);
            color: #4ecca3;
            border: 1px solid rgba(78, 204, 163, 0.3);
        }
        .message.error {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">返回大厅</a>
            <h2>每日签到</h2>
            <div>连续签到有惊喜</div>
        </div>

        <div id="message" class="message"></div>

        <div class="signin-card">
            <div class="signin-title">每日签到</div>
            
            <div class="streak-info">
                <div>连续签到</div>
                <div class="streak-count" id="currentStreak">0</div>
                <div>最长记录: <span id="longestStreak">0</span> 天</div>
            </div>

            <button class="signin-btn" id="signinBtn" onclick="signin()">
                立即签到
            </button>

            <div>今日奖励: <span id="todayReward">加载中...</span></div>
        </div>

        <div class="reward-preview">
            <h3 style="margin-bottom: 15px; color: #4ecca3;">本周奖励预览</h3>
            <div id="rewardsList"></div>
        </div>

        <div class="reward-preview">
            <h3 style="margin-bottom: 15px; color: #4ecca3;">本月签到日历</h3>
            <div class="calendar" id="calendar"></div>
        </div>
    </div>

    <script>
        let signinInfo = {};

        // 页面加载时获取签到信息
        async function loadSigninInfo() {
            try {
                const response = await fetch('api/get_signin_info.php');
                const result = await response.json();

                if (result.success) {
                    signinInfo = result;
                    updateUI();
                }
            } catch (error) {
                console.error('加载签到信息失败:', error);
            }
        }

        function updateUI() {
            // 更新连续签到信息
            document.getElementById('currentStreak').textContent = signinInfo.streak_info.current_streak;
            document.getElementById('longestStreak').textContent = signinInfo.streak_info.longest_streak;

            // 更新签到按钮状态
            const signinBtn = document.getElementById('signinBtn');
            if (signinInfo.signed_today) {
                signinBtn.disabled = true;
                signinBtn.textContent = '今日已签到';
            }

            // 更新今日奖励
            const dayOfWeek = new Date().getDay() || 7; // 1-7
            const todayReward = signinInfo.rewards_preview[dayOfWeek - 1];
            document.getElementById('todayReward').textContent = 
                `${todayReward.coins}金币 + ${todayReward.exp}经验`;

            // 更新奖励预览
            updateRewardsPreview();

            // 更新日历
            updateCalendar();
        }

        function updateRewardsPreview() {
            const rewardsList = document.getElementById('rewardsList');
            let html = '';

            signinInfo.rewards_preview.forEach((reward, index) => {
                const dayNames = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
                html += `
                    <div class="reward-item">
                        <span>${dayNames[index]}</span>
                        <span>${reward.coins}金币 + ${reward.exp}经验</span>
                    </div>
                `;
            });

            rewardsList.innerHTML = html;
        }

        function updateCalendar() {
            const calendar = document.getElementById('calendar');
            const today = new Date().toISOString().split('T')[0];
            const year = new Date().getFullYear();
            const month = new Date().getMonth() + 1;
            
            // 简单日历实现 - 显示当月所有日期
            const daysInMonth = new Date(year, month, 0).getDate();
            let html = '';

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                const isSigned = signinInfo.month_records.includes(dateStr);
                const isToday = dateStr === today;
                
                let className = 'calendar-day';
                if (isSigned) className += ' signed';
                if (isToday) className += ' today';
                
                html += `<div class="${className}">${day}</div>`;
            }

            calendar.innerHTML = html;
        }

        // 签到功能
        async function signin() {
            const signinBtn = document.getElementById('signinBtn');
            const messageEl = document.getElementById('message');

            signinBtn.disabled = true;
            signinBtn.textContent = '签到中...';

            try {
                const response = await fetch('api/signin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });

                const result = await response.json();

                if (result.success) {
                    messageEl.textContent = `签到成功！获得 ${result.rewards.coins}金币 + ${result.rewards.exp}经验`;
                    messageEl.className = 'message success';
                    
                    // 重新加载签到信息
                    await loadSigninInfo();
                    
                    // 显示连续签到奖励
                    if (result.rewards.streak_bonus) {
                        setTimeout(() => {
                            messageEl.textContent += ` | ${result.rewards.streak_bonus}`;
                        }, 1000);
                    }
                } else {
                    messageEl.textContent = result.message;
                    messageEl.className = 'message error';
                    signinBtn.disabled = false;
                    signinBtn.textContent = '立即签到';
                }
            } catch (error) {
                messageEl.textContent = '网络错误，请重试';
                messageEl.className = 'message error';
                signinBtn.disabled = false;
                signinBtn.textContent = '立即签到';
                console.error('签到失败:', error);
            }
        }

        // 页面加载
        document.addEventListener('DOMContentLoaded', loadSigninInfo);
    </script>
</body>
</html>