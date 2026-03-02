<?php
// admin/push_messages.php
// 管理员消息推送功能

// 检查管理员登录状态
include 'auth.php';
include '../config.php';

// 自动检测并修复 chat_messages 表结构
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM chat_messages LIKE 'message_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($column && strpos($column['Type'], "'admin'") === false) {
        $pdo->exec("ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text', 'system', 'admin') DEFAULT 'text'");
    }
} catch (Exception $e) {
    // 忽略错误，避免影响主流程
}

$success = '';
$error = '';
$users = [];

// 获取所有用户列表，包含未读消息数量
function getUsersList($pdo) {
    $stmt = $pdo->prepare("SELECT 
        u.id, u.username, u.nickname, 
        COUNT(cm.id) as total_messages, 
        SUM(CASE WHEN cm.is_read = 0 THEN 1 ELSE 0 END) as unread_messages
    FROM users u
    LEFT JOIN chat_sessions cs ON (cs.user1_id = ? AND cs.user2_id = u.id) OR (cs.user2_id = ? AND cs.user1_id = u.id)
    LEFT JOIN chat_messages cm ON cm.session_id = cs.id AND cm.from_user_id = ?
    GROUP BY u.id
    ORDER BY u.username");
    $admin_id = $_SESSION['admin_id'];
    $stmt->execute([$admin_id, $admin_id, $admin_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$users = getUsersList($pdo);

// 处理查看消息历史记录
$selected_user_id = $_GET['view_user'] ?? '';
$message_history = [];
$selected_user = null;

if ($selected_user_id) {
    $selected_user_id = (int)$selected_user_id;
    // 获取用户信息
    $stmt = $pdo->prepare("SELECT id, username, nickname FROM users WHERE id = ?");
    $stmt->execute([$selected_user_id]);
    $selected_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取聊天会话 (包括管理员私聊和系统消息)
    // 注意：这里我们主要查看管理员私聊的会话，但也尝试查找系统消息会话(ID 0)
    $admin_id = $_SESSION['admin_id'];
    
    // 查找与管理员的会话 (管理员ID取负值以避免与用户ID冲突)
    $neg_admin_id = -$admin_id;
    $stmt = $pdo->prepare("SELECT id FROM chat_sessions WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
    $stmt->execute([$neg_admin_id, $selected_user_id, $selected_user_id, $neg_admin_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 查找系统消息会话 (user1_id = 0)
    $stmt = $pdo->prepare("SELECT id FROM chat_sessions WHERE (user1_id = 0 AND user2_id = ?) OR (user1_id = ? AND user2_id = 0)");
    $stmt->execute([$selected_user_id, $selected_user_id]);
    $system_session = $stmt->fetch(PDO::FETCH_ASSOC);

    $session_ids = [];
    if ($session) $session_ids[] = $session['id'];
    if ($system_session) $session_ids[] = $system_session['id'];
    
    if (!empty($session_ids)) {
        // 获取消息历史记录
        $placeholders = implode(',', array_fill(0, count($session_ids), '?'));
        $sql = "SELECT 
            cm.id, cm.content, cm.message_type, cm.is_read, cm.created_at, cm.from_user_id,
            CASE 
                WHEN cm.from_user_id = 0 THEN 'system'
                WHEN cm.from_user_id = ? THEN 'admin' 
                ELSE 'user' 
            END as sender_type
        FROM chat_messages cm
        WHERE cm.session_id IN ($placeholders)
        ORDER BY cm.created_at DESC
        LIMIT 50";
        
        $params = array_merge([$neg_admin_id], $session_ids);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $message_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// 处理消息推送
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $message = $_POST['message'] ?? '';
    $message_type = $_POST['message_type'] ?? 'text';
    
    // 验证表单数据
    if (empty($user_id)) {
        $error = '请选择一个用户';
    } else if (empty($message)) {
        $error = '消息内容不能为空';
    } else if (strlen($message) > 1000) {
        $error = '消息长度不能超过1000字';
    } else {
        try {
            // 获取管理员信息
            $admin_id = $_SESSION['admin_id'];
            
            // 根据消息类型处理
            // 逻辑变更：
            // 1. 无论是"系统消息"还是"普通消息"，发送者ID都统一为 0（代表系统），且 message_type 设为 'system'。
            // 2. 这样在前端都会显示为 "系统通知"。
            // 3. 区别在于文案前缀：
            //    - 选 "系统消息" -> 文案前缀 "【普通消息】"
            //    - 选 "普通消息" (admin/text) -> 文案前缀 "【系统消息】"
            
            $sender_id = 0;
            $db_message_type = 'system'; // 数据库中统一存为 system，确保显示为系统通知
            
            if ($message_type === 'system') {
                // 用户选了 "系统消息"
                $final_message = "【系统消息】" . $message;
            } else {
                // 用户选了 "普通消息" (或其他)
                $final_message = "【普通消息】" . $message;
            }
            
            // 检查会话是否存在
            // 统一使用 (0, user_id) 的系统会话
            
            $u1 = $sender_id;
            $u2 = $user_id;
            
            $stmt = $pdo->prepare("SELECT id FROM chat_sessions WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
            $stmt->execute([$u1, $u2, $u2, $u1]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                // 创建新的聊天会话
                $stmt = $pdo->prepare("INSERT INTO chat_sessions (user1_id, user2_id, last_message_at) VALUES (?, ?, NOW())");
                $stmt->execute([$u1, $u2]);
                $session_id = $pdo->lastInsertId();
            } else {
                $session_id = $session['id'];
            }
            
            // 插入消息
            $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, from_user_id, to_user_id, message_type, content, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$session_id, $sender_id, $user_id, $db_message_type, $final_message]);
            
            // 更新会话最后活动时间
            $stmt = $pdo->prepare("UPDATE chat_sessions SET last_message_at = NOW() WHERE id = ?");
            $stmt->execute([$session_id]);
            
            $success = '消息推送成功';
            
            // 刷新历史记录
            if ($selected_user_id == $user_id) {
                 header("Location: push_messages.php?view_user=$user_id&success=" . urlencode($success));
                 exit;
            }
            
        } catch (PDOException $e) {
            $error = '消息推送失败: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>消息推送 - 管理后台</title>
    <style>
        /* 基本样式，继承自index.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* 主内容区样式 */
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .header h2 {
            font-size: 28px;
            color: #333;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info span {
            margin-right: 15px;
            font-weight: bold;
        }
        
        .logout-btn {
            padding: 8px 15px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
        }
        
        /* 消息样式 */
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* 表单样式 */
        .form-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #666;
        }
        
        .form-group select,
        .form-group textarea,
        .form-group input[type="radio"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        .form-group input[type="radio"] {
            width: auto;
            margin-right: 5px;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .radio-group label {
            font-weight: normal;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .submit-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
        
        /* 按钮容器 */
        .button-container {
            text-align: center;
            margin-top: 30px;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-system {
            background-color: #17a2b8;
            color: white;
        }
        
        .badge-admin {
            background-color: #28a745;
            color: white;
        }
        
        .badge-user {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 侧边栏 -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- 主内容区 -->
        <main class="main-content">
            <div class="header">
                <h2>消息推送</h2>
                <div class="user-info">
                    <span>欢迎，<?php echo $_SESSION['admin_username']; ?></span>
                    <a href="logout.php" class="logout-btn">退出登录</a>
                </div>
            </div>
            
            <!-- 消息显示 -->
            <?php if (!empty($success)): ?>
                <div class="message success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="message error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- 消息推送表单 -->
            <div class="form-container">
                <h3>发送消息</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="user_id">选择用户:</label>
                        <select id="user_id" name="user_id" required onchange="if(this.value) window.location.href='push_messages.php?view_user='+this.value">
                            <option value="">请选择用户</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $selected_user_id == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['nickname']); ?>)
                                    <?php if ($user['total_messages'] > 0): ?>
                                        - 消息: <?php echo $user['total_messages']; ?>条 (未读: <?php echo $user['unread_messages']; ?>条)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message_type">消息类型:</label>
                        <div class="radio-group">
                            <label><input type="radio" name="message_type" value="text" checked> 普通消息 (管理员身份)</label>
                            <label><input type="radio" name="message_type" value="system"> 系统消息 (系统通知)</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">消息内容:</label>
                        <textarea id="message" name="message" placeholder="请输入要发送的消息" required maxlength="1000"></textarea>
                    </div>
                    
                    <div class="button-container">
                        <button type="submit" class="submit-btn">发送消息</button>
                    </div>
                </form>
            </div>
            
            <!-- 消息历史记录 -->
            <?php if ($selected_user): ?>
                <div class="form-container" style="margin-top: 30px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>与 <?php echo htmlspecialchars($selected_user['username']); ?> (<?php echo htmlspecialchars($selected_user['nickname']); ?>) 的消息历史</h3>
                        <a href="push_messages.php" class="submit-btn" style="padding: 8px 16px; font-size: 14px; background: #6c757d;">清除选择</a>
                    </div>
                    
                    <?php if (empty($message_history)): ?>
                        <div style="text-align: center; padding: 30px; color: #666;">
                            还没有发送过消息
                        </div>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background-color: rgba(0, 0, 0, 0.05);">
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd; width: 50%;">内容</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">类型</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">发送者</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">状态</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">发送时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($message_history as $message): ?>
                                        <tr style="border-bottom: 1px solid #eee; transition: background-color 0.2s;">
                                            <td style="padding: 12px; max-width: 400px; word-wrap: break-word;"><?php echo htmlspecialchars($message['content']); ?></td>
                                            <td style="padding: 12px;">
                                                <?php if ($message['message_type'] === 'system'): ?>
                                                    <span class="badge badge-system">系统消息</span>
                                                <?php else: ?>
                                                    <span class="badge badge-user">普通消息</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px;">
                                                <?php if ($message['sender_type'] === 'system'): ?>
                                                    <span class="badge badge-system">系统</span>
                                                <?php elseif ($message['sender_type'] === 'admin'): ?>
                                                    <span class="badge badge-admin">管理员</span>
                                                <?php else: ?>
                                                    <span class="badge badge-user">用户</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px;">
                                                <?php if ($message['sender_type'] !== 'user'): ?>
                                                    <?php echo $message['is_read'] ? '<span style="color: green;">已读</span>' : '<span style="color: red;">未读</span>'; ?>
                                                <?php else: ?>
                                                    <span style="color: gray;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px; font-size: 12px; color: #666;"><?php echo date('Y-m-d H:i:s', strtotime($message['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- 消息统计列表 -->
                <div class="form-container" style="margin-top: 30px;">
                    <h3>用户消息统计</h3>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background-color: rgba(0, 0, 0, 0.05);">
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">用户名</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">昵称</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">总消息数</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">未读消息</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr style="border-bottom: 1px solid #eee; transition: background-color 0.2s;">
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['nickname']); ?></td>
                                        <td style="padding: 12px;"><?php echo $user['total_messages']; ?></td>
                                        <td style="padding: 12px;">
                                            <?php if ($user['unread_messages'] > 0): ?>
                                                <span style="color: red; font-weight: bold;"><?php echo $user['unread_messages']; ?></span>
                                            <?php else: ?>
                                                <?php echo $user['unread_messages']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <a href="push_messages.php?view_user=<?php echo $user['id']; ?>" class="submit-btn" style="padding: 6px 12px; font-size: 12px; text-decoration: none; display: inline-block;">
                                                查看详情
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>