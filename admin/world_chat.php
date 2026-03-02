<?php
// admin/world_chat.php
// 世界聊天管理

// 检查管理员登录状态
include 'auth.php';
include '../config.php';

// 处理删除消息请求
if (isset($_GET['delete'])) {
    $message_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM world_chat_messages WHERE id = ?");
    $stmt->execute([$message_id]);
    header('Location: world_chat.php?success=消息已删除');
    exit;
}

// 处理清空所有消息
if (isset($_POST['clear_all'])) {
    $stmt = $pdo->prepare("TRUNCATE TABLE world_chat_messages");
    $stmt->execute();
    header('Location: world_chat.php?success=所有消息已清空');
    exit;
}

// 分页设置
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 获取消息总数
$stmt = $pdo->query("SELECT COUNT(*) as total FROM world_chat_messages");
$total_messages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_messages / $per_page);

// 获取消息列表
$stmt = $pdo->prepare("
    SELECT 
        wcm.*,
        u.nickname,
        u.username
    FROM world_chat_messages wcm
    LEFT JOIN users u ON wcm.user_id = u.id
    ORDER BY wcm.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>世界聊天管理</title>
    <style>
        /* 基本样式，复用 characters.php 的样式 */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; color: #333; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; }
        .sidebar h1 { font-size: 24px; margin-bottom: 30px; text-align: center; }
        .nav-menu { list-style: none; }
        .nav-menu li { margin-bottom: 10px; }
        .nav-menu a { display: block; padding: 12px 15px; color: white; text-decoration: none; border-radius: 5px; transition: background-color 0.3s ease; }
        .nav-menu a:hover, .nav-menu a.active { background-color: rgba(255, 255, 255, 0.2); }
        .main-content { flex: 1; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid #ddd; }
        .header h2 { font-size: 28px; color: #333; }
        .user-info { display: flex; align-items: center; }
        .user-info span { margin-right: 15px; font-weight: bold; }
        .logout-btn { padding: 8px 15px; background-color: #f44336; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; }
        
        /* 表格样式 */
        .data-table { width: 100%; border-collapse: collapse; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .data-table th { background-color: #f9f9f9; font-weight: bold; color: #555; }
        .data-table tr:hover { background-color: #f5f5f5; }
        .action-btn { padding: 5px 10px; border-radius: 3px; text-decoration: none; font-size: 12px; margin-right: 5px; display: inline-block; }
        .delete-btn { background-color: #f44336; color: white; }
        .clear-btn { background-color: #ff9800; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        
        /* 分页样式 */
        .pagination { margin-top: 20px; display: flex; justify-content: center; gap: 5px; }
        .pagination a { padding: 8px 12px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; background-color: white; }
        .pagination a.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-color: transparent; }
        
        .success-message { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h2>世界聊天管理</h2>
                <div class="user-info">
                    <span>管理员: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="logout.php" class="logout-btn">退出</a>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>

            <div style="margin-bottom: 20px; text-align: right;">
                <form method="POST" onsubmit="return confirm('确定要清空所有聊天记录吗？此操作不可恢复！');" style="display: inline;">
                    <button type="submit" name="clear_all" class="clear-btn">清空所有消息</button>
                </form>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>发送者</th>
                        <th>内容</th>
                        <th>发送时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr><td colspan="5" style="text-align: center;">暂无消息</td></tr>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td><?php echo $msg['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($msg['nickname'] ?? '未知'); ?>
                                <br>
                                <span style="font-size: 12px; color: #999;">(<?php echo htmlspecialchars($msg['username'] ?? ''); ?>)</span>
                            </td>
                            <td style="max-width: 400px; word-break: break-all;">
                                <?php echo htmlspecialchars($msg['message']); ?>
                            </td>
                            <td><?php echo $msg['created_at']; ?></td>
                            <td>
                                <a href="world_chat.php?delete=<?php echo $msg['id']; ?>" class="action-btn delete-btn" onclick="return confirm('确定删除这条消息吗？')">删除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>