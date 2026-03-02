<?php
// rankings.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 获取用户信息
$stmt = $pdo->prepare("SELECT nickname, rank_score, level, coins, race FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// 获取段位信息
$current_rank_info = get_rank_info($current_user['rank_score']);

// 排行榜类型
$rank_type = $_GET['type'] ?? 'rank_score';
$valid_types = ['rank_score', 'level', 'coins'];
if (!in_array($rank_type, $valid_types)) {
    $rank_type = 'rank_score';
}

// 排行榜标题配置
$rank_titles = [
    'rank_score' => '段位积分榜',
    'level' => '玩家等级榜', 
    'coins' => '财富金币榜'
];

// 获取排行榜数据
switch ($rank_type) {
    case 'level':
        $order_by = 'level DESC, exp DESC';
        break;
    case 'coins':
        $order_by = 'coins DESC, level DESC';
        break;
    default:
        $order_by = 'rank_score DESC, level DESC';
        break;
}

$stmt = $pdo->prepare("
    SELECT id, nickname, rank_score, level, coins, race 
    FROM users 
    WHERE race IS NOT NULL 
    ORDER BY $order_by 
    LIMIT 100
");
$stmt->execute();
$rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取用户排名
$stmt = $pdo->prepare("
    SELECT COUNT(*) as user_rank 
    FROM users 
    WHERE race IS NOT NULL AND 
    (
        ($rank_type > ?) OR 
        ($rank_type = ? AND level > ?) OR 
        ($rank_type = ? AND level = ? AND id < ?)
    )
");
$compare_value = $current_user[$rank_type];
$stmt->execute([$compare_value, $compare_value, $current_user['level'], $compare_value, $current_user['level'], $user_id]);
$user_rank = $stmt->fetchColumn() + 1;

// 获取详细的段位信息
$current_rank_info = get_rank_info($current_user['rank_score']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 排行榜</title>
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
        .rank-tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .rank-tab {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid transparent;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: bold;
        }
        .rank-tab:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .rank-tab.active {
            background: rgba(78, 204, 163, 0.2);
            border-color: #4ecca3;
            color: #4ecca3;
        }
        .current-user-rank {
            background: rgba(78, 204, 163, 0.1);
            border: 2px solid #4ecca3;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .user-rank-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .user-rank-title {
            font-size: 16px;
            font-weight: bold;
            color: #4ecca3;
        }
        .user-rank-value {
            font-size: 18px;
            font-weight: bold;
            color: #ffc107;
        }
        .user-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            font-size: 12px;
        }
        .user-stat {
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
            padding: 5px;
            border-radius: 6px;
        }
        .rankings-list {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 15px;
            backdrop-filter: blur(10px);
        }
        .ranking-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 15px;
            align-items: center;
            padding: 12px;
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .ranking-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }
        .ranking-item.current-user {
            background: rgba(78, 204, 163, 0.2);
            border: 2px solid #4ecca3;
        }
        .rank-number {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 14px;
        }
        .rank-1 {
            background: linear-gradient(135deg, #ffd700, #ff9800);
            color: #1a1a2e;
        }
        .rank-2 {
            background: linear-gradient(135deg, #c0c0c0, #9e9e9e);
            color: #1a1a2e;
        }
        .rank-3 {
            background: linear-gradient(135deg, #cd7f32, #8d6e63);
            color: #1a1a2e;
        }
        .rank-other {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .user-name {
            font-weight: bold;
            font-size: 14px;
            color: #4ecca3;
        }
        .user-details {
            display: flex;
            gap: 10px;
            font-size: 11px;
            color: #ccc;
        }
        .rank-score {
            text-align: right;
        }
        .score-value {
            font-size: 16px;
            font-weight: bold;
            color: #ffc107;
        }
        .score-label {
            font-size: 10px;
            color: #ccc;
        }
        .race-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 5px;
        }
        .race-bug { background: #8bc34a; color: #1a1a2e; }
        .race-spirit { background: #9c27b0; color: white; }
        .race-ghost { background: #607d8b; color: white; }
        .race-human { background: #ff9800; color: #1a1a2e; }
        .race-god { background: #ffeb3b; color: #1a1a2e; }
        .empty-rankings {
            text-align: center;
            padding: 40px 20px;
            color: #ccc;
        }
        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .last-updated {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            color: #ccc;
            opacity: 0.7;
        }
        
        .rank-stars {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            margin-left: 5px;
        }
        
        .star-icon {
            font-size: 10px;
            color: #ffc107;
        }
        
        .star-icon.empty {
            opacity: 0.3;
        }
        
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">返回大厅</a>
            <h2>排行榜</h2>
            <div>我的排名: #<?php echo $user_rank; ?></div>
        </div>

        <!-- 排行榜标签 -->
        <div class="rank-tabs">
            <div class="rank-tab <?php echo $rank_type === 'rank_score' ? 'active' : ''; ?>" 
                 onclick="changeRankType('rank_score')">
                🏆 段位榜
            </div>
            <div class="rank-tab <?php echo $rank_type === 'level' ? 'active' : ''; ?>" 
                 onclick="changeRankType('level')">
                ⭐ 等级榜
            </div>
            <div class="rank-tab <?php echo $rank_type === 'coins' ? 'active' : ''; ?>" 
                 onclick="changeRankType('coins')">
                💰 财富榜
            </div>
        </div>

        <!-- 当前用户排名 -->
<div class="current-user-rank">
    <div class="user-rank-header">
        <div class="user-rank-title">我的排名</div>
        <div class="user-rank-value">#<?php echo $user_rank; ?></div>
    </div>
    <div class="user-stats">
        <div class="user-stat">
            <div>段位</div>
            <div style="color: <?php echo $current_rank_info['color']; ?>; font-weight: bold;">
                <?php echo $current_rank_info['display_text']; ?>
            </div>
        </div>
        <div class="user-stat">
            <div>积分</div>
            <div style="color: #ffc107; font-weight: bold;">
                <?php echo $current_user['rank_score']; ?>
            </div>
        </div>
        <div class="user-stat">
            <div>等级</div>
            <div style="color: #4ecca3; font-weight: bold;">
                Lv.<?php echo $current_user['level']; ?>
            </div>
        </div>
    </div>
    <!-- 添加段位进度显示 -->
    <div style="margin-top: 10px; font-size: 12px; color: #ccc; text-align: center;">
        下一星还需: <?php echo ceil($current_rank_info['max_progress'] - $current_rank_info['current_progress']); ?> 积分
    </div>
</div>

        <!-- 排行榜列表 -->
<div class="rankings-list">
    <h3 style="text-align: center; margin-bottom: 15px; color: #4ecca3;">
        <?php echo $rank_titles[$rank_type]; ?>
    </h3>
    
    <?php if (empty($rankings)): ?>
        <div class="empty-rankings">
            <div class="empty-icon">📊</div>
            <div>暂无排行榜数据</div>
            <div style="font-size: 12px; margin-top: 10px;">快去进行游戏登上排行榜吧！</div>
        </div>
    <?php else: ?>
        <?php foreach ($rankings as $index => $user): ?>
            <?php 
            $rank_number = $index + 1;
            $is_current_user = $user['id'] == $user_id;
            $user_rank_info = get_rank_info($user['rank_score']);
            $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
            ?>
            <div class="ranking-item <?php echo $is_current_user ? 'current-user' : ''; ?>">
                <div class="rank-number <?php echo $rank_number <= 3 ? 'rank-' . $rank_number : 'rank-other'; ?>">
                    <?php echo $rank_number; ?>
                </div>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($user['nickname']); ?>
                        <span class="race-badge race-<?php echo $user['race']; ?>">
                            <?php echo $race_names[$user['race']] ?? '未知'; ?>
                        </span>
                    </div>
                    <div class="user-details">
                        <span>Lv.<?php echo $user['level']; ?></span>
                        <span style="color: <?php echo $user_rank_info['color']; ?>; font-weight: bold;">
                            <?php echo $user_rank_info['display_text']; ?>
                        </span>
                        <span>金币: <?php echo $user['coins']; ?></span>
                    </div>
                </div>
                <div class="rank-score">
                    <div class="score-value">
                        <?php 
                        switch ($rank_type) {
                            case 'level': echo $user['level']; break;
                            case 'coins': echo $user['coins']; break;
                            default: echo $user['rank_score']; break;
                        }
                        ?>
                    </div>
                    <div class="score-label">
                        <?php 
                        switch ($rank_type) {
                            case 'level': echo '等级'; break;
                            case 'coins': echo '金币'; break;
                            default: echo '积分'; break;
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="last-updated">
        最后更新: <?php echo date('Y-m-d H:i:s'); ?>
    </div>
</div>
    </div>

    <script>
        function changeRankType(type) {
            window.location.href = `rankings.php?type=${type}`;
        }

        // 自动刷新排行榜（每30秒）
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>