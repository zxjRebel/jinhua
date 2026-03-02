<?php
// messages.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 获取所有聊天会话
$stmt = $pdo->prepare("
    SELECT 
        cs.id as session_id,
        CASE 
            WHEN cs.user1_id = ? THEN cs.user2_id
            ELSE cs.user1_id
        END as friend_id,
        COALESCE(u.nickname, a.username, '系统通知') as nickname,
        COALESCE(u.race, 'system') as race,
        COALESCE(u.level, 999) as level,
        cs.last_message_at,
        (SELECT COUNT(*) FROM chat_messages cm WHERE cm.session_id = cs.id AND cm.to_user_id = ? AND cm.is_read = 0) as unread_count,
        (SELECT content FROM chat_messages cm WHERE cm.session_id = cs.id ORDER BY cm.created_at DESC LIMIT 1) as last_message
    FROM chat_sessions cs
    LEFT JOIN users u ON (
        (cs.user1_id = ? AND u.id = cs.user2_id) OR 
        (cs.user2_id = ? AND u.id = cs.user1_id)
    )
    LEFT JOIN admins a ON (
        (cs.user1_id = ? AND a.id = -cs.user2_id) OR 
        (cs.user2_id = ? AND a.id = -cs.user1_id)
    )
    WHERE cs.user1_id = ? OR cs.user2_id = ?
    ORDER BY cs.last_message_at DESC
");
$stmt->execute([
    $user_id, // 1. CASE user1_id
    $user_id, // 2. Subquery to_user_id
    $user_id, // 3. Join users user1_id
    $user_id, // 4. Join users user2_id
    $user_id, // 5. Join admins user1_id
    $user_id, // 6. Join admins user2_id
    $user_id, // 7. WHERE user1_id
    $user_id  // 8. WHERE user2_id
]);
$chat_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 修正数据：确保ID为0显示为系统通知，处理负ID管理员
foreach ($chat_sessions as &$session) {
    if ($session['friend_id'] == 0) {
        $session['nickname'] = '系统通知';
        $session['race'] = 'system';
        $session['level'] = 999;
    } elseif ($session['friend_id'] < 0 && empty($session['nickname'])) {
        // 如果SQL JOIN没查到管理员（可能因为删除了），显示默认名
        $session['nickname'] = '管理员 (ID:' . abs($session['friend_id']) . ')';
        $session['race'] = 'system';
    } elseif ($session['friend_id'] < 0) {
        // 管理员添加后缀
        if (strpos($session['nickname'], '管理员') === false) {
             $session['nickname'] .= ' (管理员)';
        }
    }
}
unset($session);

// 获取未读消息总数
$total_unread = 0;
foreach ($chat_sessions as $session) {
    $total_unread += $session['unread_count'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 消息中心</title>
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
        .stats-bar {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #ffc107;
        }
        .stat-label {
            font-size: 12px;
            color: #ccc;
            margin-top: 5px;
        }
        .sessions-list {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        .session-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .session-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        .session-item:last-child {
            margin-bottom: 0;
        }
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .friend-name {
            font-size: 16px;
            font-weight: bold;
            color: #4ecca3;
        }
        .message-time {
            font-size: 12px;
            color: #ccc;
        }
        .last-message {
            font-size: 14px;
            color: #ccc;
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .unread-badge {
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
        .race-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 8px;
        }
        .race-bug { background: #8bc34a; color: #1a1a2e; }
        .race-spirit { background: #9c27b0; color: white; }
        .race-ghost { background: #607d8b; color: white; }
        .race-human { background: #ff9800; color: #1a1a2e; }
        .race-god { background: #ffeb3b; color: #1a1a2e; }
        .level-badge {
            background: #4ecca3;
            color: #1a1a2e;
            padding: 1px 4px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: bold;
            margin-left: 5px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #ccc;
        }
        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="social.php" class="back-btn">返回社交</a>
            <h2>消息中心</h2>
            <div>未读: <span id="headerUnread"><?php echo $total_unread; ?></span></div>
        </div>

        <!-- 统计信息 -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-value"><?php echo count($chat_sessions); ?></div>
                <div class="stat-label">聊天会话</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="statUnread"><?php echo $total_unread; ?></div>
                <div class="stat-label">未读消息</div>
            </div>
        </div>

        <div id="message" class="message"></div>

        <!-- 聊天会话列表 -->
        <div class="sessions-list" id="sessionsList">
            <?php if (empty($chat_sessions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">💬</div>
                    <div>还没有聊天消息</div>
                    <div style="font-size: 12px; margin-top: 10px;">去和好友开始聊天吧！</div>
                </div>
            <?php else: ?>
                <?php foreach ($chat_sessions as $session): ?>
                <div class="session-item" onclick="openChat(<?php echo $session['friend_id']; ?>, <?php echo $session['session_id']; ?>)">
                    <div class="session-header">
                        <div class="friend-name">
                            <?php echo htmlspecialchars($session['nickname']); ?>
                            <span class="level-badge">Lv.<?php echo $session['level']; ?></span>
                            <span class="race-badge race-<?php echo $session['race']; ?>">
                                <?php 
                                $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族', 'system' => '系统'];
                                echo $race_names[$session['race']] ?? '未知';
                                ?>
                            </span>
                        </div>
                        <div class="message-time">
                            <?php echo date('m-d H:i', strtotime($session['last_message_at'])); ?>
                        </div>
                    </div>
                    <div class="last-message">
                        <?php 
                        $last_message = $session['last_message'];
                        if (strlen($last_message) > 50) {
                            $last_message = substr($last_message, 0, 50) . '...';
                        }
                        echo htmlspecialchars($last_message);
                        ?>
                    </div>
                    <?php if ($session['unread_count'] > 0): ?>
                        <div class="unread-badge"><?php echo $session['unread_count']; ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openChat(friendId, sessionId) {
            window.location.href = `chat.php?friend_id=${friendId}&session_id=${sessionId}`;
        }

        // 实时获取消息列表
        async function fetchSessions() {
            try {
                const response = await fetch('api/get_chat_sessions.php');
                const result = await response.json();
                
                if (result.success) {
                    updateSessionsList(result.sessions);
                    updateUnreadCounts(result.total_unread);
                }
            } catch (error) {
                console.error('获取会话列表失败:', error);
            }
        }

        function updateUnreadCounts(count) {
            const headerUnread = document.getElementById('headerUnread');
            const statUnread = document.getElementById('statUnread');
            if (headerUnread) headerUnread.textContent = count;
            if (statUnread) statUnread.textContent = count;
        }

        function updateSessionsList(sessions) {
            const list = document.getElementById('sessionsList');
            if (!sessions || sessions.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">💬</div>
                        <div>还没有聊天消息</div>
                        <div style="font-size: 12px; margin-top: 10px;">去和好友开始聊天吧！</div>
                    </div>
                `;
                return;
            }

            let html = '';
            const raceNames = {
                'bug': '虫族', 'spirit': '灵族', 'ghost': '鬼族', 
                'human': '人族', 'god': '神族', 'system': '系统'
            };

            sessions.forEach(session => {
                const raceName = raceNames[session.race] || '未知';
                const date = new Date(session.last_message_at);
                const timeStr = `${(date.getMonth()+1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
                
                // 处理最后一条消息，截断过长内容
                let lastMsg = session.last_message || '';
                if (lastMsg.length > 50) lastMsg = lastMsg.substring(0, 50) + '...';
                
                // 转义HTML
                const safeMsg = lastMsg.replace(/&/g, "&amp;")
                                     .replace(/</g, "&lt;")
                                     .replace(/>/g, "&gt;")
                                     .replace(/"/g, "&quot;")
                                     .replace(/'/g, "&#039;");

                html += `
                <div class="session-item" onclick="openChat(${session.friend_id}, ${session.session_id})">
                    <div class="session-header">
                        <div class="friend-name">
                            ${session.nickname}
                            <span class="level-badge">Lv.${session.level}</span>
                            <span class="race-badge race-${session.race}">
                                ${raceName}
                            </span>
                        </div>
                        <div class="message-time">
                            ${timeStr}
                        </div>
                    </div>
                    <div class="last-message">
                        ${session.last_message_type === 'admin' ? '【后台消息推送】' : ''}${safeMsg}
                    </div>
                    ${session.unread_count > 0 ? `<div class="unread-badge">${session.unread_count}</div>` : ''}
                </div>
                `;
            });

            list.innerHTML = html;
        }

        // 每3秒刷新一次
        setInterval(fetchSessions, 3000);

        // 显示操作结果消息
        <?php if (isset($_GET['message'])): ?>
            document.getElementById('message').textContent = '<?php echo htmlspecialchars($_GET['message']); ?>';
            document.getElementById('message').className = 'message success';
            setTimeout(() => {
                document.getElementById('message').textContent = '';
                document.getElementById('message').className = 'message';
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>