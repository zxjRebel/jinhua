<?php
// admin/index.php
// 管理员仪表盘

// 检查管理员登录状态
include 'auth.php';
include '../config.php';

// 统计信息
$stats = [
    'users' => 0,
    'characters' => 0,
    'announcements' => 0,
    'total_coins' => 0
];

// 获取用户数量
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 获取角色数量
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM characters");
$stmt->execute();
$stats['characters'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 获取公告数量
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM system_announcements");
$stmt->execute();
$stats['announcements'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 获取总金币数量
$stmt = $pdo->prepare("SELECT SUM(coins) as total FROM users");
$stmt->execute();
$stats['total_coins'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员仪表盘</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <!-- 侧边栏 -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- 主内容区 -->
        <main class="main-content">
            <div class="header">
                <h2>管理员仪表盘</h2>
                <div class="user-info">
                    <span>欢迎，<?php echo $_SESSION['admin_username']; ?></span>
                    <a href="logout.php" class="logout-btn">退出登录</a>
                </div>
            </div>
            
            <!-- 统计信息 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>总用户数</h3>
                    <div class="stat-value"><?php echo $stats['users']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>总角色数</h3>
                    <div class="stat-value"><?php echo $stats['characters']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>公告总数</h3>
                    <div class="stat-value"><?php echo $stats['announcements']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>总金币数量</h3>
                    <div class="stat-value"><?php echo number_format($stats['total_coins']); ?></div>
                </div>
            </div>
            
            <!-- 快捷操作 -->
            <div class="quick-actions">
                <h3>快捷操作</h3>
                <div class="actions-grid">
                    <a href="users.php" class="action-btn">查看所有用户</a>
                    <a href="characters.php" class="action-btn">管理角色</a>
                    <a href="announcements.php" class="action-btn">发布公告</a>
                    <a href="push_messages.php" class="action-btn">消息推送</a>
                    <a href="race_audit.php" class="action-btn">种族审核</a>
                    <a href="world_chat.php" class="action-btn">世界聊天</a>
                    <a href="settings.php" class="action-btn">系统设置</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
