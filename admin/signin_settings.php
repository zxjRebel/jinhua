<?php
// admin/signin_settings.php
// 签到管理页面

include 'auth.php';
include '../config.php';

$success = '';
$error = '';

// 读取当前签到配置
$signin_config = $signin_config ?? [
    'daily_rewards' => [
        1 => ['coins' => 30, 'exp' => 10],    // 周一
        2 => ['coins' => 40, 'exp' => 30],    // 周二  
        3 => ['coins' => 50, 'exp' => 50],   // 周三
        4 => ['coins' => 60, 'exp' => 80],   // 周四
        5 => ['coins' => 70, 'exp' => 120],   // 周五
        6 => ['coins' => 80, 'exp' => 150],   // 周六
        7 => ['coins' => 150, 'exp' => 300],   // 周日（周奖励）
    ],
    'streak_bonus' => [
        7 => ['coins' => 200, 'exp' => 100],  // 连续7天额外奖励
        30 => ['coins' => 2500, 'exp' => 500] // 连续30天额外奖励
    ]
];

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 验证并处理每日奖励
        $daily_rewards = [];
        for ($i = 1; $i <= 7; $i++) {
            $coins = isset($_POST["daily_coins_{$i}"]) ? (int)$_POST["daily_coins_{$i}"] : 0;
            $exp = isset($_POST["daily_exp_{$i}"]) ? (int)$_POST["daily_exp_{$i}"] : 0;
            $daily_rewards[$i] = ['coins' => $coins, 'exp' => $exp];
        }
        
        // 验证并处理连续签到奖励
        $streak_bonus = [];
        $streak_days = [7, 30];
        foreach ($streak_days as $days) {
            $coins = isset($_POST["streak_coins_{$days}"]) ? (int)$_POST["streak_coins_{$days}"] : 0;
            $exp = isset($_POST["streak_exp_{$days}"]) ? (int)$_POST["streak_exp_{$days}"] : 0;
            $streak_bonus[$days] = ['coins' => $coins, 'exp' => $exp];
        }
        
        // 读取config.php文件内容
        $config_content = file_get_contents('../config.php');
        
        // 更新每日奖励配置
        $daily_rewards_str = "'daily_rewards' => [\n";
        foreach ($daily_rewards as $day => $reward) {
            $daily_rewards_str .= "        {$day} => ['coins' => {$reward['coins']}, 'exp' => {$reward['exp']}],    // " . ['周一', '周二', '周三', '周四', '周五', '周六', '周日'][$day - 1] . "\n";
        }
        $daily_rewards_str .= "    ]";
        
        // 更新连续签到奖励配置
        $streak_bonus_str = "'streak_bonus' => [\n";
        foreach ($streak_bonus as $days => $reward) {
            $streak_bonus_str .= "        {$days} => ['coins' => {$reward['coins']}, 'exp' => {$reward['exp']}],  // 连续{$days}天额外奖励\n";
        }
        $streak_bonus_str .= "    ]";
        
        // 替换config.php中的签到配置
        $new_config = preg_replace(
            '/\$signin_config\s*=\s*\[([\s\S]*?)\];/',
            "\$signin_config = [\n    {$daily_rewards_str},\n    {$streak_bonus_str}\n];",
            $config_content
        );
        
        // 保存修改后的配置
        file_put_contents('../config.php', $new_config);
        
        // 更新当前配置变量
        $signin_config['daily_rewards'] = $daily_rewards;
        $signin_config['streak_bonus'] = $streak_bonus;
        
        $success = '签到配置更新成功';
    } catch (Exception $e) {
        $error = '更新失败: ' . $e->getMessage();
    }
}

// 获取签到统计信息
$stats = [];
try {
    // 总签到次数
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM signin_records");
    $stats['total_signins'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 今日签到人数
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as today_count FROM signin_records WHERE signin_date = ?");
    $stmt->execute([$today]);
    $stats['today_signins'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_count'];
    
    // 本周签到人数
    $week_start = date('Y-m-d', strtotime('this week'));
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as week_count FROM signin_records WHERE signin_date >= ?");
    $stmt->execute([$week_start]);
    $stats['week_signins'] = $stmt->fetch(PDO::FETCH_ASSOC)['week_count'];
    
    // 连续签到最多的用户
    $stmt = $pdo->query("SELECT user_id, longest_streak FROM signin_streak ORDER BY longest_streak DESC LIMIT 1");
    $top_streak = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['top_streak'] = $top_streak['longest_streak'] ?? 0;
    
    // 活跃签到用户数（最近7天有签到记录）
    $week_ago = date('Y-m-d', strtotime('-7 days'));
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as active_count FROM signin_records WHERE signin_date >= ?");
    $stmt->execute([$week_ago]);
    $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_count'];
    
} catch (PDOException $e) {
    $error .= ' 获取统计数据失败: ' . $e->getMessage();
}

// 星期名称映射
$week_days = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>签到管理 - 管理员后台</title>
    <style>
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
        
        /* 侧边栏样式 */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .sidebar h1 {
            font-size: 24px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .nav-menu {
            list-style: none;
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
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background-color: rgba(255, 255, 255, 0.2);
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
        
        /* 成功消息样式 */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* 错误消息样式 */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* 签到统计样式 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #4ecca3;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        /* 表单容器样式 */
        .form-container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #4ecca3;
            font-size: 20px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .rewards-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .rewards-table th,
        .rewards-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .rewards-table th {
            background-color: #f8f9fa;
            color: #666;
            font-weight: bold;
        }
        
        .rewards-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .rewards-table input {
            width: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
                <h2>签到管理</h2>
                <div class="user-info">
                    <span>欢迎，<?php echo $_SESSION['admin_username']; ?></span>
                    <a href="logout.php" class="logout-btn">退出登录</a>
                </div>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
        
        <!-- 签到统计 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_signins'] ?? 0; ?></div>
                <div class="stat-label">总签到次数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['today_signins'] ?? 0; ?></div>
                <div class="stat-label">今日签到人数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['week_signins'] ?? 0; ?></div>
                <div class="stat-label">本周签到人数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_users'] ?? 0; ?></div>
                <div class="stat-label">活跃签到用户</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['top_streak'] ?? 0; ?></div>
                <div class="stat-label">最长连续签到</div>
            </div>
        </div>
        
        <!-- 签到配置表单 -->
        <div class="form-container">
            <div class="section-title">签到配置</div>
            <form method="POST">
                <!-- 每日奖励配置 -->
                <h3 style="color: #ffc107; margin-bottom: 15px;">每日奖励配置</h3>
                <table class="rewards-table">
                    <thead>
                        <tr>
                            <th>星期</th>
                            <th>金币奖励</th>
                            <th>经验奖励</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <tr>
                                <td><?php echo $week_days[$i - 1]; ?></td>
                                <td>
                                    <input type="number" name="daily_coins_<?php echo $i; ?>" value="<?php echo $signin_config['daily_rewards'][$i]['coins']; ?>" min="0">
                                </td>
                                <td>
                                    <input type="number" name="daily_exp_<?php echo $i; ?>" value="<?php echo $signin_config['daily_rewards'][$i]['exp']; ?>" min="0">
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                
                <!-- 连续签到奖励配置 -->
                <h3 style="color: #ffc107; margin-bottom: 15px;">连续签到奖励配置</h3>
                <table class="rewards-table">
                    <thead>
                        <tr>
                            <th>连续天数</th>
                            <th>金币奖励</th>
                            <th>经验奖励</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ([7, 30] as $days): ?>
                            <tr>
                                <td>连续<?php echo $days; ?>天</td>
                                <td>
                                    <input type="number" name="streak_coins_<?php echo $days; ?>" value="<?php echo $signin_config['streak_bonus'][$days]['coins']; ?>" min="0">
                                </td>
                                <td>
                                    <input type="number" name="streak_exp_<?php echo $days; ?>" value="<?php echo $signin_config['streak_bonus'][$days]['exp']; ?>" min="0">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <button type="submit" class="submit-btn">保存配置</button>
            </form>
        </div>
        </main>
    </div>
</body>
</html>