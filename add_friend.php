<?php
// add_friend.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 处理搜索
$search_results = [];
$search_query = '';

// 支持 POST 或 GET 搜索
if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) || isset($_GET['q'])) {
    $search_query = isset($_POST['search_query']) ? trim($_POST['search_query']) : (isset($_GET['q']) ? trim($_GET['q']) : '');
    
    if (!empty($search_query)) {
        // 搜索用户（排除自己）
        $stmt = $pdo->prepare("
            SELECT id, nickname, username, race, level, rank_score
            FROM users 
            WHERE (nickname LIKE ? OR username LIKE ?) 
            AND id != ?
            AND race IS NOT NULL
            LIMIT 20
        ");
        $search_term = "%{$search_query}%";
        $stmt->execute([$search_term, $search_term, $user_id]);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取当前用户的好友列表ID
        $stmt = $pdo->prepare("SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'");
        $stmt->execute([$user_id]);
        $friend_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 获取当前用户已发送的待处理请求ID
        $stmt = $pdo->prepare("SELECT to_user_id FROM friend_requests WHERE from_user_id = ? AND status = 'pending'");
        $stmt->execute([$user_id]);
        $pending_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 添加好友</title>
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
        .search-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .search-title {
            font-size: 18px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 15px;
        }
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .search-input {
            flex: 1;
            padding: 12px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
        }
        .search-input:focus {
            outline: none;
            border-color: #4ecca3;
            box-shadow: 0 0 0 2px rgba(78, 204, 163, 0.3);
        }
        .search-btn {
            background: #4ecca3;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            color: #1a1a2e;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(78, 204, 163, 0.4);
        }
        .results-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        .results-title {
            font-size: 16px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 15px;
        }
        .user-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .user-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        .user-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        .user-info {
            flex: 1;
        }
        .user-name {
            font-size: 16px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 5px;
        }
        .user-details {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #ccc;
        }
        .add-btn {
            background: #4ecca3;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            color: #1a1a2e;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(78, 204, 163, 0.4);
        }
        .add-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
        .search-tips {
            font-size: 12px;
            color: #ccc;
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="social.php" class="back-btn">返回社交</a>
            <h2>添加好友</h2>
            <div>搜索玩家</div>
        </div>

        <div id="message" class="message"></div>

        <!-- 搜索区域 -->
        <div class="search-section">
            <div class="search-title">搜索玩家</div>
            <form method="POST" class="search-form">
                <input type="text" name="search_query" class="search-input" 
                       placeholder="输入玩家昵称或账号..." 
                       value="<?php echo htmlspecialchars($search_query); ?>" 
                       required>
                <button type="submit" name="search" class="search-btn">搜索</button>
            </form>
            <div class="search-tips">
                提示：可以通过昵称或账号搜索其他玩家
            </div>
        </div>

        <!-- 搜索结果 -->
        <?php if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) || isset($_GET['q'])): ?>
        <div class="results-section">
            <div class="results-title">
                搜索结果 (<?php echo count($search_results); ?>人)
            </div>
            
            <?php if (empty($search_results)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <div>没有找到相关玩家</div>
                    <div style="font-size: 12px; margin-top: 10px;">请检查搜索条件是否正确</div>
                </div>
            <?php else: ?>
                <div class="user-list">
                    <?php foreach ($search_results as $user): ?>
                    <?php 
                        $is_friend = isset($friend_ids) && in_array($user['id'], $friend_ids);
                        $is_pending = isset($pending_ids) && in_array($user['id'], $pending_ids);
                    ?>
                    <div class="user-item">
                        <div class="user-info">
                            <div class="user-name">
                                <?php echo htmlspecialchars($user['nickname']); ?>
                                <span class="race-badge race-<?php echo $user['race']; ?>">
                                    <?php 
                                    $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
                                    echo $race_names[$user['race']] ?? '未知';
                                    ?>
                                </span>
                            </div>
                            <div class="user-details">
                                <span>账号: <?php echo htmlspecialchars($user['username']); ?></span>
                                <span>Lv.<?php echo $user['level']; ?></span>
                                <span>积分: <?php echo $user['rank_score']; ?></span>
                            </div>
                        </div>
                        <?php if ($is_friend): ?>
                            <button class="add-btn" disabled style="background: #6c757d; cursor: not-allowed; opacity: 0.8;">
                                已是好友
                            </button>
                        <?php elseif ($is_pending): ?>
                            <button class="add-btn" disabled style="background: #ffc107; color: #1a1a2e; cursor: not-allowed; opacity: 0.8;">
                                请求已发送
                            </button>
                        <?php else: ?>
                            <button class="add-btn" onclick="sendFriendRequest(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nickname']); ?>')">
                                添加好友
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        async function sendFriendRequest(toUserId, toUserName) {
            // 打开自定义模态框
            document.getElementById('modalToUserId').value = toUserId;
            document.getElementById('modalUserName').textContent = toUserName;
            document.getElementById('addFriendModal').style.display = 'block';
            document.getElementById('friendMessage').focus();
        }

        function closeModal() {
            document.getElementById('addFriendModal').style.display = 'none';
        }

        // 提交好友请求
        async function submitFriendRequest() {
            const toUserId = document.getElementById('modalToUserId').value;
            const message = document.getElementById('friendMessage').value;
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            
            try {
                // 显示加载状态
                submitBtn.disabled = true;
                submitBtn.textContent = '发送中...';
                
                const response = await fetch('api/send_friend_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        to_user_id: toUserId,
                        message: message || ''
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showMessage(result.message, 'success');
                    closeModal();
                    // 找到对应的按钮并更新状态
                    const btn = document.querySelector(`button[onclick*="sendFriendRequest(${toUserId}"]`);
                    if (btn) {
                        btn.textContent = '已发送';
                        btn.disabled = true;
                    }
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('网络错误，请重试', 'error');
                console.error('Error:', error);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }

        // 窗口点击关闭
        window.onclick = function(event) {
            const modal = document.getElementById('addFriendModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        function showMessage(text, type) {
            const msgDiv = document.getElementById('message');
            msgDiv.textContent = text;
            msgDiv.className = 'message ' + type;
            msgDiv.style.display = 'block';
            
            setTimeout(() => {
                msgDiv.style.display = 'none';
            }, 3000);
        }
    </script>

    <!-- 自定义添加好友模态框 -->
    <div id="addFriendModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>添加好友</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalToUserId">
                <p>发送给: <span id="modalUserName" style="color: #4ecca3; font-weight: bold;"></span></p>
                <div class="form-group">
                    <label>验证消息 (可选):</label>
                    <textarea id="friendMessage" class="form-control" rows="3" placeholder="你好，我想加你为好友..."></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeModal()">取消</button>
                    <button class="btn-submit" id="submitBtn" onclick="submitFriendRequest()">发送请求</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.6); 
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background: linear-gradient(135deg, #16213e, #1a1a2e);
            margin: 15% auto; 
            padding: 20px;
            border: 1px solid #4ecca3;
            width: 90%;
            max-width: 400px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            color: #fff;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: #fff; }
        .form-control {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            border-radius: 6px;
            resize: vertical;
        }
        .form-control:focus {
            outline: none;
            border-color: #4ecca3;
        }
        .modal-footer {
            margin-top: 20px;
            text-align: right;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-submit {
            background: #4ecca3;
            color: #1a1a2e;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-cancel {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-submit:hover { background: #45b691; }
        .btn-cancel:hover { background: rgba(255,255,255,0.2); }
    </style>
</body>
</html>