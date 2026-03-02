<?php
// chat.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$friend_id = isset($_GET['friend_id']) ? intval($_GET['friend_id']) : null;
$session_id = $_GET['session_id'] ?? 0;

if ($friend_id === null) {
    header('Location: messages.php');
    exit;
}

// 获取好友信息
if ($friend_id == 0) {
    $friend_info = [
        'id' => 0,
        'nickname' => '系统通知',
        'race' => 'system',
        'level' => 999
    ];
} elseif ($friend_id < 0) {
    // 管理员 (负ID)
    $admin_id = -$friend_id;
    $stmt = $pdo->prepare("SELECT id, username FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin_info) {
        $friend_info = [
            'id' => $friend_id,
            'nickname' => $admin_info['username'] . ' (管理员)',
            'race' => 'system',
            'level' => 999
        ];
    } else {
        header('Location: messages.php?message=管理员不存在');
        exit;
    }
} else {
    $stmt = $pdo->prepare("
        SELECT id, nickname, race, level 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$friend_id]);
    $friend_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$friend_info) {
        // 尝试从管理员表中查找 (兼容旧数据，但不推荐)
        $stmt = $pdo->prepare("SELECT id, username FROM admins WHERE id = ?");
        $stmt->execute([$friend_id]);
        $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin_info) {
            $friend_info = [
                'id' => $admin_info['id'], // 这里保持正ID，但可能会有冲突
                'nickname' => $admin_info['username'] . ' (管理员)',
                'race' => 'system',
                'level' => 999
            ];
        } else {
            header('Location: messages.php?message=好友不存在');
            exit;
        }
    }
}

// 获取或创建聊天会话
if (!$session_id) {
    $stmt = $pdo->prepare("
        SELECT id FROM chat_sessions 
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
    ");
    $stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        $session_id = $session['id'];
    } else {
        // 创建新的聊天会话
        $stmt = $pdo->prepare("INSERT INTO chat_sessions (user1_id, user2_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $friend_id]);
        $session_id = $pdo->lastInsertId();
    }
}

// 获取聊天消息
$stmt = $pdo->prepare("
    SELECT 
        cm.*,
        CASE 
            WHEN cm.from_user_id = 0 THEN '系统通知'
            ELSE COALESCE(u.nickname, a.username, '系统通知') 
        END as from_user_name,
        CASE 
            WHEN cm.from_user_id = 0 THEN 'system'
            ELSE COALESCE(u.race, 'system') 
        END as from_user_race
    FROM chat_messages cm
    LEFT JOIN users u ON cm.from_user_id = u.id
    LEFT JOIN admins a ON (a.id = cm.from_user_id OR a.id = -cm.from_user_id)
    WHERE cm.session_id = ?
    ORDER BY cm.created_at ASC
    LIMIT 100
");
$stmt->execute([$session_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 标记消息为已读
$stmt = $pdo->prepare("
    UPDATE chat_messages 
    SET is_read = 1 
    WHERE session_id = ? AND to_user_id = ? AND is_read = 0
");
$stmt->execute([$session_id, $user_id]);

// 更新会话最后活动时间
$stmt = $pdo->prepare("
    UPDATE chat_sessions 
    SET last_message_at = NOW() 
    WHERE id = ?
");
$stmt->execute([$session_id]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 私聊</title>
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
            height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
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
            margin-left: 5px;
        }
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .friend-info {
            text-align: center;
            flex: 1;
        }
        .friend-name {
            font-size: 16px;
            font-weight: bold;
            color: #4ecca3;
            
        }
        .friend-details {
            font-size: 12px;
            color: #ccc;
            margin-top: 5px;
        }
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
            overflow: hidden;
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
            max-width: 80%;
            padding: 12px;
            border-radius: 12px;
            position: relative;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message-item.own {
            align-self: flex-end;
            background: linear-gradient(135deg, #4ecca3, #3db393);
            color: #1a1a2e;
            border-bottom-right-radius: 4px;
        }
        .message-item.other {
            align-self: flex-start;
            background: rgba(255, 255, 255, 0.15);
            border-bottom-left-radius: 4px;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .message-sender {
            font-weight: bold;
        }
        .message-time {
            opacity: 0.7;
        }
        .message-content {
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .input-area {
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .input-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .message-input {
            flex: 1;
            padding: 12px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            resize: none;
            min-height: 50px;
            max-height: 100px;
        }
        .message-input:focus {
            outline: none;
            border-color: #4ecca3;
            box-shadow: 0 0 0 2px rgba(78, 204, 163, 0.3);
        }
        .send-btn {
            background: #4ecca3;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            color: #1a1a2e;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 60px;
            height: 50px;
        }
        .send-btn:hover {
            background: #3db393;
        }
        .send-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .race-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: bold;
            margin-left: 5px;
        }
        .race-bug { background: #8bc34a; color: #1a1a2e; }
        .race-spirit { background: #9c27b0; color: white; }
        .race-ghost { background: #607d8b; color: white; }
        .race-human { background: #ff9800; color: #1a1a2e; }
        .race-god { background: #ffeb3b; color: #1a1a2e; }
        .level-badge {
            background: rgba(26, 26, 46, 0.7);
            color: #fff;
            padding: 1px 4px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: bold;
            margin-left: 5px;
        }
        .message-item.own .level-badge {
            background: rgba(26, 26, 46, 0.3);
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #ccc;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .typing-indicator {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            align-self: flex-start;
            font-size: 12px;
            color: #ccc;
            font-style: italic;
        }
        
        /* 系统消息和管理员消息样式 */
        .message-item.system {
            align-self: center;
            background: rgba(78, 204, 163, 0.1);
            border: 1px solid rgba(78, 204, 163, 0.3);
            max-width: 90%;
            text-align: center;
            margin: 10px 0;
            border-radius: 8px;
        }
        .message-item.system .message-sender {
            color: #4ecca3;
            justify-content: center;
            display: flex;
        }
        .message-item.system .message-content {
            color: #e0e0e0;
            font-size: 13px;
        }
        
        .message-item.admin {
            align-self: center;
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            max-width: 90%;
            text-align: center;
            margin: 10px 0;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .message-item.admin .message-header {
            justify-content: center;
            width: 100%;
        }
        .message-item.admin .message-sender {
            color: #ffc107;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .message-item.admin .message-sender::after {
            content: "管理员";
            background: #ffc107;
            color: #000;
            font-size: 10px;
            padding: 1px 4px;
            border-radius: 4px;
            margin-left: 6px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="messages.php" class="back-btn">返回消息</a>
            <div class="friend-info">
                <div class="friend-name">
                    <?php echo htmlspecialchars($friend_info['nickname']); ?>
                    <span class="level-badge">Lv.<?php echo $friend_info['level']; ?></span>
                    <span class="race-badge race-<?php echo $friend_info['race']; ?>">
                        <?php 
                        $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
                        echo $race_names[$friend_info['race']] ?? '未知';
                        ?>
                    </span>
                </div>
                <div class="friend-details">私聊中...</div>
            </div>
            <div style="width: 80px;"></div> <!-- 占位保持对称 -->
        </div>

        <div class="chat-container">
            <div class="messages-area" id="messagesArea">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">💬</div>
                        <div>还没有聊天消息</div>
                        <div style="font-size: 12px; margin-top: 10px;">发送第一条消息开始聊天吧！</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): 
                        $msgClass = 'other';
                        if ($message['from_user_id'] == $user_id) {
                            $msgClass = 'own';
                        } elseif ($message['message_type'] == 'system') {
                            $msgClass = 'system';
                        } elseif ($message['message_type'] == 'admin') {
                            $msgClass = 'admin';
                        }
                    ?>
                    <div class="message-item <?php echo $msgClass; ?>">
                        <div class="message-header">
                            <div class="message-sender">
                                <?php echo htmlspecialchars($message['from_user_name']); ?>
                                <?php if ($message['from_user_id'] != $user_id): ?>
                                
                                <?php endif; ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('H:i', strtotime($message['created_at'])); ?>
                            </div>
                        </div>
                        <div class="message-content">
                            <?php echo htmlspecialchars($message['content']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="input-area">
                <form class="input-form" id="messageForm">
                    <input type="hidden" id="sessionId" value="<?php echo $session_id; ?>">
                    <input type="hidden" id="friendId" value="<?php echo $friend_id; ?>">
                    <textarea class="message-input" id="messageInput" 
                              placeholder="输入消息... (最多500字)" 
                              maxlength="500" required></textarea>
                    <button type="submit" class="send-btn" id="sendBtn">发送</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const messagesArea = document.getElementById('messagesArea');
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const sessionId = document.getElementById('sessionId').value;
        const friendId = document.getElementById('friendId').value;

        // 自动滚动到底部
        function scrollToBottom() {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }

        // 初始滚动到底部
        setTimeout(scrollToBottom, 100);

        // 发送消息
        messageForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            if (!message) return;

            // 禁用发送按钮
            sendBtn.disabled = true;
            sendBtn.textContent = '发送中...';

            try {
                const response = await fetch('api/send_private_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        to_user_id: friendId,
                        message: message
                    })
                });

                const result = await response.json();

                if (result.success) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    
                    // 添加新消息到聊天区域
                    addMessageToChat({
                        from_user_id: <?php echo $user_id; ?>,
                        from_user_name: '我',
                        content: message,
                        created_at: new Date().toLocaleTimeString('zh-CN', {hour: '2-digit', minute:'2-digit'})
                    }, true);
                    
                    scrollToBottom();
                } else {
                    alert('发送失败: ' + result.message);
                }
            } catch (error) {
                alert('网络错误，请重试');
                console.error('Error:', error);
            } finally {
                sendBtn.disabled = false;
                sendBtn.textContent = '发送';
            }
        });

        // 添加消息到聊天区域
        function addMessageToChat(message, isOwn = false) {
            // 移除空状态提示
            const emptyState = messagesArea.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }

            const messageElement = document.createElement('div');
            
            // Determine class based on message type
            let msgClass = 'other';
            if (isOwn) {
                msgClass = 'own';
            } else if (message.message_type === 'system') {
                msgClass = 'system';
            } else if (message.message_type === 'admin') {
                msgClass = 'admin';
            }
            
            messageElement.className = `message-item ${msgClass}`;
            
            const raceNames = {
                'bug': '虫族',
                'spirit': '灵族', 
                'ghost': '鬼族',
                'human': '人族',
                'god': '神族',
                'system': '系统'
            };
            
            messageElement.innerHTML = `
                <div class="message-header">
                    <div class="message-sender">
                        ${message.from_user_name}
                        ${!isOwn && msgClass != 'system' && msgClass != 'admin' ? `
                        
                        ` : ''}
                    </div>
                    <div class="message-time">${message.created_at}</div>
                </div>
                <div class="message-content">
                    ${message.message_type === 'admin' ? '【后台消息推送】' : ''}${escapeHtml(message.content)}
                </div>
            `;
            
            messagesArea.appendChild(messageElement);
        }

        // HTML转义函数
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 输入框高度自适应
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // 按Enter发送，Ctrl+Enter换行
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.ctrlKey && !e.shiftKey) {
                e.preventDefault();
                messageForm.dispatchEvent(new Event('submit'));
            }
        });

        // 简单轮询获取新消息（实际应用应该用WebSocket）
        let lastMessageId = <?php echo empty($messages) ? 0 : end($messages)['id']; ?>;
        
        async function checkNewMessages() {
            try {
                const response = await fetch(`api/get_new_messages.php?session_id=${sessionId}&last_id=${lastMessageId}`);
                const result = await response.json();
                
                if (result.success && result.messages.length > 0) {
                    result.messages.forEach(message => {
                        addMessageToChat(message, false);
                        lastMessageId = Math.max(lastMessageId, message.id);
                    });
                    scrollToBottom();
                }
            } catch (error) {
                console.error('获取新消息失败:', error);
            }
        }

        // 每3秒检查一次新消息
        setInterval(checkNewMessages, 3000);
    </script>
</body>
</html>

<?php
/*
<span class="level-badge">Lv.<?php echo $friend_info['level']; ?></span>
                                <span class="race-badge race-<?php echo $message['from_user_race']; ?>">
                                    <?php echo $race_names[$message['from_user_race']] ?? '未知'; ?>
                                </span>
                                */
                                
                                
                                
                                
                                
                                
                                /*
                                <span class="level-badge">Lv.?</span>
                        <span class="race-badge race-${message.from_user_race || 'human'}">
                            ${raceNames[message.from_user_race] || '未知'}
                        </span>
                                */
                                ?>