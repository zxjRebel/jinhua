<?php
// world_chat.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT nickname, race, level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 获取最近的世界聊天消息（最多50条）
// 初始加载由JS完成，这里只渲染页面框架
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 世界聊天</title>
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
            padding: 10px; /* 减少内边距以适应移动端 */
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            height: calc(100vh - 100px); /* 调整高度计算 */
            display: flex;
            flex-direction: column;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px; /* 减少底部间距 */
            padding: 10px 15px; /* 减少垂直内边距 */
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            flex-shrink: 0; /* 防止头部被压缩 */
        }
        .header h2 {
            font-size: 1.2em; /* 稍微减小标题字体 */
            margin: 0;
        }
        .back-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            padding: 6px 12px; /* 减小按钮内边距 */
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9em; /* 稍微减小字体 */
        }
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
            overflow: hidden;
            position: relative;
        }
        .messages-area {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message-item {
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
            color: #aaa;
        }
        .message-user {
            font-weight: bold;
            color: #4ecca3;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .server-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            margin-right: 5px;
            background: #6c757d;
            color: #fff;
        }
        
        /* 本服消息样式 */
        .message-item.local-server .server-badge {
            background: #4ecca3; /* 绿色背景 */
            color: #1a1a2e;
        }
        
        .message-item.local-server .nickname {
            color: #4ecca3; /* 昵称也变绿 */
        }
        
        .message-item.local-server .message-content {
            /* border-left: 2px solid #4ecca3;  Removed per user request */
            /* padding-left: 8px; */
            background: rgba(78, 204, 163, 0.05); /* 轻微背景色 */
        }

        .level-badge {
            background: #e67e22;
            color: #fff;
            padding: 1px 4px;
            border-radius: 4px;
            font-size: 10px;
        }
        .race-badge {
            padding: 1px 4px;
            border-radius: 4px;
            font-size: 10px;
        }
        .race-bug { background: #8bc34a; color: #1a1a2e; }
        .race-spirit { background: #9c27b0; color: white; }
        .race-ghost { background: #607d8b; color: white; }
        .race-human { background: #ff9800; color: #1a1a2e; }
        .race-god { background: #ffeb3b; color: #1a1a2e; }
        
        .message-content {
            word-break: break-all;
            line-height: 1.5;
        }
        .input-area {
            padding: 15px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .input-form {
            display: flex;
            gap: 10px;
        }
        .message-input {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            resize: none;
            height: 42px;
            max-height: 100px;
            font-family: inherit;
        }
        .message-input:focus {
            outline: none;
            border-color: #4ecca3;
            background: rgba(255, 255, 255, 0.15);
        }
        .send-btn {
            padding: 0 20px;
            border-radius: 8px;
            border: none;
            background: #4ecca3;
            color: #1a1a2e;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .send-btn:hover {
            background: #45b691;
        }
        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #ccc;
            opacity: 0.7;
        }
        .empty-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .refresh-btn {
            background: transparent;
            border: none;
            color: #4ecca3;
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
            transition: transform 0.3s ease;
        }
        .refresh-btn:hover {
            transform: rotate(180deg);
        }
        .user-menu-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }
        .user-menu-content {
            background: #16213e;
            border: 1px solid #4ecca3;
            border-radius: 10px;
            width: 80%;
            max-width: 300px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 0 20px rgba(78, 204, 163, 0.2);
            animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .user-menu-title {
            font-size: 18px;
            color: #4ecca3;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .user-menu-subtitle {
            font-size: 12px;
            color: #888;
            margin-bottom: 20px;
        }
        .user-menu-btn {
            display: block;
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .user-menu-btn:hover {
            background: rgba(78, 204, 163, 0.1);
            border-color: #4ecca3;
        }
        .user-menu-btn.close-btn {
            border-color: #ff6b6b;
            color: #ff6b6b;
            margin-bottom: 0;
        }
        .user-menu-btn.close-btn:hover {
            background: rgba(255, 107, 107, 0.1);
        }
        .clickable-name {
            cursor: pointer;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.3);
            transition: all 0.2s;
        }
        .clickable-name:hover {
            color: #4ecca3;
            border-bottom-color: #4ecca3;
        }
        .new-msg-badge {
            position: absolute;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(78, 204, 163, 0.9);
            color: #1a1a2e;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            display: none;
            animation: bounce 1s infinite;
            z-index: 100;
        }
        @keyframes bounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-5px); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="social.php" class="back-btn">返回社交</a>
            <h2>世界聊天<?php echo isset($chat_config['server_name']) ? '<span style="font-size: 0.8em; margin-left: 5px; color: #4ecca3;">(我在' . htmlspecialchars($chat_config['server_name']) . ')</span>' : ''; ?></h2>
            <button id="refreshBtn" class="refresh-btn" title="刷新">↻</button>
        </div>

        <div class="chat-container">
            <div class="messages-area" id="messagesArea">
                <div class="empty-state">
                    <div class="empty-icon">💬</div>
                    <div>正在加载聊天记录...</div>
                </div>
            </div>
            
            <!-- 新消息提示 -->
            <div id="newMsgBadge" class="new-msg-badge" onclick="scrollToBottom()">
                ↓ 有新消息
            </div>

            <div class="input-area">
                <form class="input-form" id="messageForm">
                    <textarea class="message-input" id="messageInput" 
                              placeholder="输入消息... (最多200字)" 
                              maxlength="200" required></textarea>
                    <button type="submit" class="send-btn" id="sendBtn">发送</button>
                </form>
            </div>
        </div>
    </div>

    <!-- 用户操作菜单 -->
    <div id="userMenuModal" class="user-menu-modal" onclick="closeUserMenu(event)">
        <div class="user-menu-content" onclick="event.stopPropagation()">
            <div id="menuUserTitle" class="user-menu-title">玩家名称</div>
            <div id="menuUserServer" class="user-menu-subtitle">所属服务器</div>
            
            <button class="user-menu-btn" onclick="actionAddFriend()">➕ 添加好友</button>
            <button class="user-menu-btn" onclick="actionPrivateChat()">💬 发送私信</button>
            <button class="user-menu-btn close-btn" onclick="closeUserMenu()">关闭</button>
        </div>
    </div>
    
    <?php include 'bottom_nav.php'; ?>

    <script>
        const localServerName = '<?php echo $chat_config['server_name'] ?? ""; ?>';
        const currentUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>;
        
        // 用户菜单相关变量
        let selectedUser = { id: 0, name: '', server: '' };
        const userMenuModal = document.getElementById('userMenuModal');
        const menuUserTitle = document.getElementById('menuUserTitle');
        const menuUserServer = document.getElementById('menuUserServer');
        
        function showUserMenu(id, name, server) {
            // 不允许操作自己
            if (id == currentUserId) return;
            
            selectedUser = { id, name, server };
            menuUserTitle.textContent = name;
            menuUserServer.textContent = server ? server : '未知服务器';
            
            const isLocal = !server || server === localServerName;
            const addFriendBtn = document.querySelector('button[onclick="actionAddFriend()"]');
            const privateChatBtn = document.querySelector('button[onclick="actionPrivateChat()"]');
            
            // 重置点击事件（因为之前可能被修改过）
            addFriendBtn.onclick = actionAddFriend;
            
            // 检查ID有效性
            // 注意：如果id是 undefined 字符串或者 0，都视为无效
            if (!id || id == 0 || id === 'undefined') {
                 if (isLocal) {
                     // 如果是本服玩家但ID获取失败，允许通过昵称搜索
                     addFriendBtn.disabled = false;
                     addFriendBtn.style.opacity = 1;
                     addFriendBtn.textContent = "🔍 搜索添加";
                     addFriendBtn.onclick = function() {
                         window.location.href = `add_friend.php?q=${encodeURIComponent(name)}`;
                     };
                     
                     privateChatBtn.disabled = true;
                     privateChatBtn.style.opacity = 0.5;
                     privateChatBtn.textContent = "需要先添加";
                 } else {
                     addFriendBtn.disabled = true;
                     addFriendBtn.style.opacity = 0.5;
                     addFriendBtn.textContent = "⚠️ 无效用户";
                     privateChatBtn.disabled = true;
                     privateChatBtn.style.opacity = 0.5;
                 }
            } else if (isLocal) {
                addFriendBtn.disabled = false;
                addFriendBtn.style.opacity = 1;
                addFriendBtn.textContent = "➕ 添加好友";
                
                privateChatBtn.disabled = false;
                privateChatBtn.style.opacity = 1;
                privateChatBtn.textContent = "💬 发送私信";
            } else {
                addFriendBtn.disabled = true;
                addFriendBtn.style.opacity = 0.5;
                addFriendBtn.textContent = "🚫 跨服无法添加";
                
                privateChatBtn.disabled = true;
                privateChatBtn.style.opacity = 0.5;
                privateChatBtn.textContent = "💬 发送私信";
            }
            
            userMenuModal.style.display = 'flex';
        }
        
        function closeUserMenu(e) {
            if (e) e.preventDefault();
            userMenuModal.style.display = 'none';
        }
        
        function actionAddFriend() {
            if (!selectedUser.id) return;
            
            // 简单防抖
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '请求中...';
            btn.disabled = true;
            
            // 发送好友请求
            fetch('api/friend_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send&target_id=${selectedUser.id}`
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message || (data.success ? '请求已发送' : '操作失败'));
                closeUserMenu();
            })
            .catch(err => {
                alert('网络错误');
                console.error(err);
            })
            .finally(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }
        
        /**
         * 处理私信按钮点击
         * 跳转到私聊页面
         */
        function actionPrivateChat() {
            if (!selectedUser.id) return;
            window.location.href = `chat.php?friend_id=${selectedUser.id}&friend_name=${encodeURIComponent(selectedUser.name)}`;
            closeUserMenu();
        }

        const messagesArea = document.getElementById('messagesArea');
        
        // 监听点击用户名
        messagesArea.addEventListener('click', function(e) {
            if (e.target.classList.contains('clickable-name')) {
                const uid = e.target.getAttribute('data-uid');
                const name = e.target.getAttribute('data-name');
                const server = e.target.getAttribute('data-server');
                showUserMenu(uid, name, server);
            }
        });

        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const refreshBtn = document.getElementById('refreshBtn');
        const newMsgBadge = document.getElementById('newMsgBadge');
        
        let isSending = false;
        let isAutoScrolling = true;
        let lastMessageId = 0;

        // 手动刷新
        refreshBtn.addEventListener('click', () => {
            fetchMessages();
            refreshBtn.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                refreshBtn.style.transform = 'rotate(0deg)';
            }, 300);
        });

        // 监听滚动
        messagesArea.addEventListener('scroll', () => {
            const threshold = 50;
            const position = messagesArea.scrollTop + messagesArea.offsetHeight;
            const height = messagesArea.scrollHeight;
            
            if ((height - position) <= threshold) {
                isAutoScrolling = true;
                newMsgBadge.style.display = 'none';
            } else {
                isAutoScrolling = false;
            }
        });

        function scrollToBottom() {
            messagesArea.scrollTop = messagesArea.scrollHeight;
            isAutoScrolling = true;
            newMsgBadge.style.display = 'none';
        }

        function renderMessage(msg) {
            if (document.querySelector(`.message-item[data-id="${msg.id}"]`)) {
                return;
            }

            const div = document.createElement('div');
            // Check if local server
            const isLocal = localServerName && msg.server_name === localServerName;
            div.className = `message-item ${isLocal ? 'local-server' : ''}`;
            
            div.setAttribute('data-id', msg.id);
            div.innerHTML = `
                <div class="message-header">
                    <div class="message-user">
                        ${msg.server_name ? `<span class="server-badge">${escapeHtml(msg.server_name)}</span>` : ''}
                        <span class="clickable-name" data-uid="${msg.user_id}" data-name="${escapeHtml(msg.nickname)}" data-server="${escapeHtml(msg.server_name || '')}">${escapeHtml(msg.nickname)}</span>
                        <span class="level-badge">Lv.${msg.level}</span>
                        <span class="race-badge race-${msg.race}">
                            ${msg.race_name}
                        </span>
                    </div>
                    <div class="message-time">
                        ${msg.time_str}
                    </div>
                </div>
                <div class="message-content">
                    ${escapeHtml(msg.message)}
                </div>
            `;
            messagesArea.appendChild(div);
            
            if (parseInt(msg.id) > lastMessageId) {
                lastMessageId = parseInt(msg.id);
            }
            
            if (isAutoScrolling) {
                scrollToBottom();
            } else {
                newMsgBadge.style.display = 'block';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function fetchMessages() {
            try {
                const response = await fetch(`api/get_world_messages.php?last_id=${lastMessageId}&_t=${new Date().getTime()}`);
                const result = await response.json();

                if (result.success) {
                    const emptyState = messagesArea.querySelector('.empty-state');
                    
                    if (result.messages.length > 0) {
                        if (emptyState) {
                            emptyState.remove();
                        }

                        result.messages.forEach(msg => {
                            renderMessage(msg);
                        });
                        
                        localStorage.setItem('last_world_chat_id', lastMessageId);
                        // 同时更新已读ID，用于社交中心红点消除
                        localStorage.setItem('last_world_chat_read_id', lastMessageId);
                    } else {
                        // 如果没有消息且当前显示的是"正在加载"，则更新为"暂无消息"
                        if (emptyState && emptyState.textContent.includes('正在加载')) {
                            emptyState.innerHTML = `
                                <div class="empty-icon">💬</div>
                                <div>暂无聊天记录</div>
                                <div style="font-size: 12px; margin-top: 10px;">发送第一条消息开始聊天吧！</div>
                            `;
                        }
                    }
                }
            } catch (error) {
                console.error('获取消息失败:', error);
                // 如果出错且还在加载中，显示错误
                const emptyState = messagesArea.querySelector('.empty-state');
                if (emptyState && emptyState.textContent.includes('正在加载')) {
                     emptyState.innerHTML = `
                        <div class="empty-icon">⚠️</div>
                        <div>加载失败</div>
                        <div style="font-size: 12px; margin-top: 10px;">请检查网络连接</div>
                    `;
                }
            }
        }

        // 首次加载
        fetchMessages();

        // 启动轮询
        setInterval(fetchMessages, 2000);

        messageForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            if (!message || isSending) return;

            isSending = true;
            sendBtn.disabled = true;
            sendBtn.textContent = '发送中...';

            try {
                const response = await fetch('api/send_world_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: message })
                });

                const result = await response.json();

                if (result.success) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    isAutoScrolling = true;
                    await fetchMessages();
                } else {
                    alert('发送失败: ' + result.message);
                }
            } catch (error) {
                alert('网络错误，请重试');
            } finally {
                isSending = false;
                sendBtn.disabled = false;
                sendBtn.textContent = '发送';
            }
        });

        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });
    </script>
    <?php include 'bottom_nav.php'; ?>
</body>
</html>