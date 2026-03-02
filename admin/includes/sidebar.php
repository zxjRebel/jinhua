<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* 侧边栏样式 */
    .sidebar {
        width: 250px;
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        flex-shrink: 0;
    }
    
    .sidebar h1 {
        font-size: 24px;
        margin-bottom: 30px;
        text-align: center;
        color: white;
    }
    
    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .nav-menu li {
        margin-bottom: 10px;
    }
    
    .nav-menu a {
        display: block;
        padding: 12px 15px;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        transition: background-color 0.3s ease;
        font-size: 16px;
    }
    
    .nav-menu a:hover,
    .nav-menu a.active {
        background-color: rgba(255, 255, 255, 0.2);
    }
</style>
<aside class="sidebar">
    <h1>管理后台</h1>
    <ul class="nav-menu">
        <li><a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">仪表盘</a></li>
        <li><a href="users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">用户管理</a></li>
        <li><a href="characters.php" class="<?php echo $current_page == 'characters.php' ? 'active' : ''; ?>">角色管理</a></li>
        <li><a href="announcements.php" class="<?php echo $current_page == 'announcements.php' ? 'active' : ''; ?>">公告管理</a></li>
        <li><a href="push_messages.php" class="<?php echo $current_page == 'push_messages.php' ? 'active' : ''; ?>">消息推送</a></li>
        <li><a href="race_audit.php" class="<?php echo $current_page == 'race_audit.php' ? 'active' : ''; ?>">种族审核</a></li>
        <li><a href="world_chat.php" class="<?php echo $current_page == 'world_chat.php' ? 'active' : ''; ?>">世界聊天</a></li>
        <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">系统设置</a></li>
        <li><a href="contact_us.php" class="<?php echo $current_page == 'contact_us.php' ? 'active' : ''; ?>">联系我们</a></li>
        <li><a href="signin_settings.php" class="<?php echo $current_page == 'signin_settings.php' ? 'active' : ''; ?>">签到配置</a></li>
        <li><a href="rank_settings.php" class="<?php echo $current_page == 'rank_settings.php' ? 'active' : ''; ?>">段位配置</a></li>
        <li><a href="profile.php" class="<?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">管理员资料</a></li>
        <li><a href="logout.php">退出登录</a></li>
    </ul>
</aside>

<!-- 系统版权保护脚本 -->
<script>
(function() {
    // Base64 Encoded Warning Message
    var _key_str = "5q2k5ri45oiP54mI5pys55Sx6JGr6Iqm5L6g5b2p6Iej57K+5b+D5LqM5byA5bm25Yi25L2c77yM5YmN5ZCO56uv5LqM5byA5Yqf6IO95a6M576O77yB5aaC5p6c5L2g5piv6Iqx6ZKx5Lmw5p2l55qE77yB5oGt5Zac5L2g5LiK5b2T5LqG77yB";
    
    function _decode_msg(str) {
        return decodeURIComponent(atob(str).split('').map(function(c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
    }

    document.addEventListener('DOMContentLoaded', function() {
        try {
            var msg = _decode_msg(_key_str);
            var banner = document.createElement('div');
            // 高级样式，防止被轻易覆盖
            banner.style.cssText = 'background:linear-gradient(90deg, #ff4d4f, #cf1322);color:#fff;text-align:center;padding:12px;font-weight:bold;font-size:15px;box-shadow:0 4px 12px rgba(0,0,0,0.15);position:relative;z-index:9999;font-family:"Microsoft YaHei", sans-serif;letter-spacing:1px;text-shadow: 1px 1px 2px rgba(0,0,0,0.2);';
            banner.innerHTML = '<i style="margin-right:8px">⚠️</i>' + msg;
            
            var mainContent = document.querySelector('.main-content');
            if (mainContent) {
                mainContent.insertBefore(banner, mainContent.firstChild);
            } else {
                var container = document.querySelector('.container');
                if (container) {
                     container.insertBefore(banner, container.firstChild);
                } else {
                     document.body.insertBefore(banner, document.body.firstChild);
                }
            }
        } catch(e) {
            console.error('System integrity check failed');
        }
    });
})();
</script>
