<?php
// bottom_nav.php
// 获取当前文件名
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* 底部导航栏样式 */
    .bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: rgba(22, 33, 62, 0.95);
        backdrop-filter: blur(10px);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-around;
        padding: 10px 0;
        z-index: 1000;
        box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.5);
    }
    
    .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: #888;
        font-size: 12px;
        transition: all 0.3s ease;
        flex: 1;
        position: relative;
    }
    
    .nav-item.active {
        color: #4ecca3;
        transform: translateY(-2px);
    }
    
    .nav-badge {
        position: absolute;
        top: -5px;
        right: 25%;
        background: #ff6b6b;
        color: white;
        border-radius: 10px;
        padding: 2px 5px;
        font-size: 10px;
        font-weight: bold;
        min-width: 16px;
        text-align: center;
        display: none;
        border: 1px solid #1a1a2e;
        z-index: 2;
    }

    .nav-icon {
        font-size: 20px;
        margin-bottom: 4px;
    }
    
    /* 增加底部间距，防止内容被遮挡 */
    body {
        padding-bottom: 70px !important; 
    }
</style>

<div class="bottom-nav">
    <a href="dashboard.php" class="nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
        <div class="nav-icon">🏠</div>
        <span>大厅</span>
    </a>
    <a href="friends.php" class="nav-item <?php echo $current_page === 'friends.php' ? 'active' : ''; ?>">
        <div class="nav-icon">👥</div>
        <span class="nav-badge" id="navFriendBadge">0</span>
        <span>好友</span>
    </a>
    <a href="world_chat.php" class="nav-item <?php echo $current_page === 'world_chat.php' ? 'active' : ''; ?>">
        <div class="nav-icon">💬</div>
        <span class="nav-badge" id="navChatBadge">0</span>
        <span>世界聊天</span>
    </a>
    <a href="social.php" class="nav-item <?php echo $current_page === 'social.php' ? 'active' : ''; ?>">
        <div class="nav-icon">🧭</div>
        <span class="nav-badge" id="navSocialBadge">0</span>
        <span>社交</span>
    </a>
</div>

<script>
    // 全局导航栏红点逻辑
    document.addEventListener('DOMContentLoaded', function() {
        updateNavBadges();
        
        // 每30秒检查一次
        setInterval(updateNavBadges, 30000);
        
        // 页面可见时检查
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                updateNavBadges();
            }
        });
    });

    function updateNavBadges() {
        // 如果在社交页面，可能已经有更新逻辑，但这里做全局保障
        fetch('api/get_social_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const d = data.data;
                    
                    // 好友红点 (好友请求)
                    const friendBadge = document.getElementById('navFriendBadge');
                    if (friendBadge) {
                        if (d.request_count > 0) {
                            friendBadge.textContent = d.request_count > 99 ? '99+' : d.request_count;
                            friendBadge.style.display = 'block';
                        } else {
                            friendBadge.style.display = 'none';
                        }
                    }
                    
                    // 世界聊天红点
                    const chatBadge = document.getElementById('navChatBadge');
                    if (chatBadge) {
                        const lastReadId = parseInt(localStorage.getItem('last_world_chat_read_id') || 0);
                        if (d.latest_world_chat_id > lastReadId) {
                            // 不显示具体数字，只显示红点
                            chatBadge.textContent = '';
                            chatBadge.style.width = '10px';
                            chatBadge.style.height = '10px';
                            chatBadge.style.minWidth = 'unset';
                            chatBadge.style.padding = '0';
                            chatBadge.style.display = 'block';
                        } else {
                            chatBadge.style.display = 'none';
                        }
                    }
                    
                    // 社交红点 (未读私信)
                    const socialBadge = document.getElementById('navSocialBadge');
                    if (socialBadge) {
                        if (d.unread_count > 0) {
                            socialBadge.textContent = d.unread_count > 99 ? '99+' : d.unread_count;
                            socialBadge.style.display = 'block';
                        } else {
                            socialBadge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(e => console.error('Badge update failed', e));
    }
</script>
