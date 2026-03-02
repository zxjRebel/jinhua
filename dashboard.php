<?php
// dashboard.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 确保数据库字段存在 (修复白屏问题)
function ensureMutateValueFieldsExist($pdo) {
    try {
        // 检查 characters 表
        $stmt = $pdo->query("DESCRIBE characters mutate_value");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE characters ADD COLUMN mutate_value INT DEFAULT 10 COMMENT '基础异化值'");
        }
        
        // 检查 user_characters 表
        $stmt = $pdo->query("DESCRIBE user_characters mutate_value");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE user_characters ADD COLUMN mutate_value INT DEFAULT 0 COMMENT '异化值' AFTER upgrade_cost");
        }
    } catch (PDOException $e) {
        // 忽略错误，避免中断
    }
}
ensureMutateValueFieldsExist($pdo);

// 检查用户是否被封禁
$stmt = $pdo->prepare("SELECT is_banned, ban_reason, ban_expires_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$ban_info = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ban_info && $ban_info['is_banned']) {
    // 检查封禁是否已到期
    if ($ban_info['ban_expires_at'] && strtotime($ban_info['ban_expires_at']) < time()) {
        // 封禁已到期，自动解封
        $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL, ban_expires_at = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
    } else {
        // 用户仍在封禁期内，重定向到登录页面
        header('Location: index.php?banned=1&reason=' . urlencode($ban_info['ban_reason']));
        exit;
    }
}

// 获取用户信息
$stmt = $pdo->prepare("SELECT level, exp, rank_score, coins, nickname, race FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_info) {
    header('Location: index.php');
    exit;
}

// 获取段位信息
$rank_info = get_rank_info($user_info['rank_score']);
$next_rank_info = get_next_rank_info($user_info['rank_score']);

// 获取用户当前选择的角色 - 修复这里
$current_character = null;
$player_stats = null;
$stmt = $pdo->prepare("
    SELECT c.*, uc.level as char_level, uc.mutate_value
    FROM user_characters uc 
    JOIN characters c ON uc.character_id = c.id 
    WHERE uc.user_id = ? AND uc.is_selected = 1
");
$stmt->execute([$user_id]);
$current_character = $stmt->fetch(PDO::FETCH_ASSOC);

// 只有在找到角色时才计算属性
if ($current_character) {
    // 计算玩家角色实际属性（基于等级）
    $player_stats = calculate_character_stats($current_character);
}

// 检查用户是否选择了种族
$has_race = !empty($user_info['race']);

// 计算角色属性的函数
function calculate_character_stats($character) {
    $level = $character['char_level'];
    return [
        'hp' => $character['base_hp'] + ($level - 1) * 20,
        'attack' => $character['base_attack'] + ($level - 1) * 5,
        'defense' => $character['base_defense'] + ($level - 1) * 3,
        'mutate_value' => $character['mutate_value']
    ];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 主大厅</title>
    <style>
        /* 原有的样式保持不变 */
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
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        .header h1 {
            color: #4ecca3;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        .user-info {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        .info-item {
            text-align: center;
        }
        .info-label {
            font-size: 12px;
            color: #ccc;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            font-weight: bold;
            color: #4ecca3;
        }
        .main-content {
            display: none;
        }
        .main-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* 段位显示样式 */
        .rank-display {
            background: <?php echo $rank_info['bg_color']; ?>;
            border: 2px solid <?php echo $rank_info['color']; ?>;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            color: <?php echo $rank_info['text_color']; ?>;
            position: relative;
            overflow: hidden;
        }
        .rank-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        .rank-score {
            font-size: 16px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .stars-container {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-bottom: 10px;
        }
        .star {
            font-size: 18px;
            color: <?php echo $rank_info['color']; ?>;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
        }
        .star.empty {
            opacity: 0.3;
        }
        .progress-container {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            height: 8px;
            margin: 10px 0;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: <?php echo $rank_info['color']; ?>;
            border-radius: 10px;
            transition: width 0.5s ease;
            width: <?php echo $rank_info['progress_percent']; ?>%;
        }
        .progress-text {
            font-size: 12px;
            margin-top: 5px;
            opacity: 0.8;
        }
        .next-rank-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 8px;
            margin-top: 10px;
            font-size: 12px;
        }
        .next-rank-name {
            color: <?php echo $next_rank_info ? $next_rank_info['color'] : '#4ecca3'; ?>;
            font-weight: bold;
        }
        .rank-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
            background: <?php echo $rank_info['color']; ?>;
            color: <?php echo $rank_info['text_color']; ?>;
        }

        /* 种族选择样式 */
        .race-selection {
            text-align: center;
        }
        .race-title {
            margin-bottom: 20px;
            color: #4ecca3;
        }
        .races-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .race-card {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .race-card:hover {
            transform: translateY(-5px);
            border-color: #4ecca3;
            box-shadow: 0 5px 15px rgba(78, 204, 163, 0.3);
        }
        .race-card.selected {
            border-color: #4ecca3;
            background: rgba(78, 204, 163, 0.1);
        }
        .race-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .race-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #4ecca3;
        }
        .race-desc {
            font-size: 12px;
            color: #ccc;
            line-height: 1.4;
        }

        /* 游戏主界面样式 */
        .current-character {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            border: 2px solid #4ecca3;
        }
        .character-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .character-name {
            font-size: 20px;
            font-weight: bold;
            color: #4ecca3;
        }
        .character-level {
            background: #4ecca3;
            color: #1a1a2e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .character-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .stat-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
            padding: 8px;
            border-radius: 8px;
        }
        .stat-label {
            font-size: 10px;
            color: #ccc;
            margin-bottom: 3px;
        }
        .stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #fff;
        }
        .character-special {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .special-label {
            font-size: 12px;
            color: #ccc;
            margin-bottom: 5px;
        }
        .special-value {
            font-size: 14px;
            color: #4ecca3;
            font-weight: bold;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        .action-btn {
            background: linear-gradient(135deg, #4ecca3, #3db393);
            border: none;
            border-radius: 10px;
            padding: 18px;
            color: #1a1a2e;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(78, 204, 163, 0.4);
        }
        .action-btn.secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
        }
        .action-btn.secondary:hover {
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }
        .logout-btn {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin-top: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        .logout-btn:hover {
            background: rgba(255, 107, 107, 0.3);
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
        .message.error {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }
        
        /* 没有角色的提示样式 */
        .no-character {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .no-character-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .no-character-text {
            color: #ccc;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>我的进化之路</h1>
            <div class="user-info">
                <div class="info-item">
                    <div class="info-label">玩家</div>
                    <div class="info-value"><?php echo htmlspecialchars($user_info['nickname']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">等级</div>
                    <div class="info-value">Lv.<?php echo $user_info['level']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">金币</div>
                    <div class="info-value"><?php echo $user_info['coins']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">积分</div>
                    <div class="info-value"><?php echo $user_info['rank_score']; ?></div>
                </div>
            </div>
        </div>

        <!-- 段位显示 -->
        <?php if ($has_race): ?>
        <div class="rank-display">
            <div class="rank-name">
                <?php echo $rank_info['name']; ?>段位
                <span class="rank-badge"><?php echo $rank_info['stars']; ?>星</span>
            </div>
            <div class="rank-score">积分: <?php echo $rank_info['score']; ?></div>
            
            <!-- 星级显示 -->
            <div class="stars-container">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="star <?php echo $i > $rank_info['stars'] ? 'empty' : ''; ?>">
                        ★
                    </div>
                <?php endfor; ?>
            </div>
            
            <!-- 进度条 -->
            <div class="progress-container">
                <div class="progress-bar"></div>
            </div>
            <div class="progress-text">
                进度: <?php echo round($rank_info['progress_percent'], 1); ?>%
                (<?php echo $rank_info['current_progress']; ?>/<?php echo $rank_info['max_progress']; ?>)
            </div>
            
            <!-- 下一段位信息 -->
            <?php if ($next_rank_info): ?>
            <div class="next-rank-info">
                下一段位: <span class="next-rank-name"><?php echo $next_rank_info['name']; ?></span>
                | 还需积分: <?php echo $next_rank_info['needed_score']; ?>
            </div>
            <?php else: ?>
            <div class="next-rank-info" style="color: #4ecca3;">
                 # 已达到最高段位
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 种族选择界面（新用户显示） -->
        <div id="raceSelection" class="main-content <?php echo !$has_race ? 'active' : ''; ?>">
            <div class="race-selection">
                <h2 class="race-title">选择你的进化种族</h2>
                <div class="races-grid">
                    <div class="race-card" onclick="selectRace('bug')">
                        <div class="race-icon">🐛</div>
                        <div class="race-name">虫族</div>
                        <div class="race-desc">高攻击力，繁殖迅速，擅长毒系和速攻</div>
                    </div>
                    <div class="race-card" onclick="selectRace('spirit')">
                        <div class="race-icon">✨</div>
                        <div class="race-name">灵族</div>
                        <div class="race-desc">元素掌控，灵魂之力，平衡发展的法师</div>
                    </div>
                    <div class="race-card" onclick="selectRace('ghost')">
                        <div class="race-icon">👻</div>
                        <div class="race-name">鬼族</div>
                        <div class="race-desc">高异化值，诅咒大师，拥有特殊效果</div>
                    </div>
                    <div class="race-card" onclick="selectRace('human')">
                        <div class="race-icon">👨‍🚀</div>
                        <div class="race-name">人族</div>
                        <div class="race-desc">均衡发展，潜力巨大，多职业选择</div>
                    </div>
                    <div class="race-card" onclick="selectRace('god')">
                        <div class="race-icon">🌠</div>
                        <div class="race-name">神族</div>
                        <div class="race-desc">全面强大，基础属性高，天生的王者</div>
                    </div>
                </div>
                <button class="action-btn" onclick="confirmRace()" id="confirmRaceBtn" disabled>
                    确认选择并开始进化
                </button>
                <div id="raceMessage" class="message"></div>
            </div>
        </div>

        <!-- 游戏主界面（已选择种族的用户显示） -->
        <div id="gameMain" class="main-content <?php echo $has_race ? 'active' : ''; ?>">
            <?php if ($current_character && $player_stats): ?>
            <div class="current-character">
                <div class="character-header">
                    <div class="character-name"><?php echo htmlspecialchars($current_character['name']); ?></div>
                    <div class="character-level">Lv.<?php echo $current_character['char_level']; ?></div>
                </div>
                <div class="character-stats">
                    <div class="stat-item">
                        <div class="stat-label">生命值</div>
                        <div class="stat-value">
                            <?php echo $player_stats['hp']; ?>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">攻击力</div>
                        <div class="stat-value">
                            <?php echo $player_stats['attack']; ?>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">防御力</div>
                        <div class="stat-value">
                            <?php echo $player_stats['defense']; ?>
                        </div>
                    </div>
                    <!-- 添加异化值显示 -->
                    <div class="stat-item">
                        <div class="stat-label">异化值</div>
                        <div class="stat-value">
                            <?php echo $player_stats['mutate_value']; ?>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">特殊领域</div>
                        <div class="stat-value"><?php echo htmlspecialchars($current_character['special_field']); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">种族</div>
                        <div class="stat-value">
                            <?php 
                            $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
                            echo $race_names[$current_character['race']] ?? '未知';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- 没有角色的提示 -->
            <div class="no-character">
                <div class="no-character-icon">🎮</div>
                <div class="no-character-text">您还没有角色</div>
                <div style="font-size: 12px; color: #888; margin-top: 10px;">请去抽奖获得您的第一个角色</div>
                <a href="shop.php" class="action-btn" style="margin-top: 15px;">前往抽奖</a>
            </div>
            <?php endif; ?>

            <div class="actions-grid">
                <a href="signin.php" class="action-btn">每日签到</a>
                <a href="battle.php" class="action-btn">开始挑战</a>
                
                <a href="characters.php" class="action-btn">我的角色</a>
                <a href="shop.php" class="action-btn">角色抽奖</a>
                <a href="rankings.php" class="action-btn">排行榜</a>
                <a href="social.php" class="action-btn">社交中心</a>
            </div>
            
            <div class="logout-btn" onclick="logout()">退出登录</div>
        </div>
    </div>

    <script>
        let selectedRace = null;

        function selectRace(race) {
            selectedRace = race;
            
            // 更新UI显示选中的种族
            document.querySelectorAll('.race-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // 启用确认按钮
            document.getElementById('confirmRaceBtn').disabled = false;
        }

        async function confirmRace() {
    if (!selectedRace) {
        showRaceMessage('请先选择一个种族', 'error');
        return;
    }

    const confirmBtn = document.getElementById('confirmRaceBtn');
    const messageEl = document.getElementById('raceMessage');
    const originalText = confirmBtn.innerHTML;
    
    confirmBtn.innerHTML = '进化中...';
    confirmBtn.disabled = true;
    messageEl.textContent = '';
    messageEl.className = 'message';

    try {
        console.log('发送种族选择请求:', selectedRace);
        
        const response = await fetch('api/select_race.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ race: selectedRace })
        });

        console.log('收到响应，状态:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP错误: ${response.status}`);
        }

        const result = await response.json();
        console.log('解析结果:', result);

        if (result.success) {
            showRaceMessage('种族选择成功！获得初始角色：' + result.character_name, 'success');
            setTimeout(() => {
                location.reload(); // 刷新页面显示游戏主界面
            }, 2000);
        } else {
            showRaceMessage(result.message || '选择失败，请重试', 'error');
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    } catch (error) {
        console.error('选择种族错误:', error);
        showRaceMessage('网络错误，请重试: ' + error.message, 'error');
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    }
}

        function showRaceMessage(message, type) {
            const messageEl = document.getElementById('raceMessage');
            messageEl.textContent = message;
            messageEl.className = `message ${type}`;
        }

        function logout() {
            if (confirm('确定要退出登录吗？')) {
                window.location.href = 'api/logout.php';
            }
        }
    </script>

    <!-- 这里添加公告弹窗的代码 -->
    <!-- 系统公告弹窗 -->
<div class="announcement-modal" id="announcementModal">
    <div class="announcement-content">
        <div class="announcement-header">
            <h3>系统公告</h3>
            <button class="close-btn" onclick="closeAnnouncement()">×</button>
        </div>
        <div class="announcement-body" id="announcementBody">
            <!-- 公告内容将通过JavaScript动态加载 -->
        </div>
        <div class="announcement-footer">
            <label class="dont-show-again">
                <input type="checkbox" id="dontShowAgain"> 今日不再显示
            </label>
            <button class="confirm-btn" onclick="closeAnnouncement()">我知道了</button>
        </div>
    </div>
</div>

<script>
// 公告功能
let announcements = [];

// 加载公告
async function loadAnnouncements() {
    try {
        const response = await fetch('api/get_announcements.php');
        const result = await response.json();
        
        if (result.success && result.announcements.length > 0) {
            announcements = result.announcements;
            showAnnouncements();
        }
    } catch (error) {
        console.error('加载公告失败:', error);
    }
}

// 显示公告
function showAnnouncements() {
    // 检查是否今日不再显示
    const dontShow = localStorage.getItem('dontShowAnnouncements');
    const today = new Date().toDateString();
    
    if (dontShow === today) {
        return;
    }
    
    const modal = document.getElementById('announcementModal');
    const body = document.getElementById('announcementBody');
    
    let html = '';
    announcements.forEach((announcement, index) => {
        const typeClass = getTypeClass(announcement.type);
        const endTime = announcement.end_time ? new Date(announcement.end_time).toLocaleDateString() : '长期有效';
        
        html += `
            <div class="announcement-item ${typeClass}">
                <div class="announcement-title">
                    <span class="type-badge">${getTypeBadge(announcement.type)}</span>
                    ${announcement.title}
                </div>
                <div class="announcement-text">${announcement.content}</div>
                <div class="announcement-meta">
                    有效期至: ${endTime}
                </div>
                ${index < announcements.length - 1 ? '<hr>' : ''}
            </div>
        `;
    });
    
    body.innerHTML = html;
    modal.style.display = 'flex';
    
    // 添加动画
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
}

// 获取类型样式
function getTypeClass(type) {
    const classes = {
        'important': 'announcement-important',
        'warning': 'announcement-warning',
        'update': 'announcement-update',
        'info': 'announcement-info'
    };
    return classes[type] || 'announcement-info';
}

// 获取类型徽章
function getTypeBadge(type) {
    const badges = {
        'important': '重要',
        'warning': '注意',
        'update': '更新',
        'info': '公告'
    };
    return badges[type] || '公告';
}

// 关闭公告
function closeAnnouncement() {
    const modal = document.getElementById('announcementModal');
    const dontShowCheckbox = document.getElementById('dontShowAgain');
    
    if (dontShowCheckbox.checked) {
        const today = new Date().toDateString();
        localStorage.setItem('dontShowAnnouncements', today);
    }
    
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

// 页面加载完成后显示公告
document.addEventListener('DOMContentLoaded', function() {
    // 延迟1秒显示公告，让页面先加载完成
    setTimeout(loadAnnouncements, 1000);
});

// 点击外部关闭
document.getElementById('announcementModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAnnouncement();
    }
});
</script>

<style>
/* 公告弹窗样式 */
.announcement-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    justify-content: center;
    align-items: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.announcement-modal.active {
    opacity: 1;
}

.announcement-content {
    background: linear-gradient(135deg, #1a1a2e, #16213e);
    border: 2px solid #4ecca3;
    border-radius: 15px;
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow: hidden;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.announcement-modal.active .announcement-content {
    transform: scale(1);
}

.announcement-header {
    background: linear-gradient(135deg, #4ecca3, #3db393);
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.announcement-header h3 {
    margin: 0;
    color: #1a1a2e;
    font-size: 18px;
    font-weight: bold;
}

.close-btn {
    background: none;
    border: none;
    color: #1a1a2e;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s ease;
}

.close-btn:hover {
    background: rgba(26, 26, 46, 0.2);
}

.announcement-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.announcement-item {
    margin-bottom: 15px;
}

.announcement-item:last-child {
    margin-bottom: 0;
}

.announcement-title {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.type-badge {
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: bold;
}

.announcement-text {
    font-size: 14px;
    line-height: 1.5;
    color: #e0e0e0;
    margin-bottom: 8px;
}

.announcement-meta {
    font-size: 12px;
    color: #888;
    text-align: right;
}

.announcement-important .type-badge {
    background: #ff6b6b;
    color: white;
}

.announcement-warning .type-badge {
    background: #ffc107;
    color: #1a1a2e;
}

.announcement-update .type-badge {
    background: #4ecca3;
    color: #1a1a2e;
}

.announcement-info .type-badge {
    background: #6c757d;
    color: white;
}

.announcement-footer {
    padding: 15px 20px;
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.dont-show-again {
    font-size: 12px;
    color: #ccc;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
}

.confirm-btn {
    background: #4ecca3;
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    color: #1a1a2e;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.confirm-btn:hover {
    background: #3db393;
    transform: translateY(-2px);
}

/* 滚动条样式 */
.announcement-body::-webkit-scrollbar {
    width: 6px;
}

.announcement-body::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.announcement-body::-webkit-scrollbar-thumb {
    background: #4ecca3;
    border-radius: 3px;
}

.announcement-body::-webkit-scrollbar-thumb:hover {
    background: #3db393;
}

hr {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin: 15px 0;
}
</style>
    <!-- 将之前提供的公告弹窗HTML、CSS和JavaScript代码放在这里 -->
    <?php include 'bottom_nav.php'; ?>
</body>
</html>