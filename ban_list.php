<?php
// ban_list.php - 封禁榜 (Optimized)
include 'config.php';
// This is a frontend page, so it should use frontend layout.

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 获取封禁列表 (直接查询 users 表，以保证实时准确性)
$banned_users = [];
try {
    // 确保查询被封禁且封禁未过期的用户
    $stmt = $pdo->prepare("SELECT 
        id, username, nickname, race, avatar,
        ban_reason, banned_at as ban_start_time, ban_expires_at as ban_end_time
    FROM users 
    WHERE is_banned = 1 
    AND (ban_expires_at IS NULL OR ban_expires_at > NOW())
    ORDER BY banned_at DESC");
    $stmt->execute();
    $banned_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // 忽略错误
}

// 种族配置
$races = [
    'bug' => ['name' => '虫族', 'color' => '#8bc34a', 'icon' => '🐛'],
    'spirit' => ['name' => '灵族', 'color' => '#00bcd4', 'icon' => '👻'],
    'ghost' => ['name' => '鬼族', 'color' => '#9c27b0', 'icon' => '💀'],
    'human' => ['name' => '人族', 'color' => '#ff9800', 'icon' => '👤'],
    'god' => ['name' => '神族', 'color' => '#f44336', 'icon' => '⚡']
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>封禁榜 - 我的进化之路</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #ff6b6b;
            --bg-dark: #1a1a2e;
            --bg-card: #16213e;
            --text-main: #ffffff;
            --text-muted: #a0a0a0;
            --border: rgba(255, 255, 255, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
            background: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 80px;
        }
        
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .back-btn {
            color: var(--text-main);
            text-decoration: none;
            font-size: 1.2rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            transition: all 0.3s ease;
        }
        
        .page-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
            animation: fadeIn 0.5s ease;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
            background: var(--bg-card);
            border-radius: 15px;
            border: 1px solid var(--border);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
            color: #4caf50;
        }
        
        .ban-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .ban-card {
            background: var(--bg-card);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            display: flex;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .ban-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }
        
        .ban-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            background: #000;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
            flex-wrap: wrap;
        }
        
        .nickname {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .race-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 4px;
            background: rgba(255,255,255,0.1);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .ban-reason {
            color: var(--primary);
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 5px;
            line-height: 1.4;
        }
        
        .ban-time {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .stamp {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 4rem;
            font-weight: 900;
            color: var(--primary);
            opacity: 0.1;
            transform: rotate(-15deg);
            border: 4px solid var(--primary);
            padding: 5px 10px;
            border-radius: 10px;
            pointer-events: none;
            user-select: none;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="social.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="page-title"><i class="fas fa-ban"></i> 封神榜</div>
        <div style="width: 40px;"></div>
    </div>

    <div class="container">
        <?php if (empty($banned_users)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>暂无封禁记录</h3>
                <p>和谐游戏，从我做起。</p>
            </div>
        <?php else: ?>
            <div class="ban-grid">
                <?php foreach ($banned_users as $user): ?>
                    <?php 
                        $race_info = $races[$user['race']] ?? ['name' => '未知', 'color' => '#ccc', 'icon' => '?'];
                    ?>
                    <div class="ban-card">
                        <img src="<?php echo !empty($user['avatar']) ? $user['avatar'] : 'https://via.placeholder.com/150'; ?>" class="user-avatar" alt="头像">
                        <div class="user-info">
                            <div class="user-name-row">
                                <span class="nickname"><?php echo htmlspecialchars($user['nickname']); ?></span>
                                <span class="race-badge" style="color: <?php echo $race_info['color']; ?>">
                                    <?php echo $race_info['icon'] . ' ' . $race_info['name']; ?>
                                </span>
                            </div>
                            <div class="ban-reason">
                                <i class="fas fa-gavel" style="margin-top: 3px;"></i>
                                <span><?php echo htmlspecialchars($user['ban_reason']); ?></span>
                            </div>
                            <div class="ban-time">
                                <div><i class="far fa-calendar-alt"></i> 封禁时间: <?php echo date('Y-m-d', strtotime($user['ban_start_time'])); ?></div>
                                <div><i class="far fa-clock"></i> 解封时间: <?php echo $user['ban_end_time'] ? date('Y-m-d H:i', strtotime($user['ban_end_time'])) : '永久'; ?></div>
                            </div>
                        </div>
                        <div class="stamp">BANNED</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'bottom_nav.php'; ?>
</body>
</html>
