<?php
// friends.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 获取好友列表
$stmt = $pdo->prepare("
    SELECT 
        f.id as friendship_id,
        u.id as user_id,
        u.nickname,
        u.race,
        u.level,
        u.rank_score,
        (SELECT COUNT(*) FROM chat_messages cm WHERE cm.to_user_id = ? AND cm.from_user_id = u.id AND cm.is_read = 0) as unread_count
    FROM friends f
    JOIN users u ON f.friend_id = u.id
    WHERE f.user_id = ? AND f.status = 'accepted'
    ORDER BY u.nickname
");
$stmt->execute([$user_id, $user_id]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取待处理的好友请求
$stmt = $pdo->prepare("
    SELECT 
        fr.id as request_id,
        fr.from_user_id,
        u.nickname,
        u.race,
        u.level,
        fr.message,
        fr.created_at
    FROM friend_requests fr
    JOIN users u ON fr.from_user_id = u.id
    WHERE fr.to_user_id = ? AND fr.status = 'pending'
    ORDER BY fr.created_at DESC
");
$stmt->execute([$user_id]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 好友列表</title>
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
        .section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .friends-list, .requests-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .friend-item, .request-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        .friend-item:hover, .request-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        .friend-info, .request-info {
            flex: 1;
        }
        .friend-name {
            font-size: 16px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 5px;
        }
        .friend-details {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #ccc;
        }
        .friend-actions, .request-actions {
            display: flex;
            gap: 8px;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .chat-btn {
            background: #4ecca3;
            color: #1a1a2e;
        }
        .remove-btn {
            background: #ff6b6b;
            color: white;
        }
        .accept-btn {
            background: #4ecca3;
            color: #1a1a2e;
        }
        .reject-btn {
            background: #6c757d;
            color: white;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
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
        .unread-badge {
            background: #ff6b6b;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
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
            <a href="social.php" class="back-btn">返回社交</a>
            <h2>好友列表</h2>
            <div>好友: <?php echo count($friends); ?>人</div>
        </div>

        <div id="message" class="message"></div>

        <!-- 待处理的好友请求 -->
        <?php if (!empty($pending_requests)): ?>
        <div class="section">
            <div class="section-title">
                <span>待处理的好友请求</span>
                <span style="color: #ffc107;"><?php echo count($pending_requests); ?>个</span>
            </div>
            <div class="requests-list">
                <?php foreach ($pending_requests as $request): ?>
                <div class="request-item">
                    <div class="request-info">
                        <div class="friend-name">
                            <?php echo htmlspecialchars($request['nickname']); ?>
                            <span class="race-badge race-<?php echo $request['race']; ?>">
                                <?php 
                                $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
                                echo $race_names[$request['race']] ?? '未知';
                                ?>
                            </span>
                        </div>
                        <div class="friend-details">
                            <span>Lv.<?php echo $request['level']; ?></span>
                            <span><?php echo date('m-d H:i', strtotime($request['created_at'])); ?></span>
                            <?php if (!empty($request['message'])): ?>
                                <span>留言: <?php echo htmlspecialchars($request['message']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="request-actions">
                        <button class="action-btn accept-btn" onclick="handleFriendRequest(<?php echo $request['request_id']; ?>, 'accept')">
                            接受
                        </button>
                        <button class="action-btn reject-btn" onclick="handleFriendRequest(<?php echo $request['request_id']; ?>, 'reject')">
                            拒绝
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 好友列表 -->
        <div class="section">
            <div class="section-title">
                <span>我的好友</span>
                <span><?php echo count($friends); ?>人</span>
            </div>
            
            <?php if (empty($friends)): ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <div>还没有好友</div>
                    <div style="font-size: 12px; margin-top: 10px;">去添加一些好友开始互动吧！</div>
                </div>
            <?php else: ?>
                <div class="friends-list">
                    <?php foreach ($friends as $friend): ?>
                    <div class="friend-item">
                        <div class="friend-info">
                            <div class="friend-name">
                                <?php echo htmlspecialchars($friend['nickname']); ?>
                                <span class="race-badge race-<?php echo $friend['race']; ?>">
                                    <?php 
                                    $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
                                    echo $race_names[$friend['race']] ?? '未知';
                                    ?>
                                </span>
                                <?php if ($friend['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $friend['unread_count']; ?>条未读</span>
                                <?php endif; ?>
                            </div>
                            <div class="friend-details">
                                <span>Lv.<?php echo $friend['level']; ?></span>
                                <span>积分: <?php echo $friend['rank_score']; ?></span>
                            </div>
                        </div>
                        <div class="friend-actions">
                            <button class="action-btn chat-btn" onclick="startChat(<?php echo $friend['user_id']; ?>, '<?php echo htmlspecialchars($friend['nickname']); ?>')">
                                聊天
                            </button>
                            <button class="action-btn remove-btn" onclick="removeFriend(<?php echo $friend['friendship_id']; ?>, '<?php echo htmlspecialchars($friend['nickname']); ?>')">
                                删除
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        async function handleFriendRequest(requestId, action) {
            try {
                const response = await fetch('api/handle_friend_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        action: action
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showMessage(result.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('网络错误，请重试', 'error');
                console.error('Error:', error);
            }
        }

        async function removeFriend(friendshipId, friendName) {
            if (!confirm(`确定要删除好友 ${friendName} 吗？`)) {
                return;
            }

            try {
                const response = await fetch('api/remove_friend.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        friendship_id: friendshipId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showMessage(result.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('网络错误，请重试', 'error');
                console.error('Error:', error);
            }
        }

        function startChat(friendId, friendName) {
            window.location.href = `chat.php?friend_id=${friendId}&friend_name=${encodeURIComponent(friendName)}`;
        }

        function showMessage(message, type) {
            const messageEl = document.getElementById('message');
            messageEl.textContent = message;
            messageEl.className = `message ${type}`;
            setTimeout(() => {
                messageEl.textContent = '';
                messageEl.className = 'message';
            }, 3000);
        }

        // 显示操作结果消息
        <?php if (isset($_GET['message'])): ?>
            showMessage('<?php echo htmlspecialchars($_GET['message']); ?>', 'success');
        <?php endif; ?>
    </script>
    <?php include 'bottom_nav.php'; ?>
</body>
</html>