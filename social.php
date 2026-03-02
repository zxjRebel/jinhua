<?php
// social.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 获取用户信息
$stmt = $pdo->prepare("SELECT nickname, race FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// 初始化计数（前端将通过AJAX实时更新）
$friend_count = 0;
$request_count = 0;
$unread_count = 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 社交中心</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .header h1 {
            color: #4ecca3;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        .user-info {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        .info-item {
            text-align: center;
        }
        .info-label {
            font-size: 12px;
            color: #ccc;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 12px;
            font-weight: bold;
            color: #4ecca3;
        }
        
        /* 统计信息样式 */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.15);
        }
        .stat-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #ffc107;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 12px;
            color: #ccc;
        }
        .stat-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff6b6b;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* 社交网格样式 */
        .social-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        .social-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 2px solid transparent;
            position: relative;
        }
        .social-card:hover {
            transform: translateY(-5px);
            border-color: #4ecca3;
            box-shadow: 0 5px 15px rgba(78, 204, 163, 0.3);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .card-title {
            font-size: 18px;
            font-weight: bold;
            color: #4ecca3;
        }
        .card-icon {
            font-size: 24px;
        }
        .card-desc {
            font-size: 14px;
            color: #ccc;
            line-height: 1.4;
        }
        .card-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff6b6b;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }
        
        /* 隐藏更新状态区域 */
        .update-time {
            display: none; /* 完全隐藏更新时间 */
        }
        
        /* 隐藏刷新状态 */
        #refreshStatus {
            display: none;
        }
        
        .loading {
            opacity: 0.7;
            pointer-events: none;
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

        /* 模态框样式 */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.8); 
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s;
        }

        .modal-content {
            background: #16213e;
            margin: 15% auto; 
            padding: 0;
            border: 1px solid #4ecca3;
            width: 80%; 
            max-width: 400px;
            border-radius: 15px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.5);
            animation: slideDown 0.3s;
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #4ecca3;
            font-size: 18px;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover,
        .close:focus {
            color: #fff;
            text-decoration: none;
        }

        .modal-body {
            padding: 30px 20px;
            text-align: center;
        }

        .modal-body img {
            max-width: 100%;
            border-radius: 10px;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            margin-bottom: 15px;
        }
        
        .modal-body p {
            color: #ccc;
            font-size: 14px;
            margin-top: 10px;
            line-height: 1.5;
        }

        .modal-footer {
            padding: 15px;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #666;
            font-size: 12px;
        }

        @keyframes fadeIn {
            from {opacity: 0} 
            to {opacity: 1}
        }

        @keyframes slideDown {
            from {transform: translateY(-50px); opacity: 0;} 
            to {transform: translateY(0); opacity: 1;}
        }
        
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">返回大厅</a>
            <h2>社交中心</h2>
            
            <div class="user-info">
                <div class="info-item">
                    <div class="info-label">玩家</div>
                    <div class="info-value"><?php echo htmlspecialchars($user_info['nickname']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">种族</div>
                    <div class="info-value">
                        <?php 
                        $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
                        echo $race_names[$user_info['race']] ?? '未选择';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 实时统计信息 -->
        <div class="stats-container">
            <div class="stat-card" onclick="openFriends()">
                <div class="stat-icon"><i class="fas fa-user-friends" style="color: #4ecca3;"></i></div>
                <div class="stat-number" id="friendCount">0</div>
                <div class="stat-label">好友数量</div>
            </div>
            <div class="stat-card" onclick="openFriends()">
                <div class="stat-icon"><i class="fas fa-user-plus" style="color: #ff9800;"></i></div>
                <div class="stat-number" id="requestCount">0</div>
                <div class="stat-label">好友请求</div>
                <div class="stat-badge" id="requestBadge" style="display: none;">0</div>
            </div>
            <div class="stat-card" onclick="openMessages()">
                <div class="stat-icon"><i class="fas fa-comment-dots" style="color: #03a9f4;"></i></div>
                <div class="stat-number" id="unreadCount">0</div>
                <div class="stat-label">未读消息</div>
                <div class="stat-badge" id="unreadBadge" style="display: none;">0</div>
            </div>
        </div>

        <!-- 隐藏更新时间 -->
        <div class="update-time" style="display: none;">
            最后更新: <span id="lastUpdate">--:--:--</span>
            <span id="refreshStatus"></span>
        </div>

        <!-- 社交功能网格 -->
        <div class="social-grid">
            <!-- 世界聊天 -->
            <div class="social-card" onclick="openWorldChat()">
                <div class="card-header">
                    <div class="card-title">世界聊天</div>
                    <div class="card-icon"><i class="fas fa-globe-asia" style="color: #4ecca3;"></i></div>
                </div>
                <div class="card-desc">
                    与全服玩家实时交流，分享游戏心得
                </div>
            </div>

            <!-- 好友列表 -->
            <div class="social-card" onclick="openFriends()">
                <div class="card-header">
                    <div class="card-title">好友列表</div>
                    <div class="card-icon"><i class="fas fa-address-book" style="color: #ff9800;"></i></div>
                </div>
                <div class="card-desc">
                    管理您的好友，查看在线状态，开始私聊
                </div>
                <div class="card-badge" id="friendRequestBadge" style="display: none;"></div>
            </div>

            <!-- 添加好友 -->
            <div class="social-card" onclick="openAddFriend()">
                <div class="card-header">
                    <div class="card-title">添加好友</div>
                    <div class="card-icon"><i class="fas fa-user-plus" style="color: #03a9f4;"></i></div>
                </div>
                <div class="card-desc">
                    通过玩家ID或昵称搜索并添加好友
                </div>
            </div>

            <!-- 消息中心 -->
            <div class="social-card" onclick="openMessages()">
                <div class="card-header">
                    <div class="card-title">消息中心</div>
                    <div class="card-icon"><i class="fas fa-envelope" style="color: #ffc107;"></i></div>
                </div>
                <div class="card-desc">
                    查看所有聊天记录和未读消息
                </div>
                <div class="card-badge" id="messageBadge" style="display: none;"></div>
            </div>
            
            <!-- 个人信息 -->
            <div class="social-card" onclick="openProfile()">
                <div class="card-header">
                    <div class="card-title">个人信息</div>
                    <div class="card-icon"><i class="fas fa-id-card" style="color: #9c27b0;"></i></div>
                </div>
                <div class="card-desc">
                    查看和修改您的个人信息
                </div>
            </div>
            
            <!-- 封禁榜 -->
            <div class="social-card" onclick="openBanList()">
                <div class="card-header">
                    <div class="card-title">封禁榜</div>
                    <div class="card-icon"><i class="fas fa-ban" style="color: #f44336;"></i></div>
                </div>
                <div class="card-desc">
                    查看被封禁的用户列表和原因
                </div>
            </div>
            
            <!-- 联系我们 -->
            <div class="social-card" onclick="openContactUs()">
                <div class="card-header">
                    <div class="card-title">联系我们</div>
                    <div class="card-icon"><i class="fas fa-headset" style="color: #009688;"></i></div>
                </div>
                <div class="card-desc">
                    如有问题，请联系我们
                </div>
            </div>
        </div>
    </div>

    <script>
        // 页面导航函数
        function openWorldChat() {
            window.location.href = 'world_chat.php';
        }

        function openFriends() {
            window.location.href = 'friends.php';
        }

        function openAddFriend() {
            window.location.href = 'add_friend.php';
        }

        function openMessages() {
            window.location.href = 'messages.php';
        }

        function openProfile() {
            window.location.href = 'profile.php';
        }
        
        function openBanList() {
            window.location.href = 'ban_list.php';
        }
        
        function openContactUs() {
            window.location.href = 'contact_us.php';
        }

        // 实时数据更新
        class SocialDataUpdater {
            constructor() {
                this.isUpdating = false;
                this.updateInterval = 5000; // 5秒更新一次
                this.lastUpdateTime = null;
                this.init();
            }

            init() {
                // 立即加载一次数据
                this.updateSocialData();
                
                // 设置定时更新
                setInterval(() => {
                    this.updateSocialData();
                }, this.updateInterval);
            }

            async updateSocialData() {
                if (this.isUpdating) return;
                
                this.isUpdating = true;

                try {
                    const response = await fetch('api/get_social_data.php');
                    const result = await response.json();

                    if (result.success) {
                        this.updateUI(result.data);
                        this.lastUpdateTime = new Date();
                    }
                } catch (error) {
                    console.error('更新社交数据失败:', error);
                    // 静默失败，不显示错误信息
                } finally {
                    this.isUpdating = false;
                }
            }

            updateUI(data) {
                // 更新统计数字
                document.getElementById('friendCount').textContent = data.friend_count;
                document.getElementById('requestCount').textContent = data.request_count;
                document.getElementById('unreadCount').textContent = data.unread_count;

                // 更新徽章
                this.updateBadge('requestBadge', data.request_count);
                this.updateBadge('unreadBadge', data.unread_count);
                this.updateBadge('friendRequestBadge', data.request_count);
                this.updateBadge('messageBadge', data.unread_count);
            }

            updateBadge(badgeId, count) {
                const badge = document.getElementById(badgeId);
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = 'block';
                    
                    // 如果有新数据，添加动画效果
                    if (!badge.classList.contains('pulse')) {
                        badge.classList.add('pulse');
                        setTimeout(() => {
                            badge.classList.remove('pulse');
                        }, 2000);
                    }
                } else {
                    badge.style.display = 'none';
                }
            }

            // 手动刷新数据（静默刷新）
            manualRefresh() {
                this.updateSocialData();
            }
        }

        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            window.socialUpdater = new SocialDataUpdater();
            
            // 添加手动刷新功能（下拉刷新）- 静默刷新
            let touchStartY = 0;
            document.addEventListener('touchstart', function(e) {
                touchStartY = e.touches[0].clientY;
            });

            document.addEventListener('touchend', function(e) {
                const touchEndY = e.changedTouches[0].clientY;
                const diff = touchStartY - touchEndY;
                
                // 下拉刷新（向下滑动超过50px）
                if (diff > 50 && window.pageYOffset === 0) {
                    window.socialUpdater.manualRefresh();
                }
            });

            // 键盘快捷键刷新 (F5 或 Ctrl+R) - 静默刷新
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                    e.preventDefault();
                    window.socialUpdater.manualRefresh();
                }
            });
        });

        // 页面可见性改变时静默刷新数据
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && window.socialUpdater) {
                // 页面从隐藏变为可见时，静默刷新数据
                setTimeout(() => {
                    window.socialUpdater.manualRefresh();
                }, 500);
            }
        });
    </script>
</body>
</html>