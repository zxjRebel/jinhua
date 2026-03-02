<?php
// characters.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 获取用户金币
$stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_coins = $stmt->fetchColumn();

// 获取用户拥有的所有角色
// 修改 characters.php 中的查询语句
$stmt = $pdo->prepare("
    SELECT c.*, uc.level as char_level, uc.id as user_character_id, uc.is_selected, uc.upgrade_cost, uc.mutate_value,
           (c.base_hp + (uc.level - 1) * 20) as current_hp,
           (c.base_attack + (uc.level - 1) * 5) as current_attack,
           (c.base_defense + (uc.level - 1) * 3) as current_defense,
           (c.base_hp + uc.level * 20) as next_hp,
           (c.base_attack + uc.level * 5) as next_attack,
           (c.base_defense + uc.level * 3) as next_defense,
           (uc.mutate_value + CEILING(c.mutate_value * 0.05)) as next_mutate
    FROM user_characters uc 
    JOIN characters c ON uc.character_id = c.id 
    WHERE uc.user_id = ?
    ORDER BY uc.is_selected DESC, uc.level DESC, uc.obtained_at DESC
");
$stmt->execute([$user_id]);
$user_characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取当前出战角色
$current_character = null;
foreach ($user_characters as $character) {
    if ($character['is_selected']) {
        $current_character = $character;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 角色管理</title>
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
        .current-character {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            border: 2px solid #4ecca3;
        }
        .current-header {
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
        .characters-list {
            margin-top: 20px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 15px;
            text-align: center;
        }
        .characters-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        .character-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid transparent;
        }
        .character-card:hover {
            transform: translateY(-3px);
            border-color: #4ecca3;
            box-shadow: 0 5px 15px rgba(78, 204, 163, 0.3);
        }
        .character-card.selected {
            border-color: #4ecca3;
            background: rgba(78, 204, 163, 0.1);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .card-name {
            font-size: 16px;
            font-weight: bold;
            color: #4ecca3;
        }
        .card-level {
            background: #4ecca3;
            color: #1a1a2e;
            padding: 2px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        .card-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5px;
            font-size: 11px;
            margin-bottom: 8px;
        }
        .card-stat {
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
            padding: 4px;
            border-radius: 5px;
        }
        .card-special {
            font-size: 11px;
            color: #ccc;
            margin-bottom: 8px;
        }
        .select-btn {
            width: 100%;
            background: linear-gradient(135deg, #4ecca3, #3db393);
            border: none;
            border-radius: 6px;
            padding: 8px;
            color: #1a1a2e;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .select-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(78, 204, 163, 0.4);
        }
        .select-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .select-btn.selected {
            background: linear-gradient(135deg, #ffc107, #ff9800);
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
        .message.error {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }
        .race-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 8px;
        }
        .race-bug { background: #8bc34a; color: #1a1a2e; }
        .race-spirit { background: #9c27b0; color: white; }
        .race-ghost { background: #607d8b; color: white; }
        .race-human { background: #ff9800; color: #1a1a2e; }
        .race-god { background: #ffeb3b; color: #1a1a2e; }
        /* 保持原有的样式，添加升级相关样式 */
        .upgrade-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            position: relative;
            overflow: hidden;
        }
        .upgrade-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .upgrade-cost {
            color: #ffc107;
            font-weight: bold;
            font-size: 14px;
        }
        .upgrade-stats {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            font-size: 12px;
        }
        .stat-comparison {
            text-align: center;
        }
        .current-stat {
            color: #ccc;
        }
        .next-stat {
            color: #4ecca3;
            font-weight: bold;
        }
        .arrow {
            color: #ffc107;
            font-size: 16px;
        }
        .upgrade-btn {
            width: 100%;
            background: linear-gradient(135deg, #ffc107, #ff9800);
            border: none;
            border-radius: 8px;
            padding: 10px;
            color: #1a1a2e;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .upgrade-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
        }
        .upgrade-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .upgrade-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        .upgrade-btn.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid #1a1a2e;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .coins-info {
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
            color: #ffc107;
        }
        
        /* 升级动效 */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes levelUp {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        @keyframes glow {
            0% { box-shadow: 0 0 5px rgba(78, 204, 163, 0.5); }
            50% { box-shadow: 0 0 20px rgba(78, 204, 163, 0.8); }
            100% { box-shadow: 0 0 5px rgba(78, 204, 163, 0.5); }
        }
        
        @keyframes coinFly {
            0% { transform: translateY(0) scale(1); opacity: 1; }
            100% { transform: translateY(-50px) scale(0.5); opacity: 0; }
        }
        
        .level-up-animation {
            animation: levelUp 0.6s ease;
        }
        
        .glow-effect {
            animation: glow 1s ease;
        }
        
        .coin-fly {
            position: absolute;
            font-size: 20px;
            pointer-events: none;
            animation: coinFly 1s ease forwards;
        }
        
        /* 成功提示 */
        .success-notification {
            position: fixed;
            top: 20%;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #4ecca3, #3db393);
            color: #1a1a2e;
            padding: 20px 30px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 18px;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: slideDown 0.5s ease, fadeOut 0.5s ease 2s forwards;
        }
        
        @keyframes slideDown {
            from { top: -100px; opacity: 0; }
            to { top: 20%; opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        .stats-increase {
            color: #1a1a2e;
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">返回大厅</a>
            <h2>角色管理</h2>
            <div>金币: <span id="currentCoins"><?php echo $user_coins; ?></span></div>
        </div>

        <!-- 当前出战角色 -->
        <?php if ($current_character): ?>
        <div class="current-character" id="currentCharacter">
            <div class="current-header">
                <div class="character-name">
                    <span id="characterName"><?php echo htmlspecialchars($current_character['name']); ?></span>
                    <span class="race-badge race-<?php echo $current_character['race']; ?>">
                        <?php 
                        $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
                        echo $race_names[$current_character['race']] ?? '未知';
                        ?>
                    </span>
                </div>
                <div class="character-level" id="characterLevel">Lv.<?php echo $current_character['char_level']; ?></div>
            </div>
            <div class="character-stats">
                <div class="stat-item">
                    <div class="stat-label">生命值</div>
                    <div class="stat-value" id="currentHp"><?php echo $current_character['current_hp']; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">攻击力</div>
                    <div class="stat-value" id="currentAttack"><?php echo $current_character['current_attack']; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">防御力</div>
                    <div class="stat-value" id="currentDefense"><?php echo $current_character['current_defense']; ?></div>
                </div>
            </div>
            
           <div class="character-special">
        <div class="stat-label">异化值</div>
        <div class="stat-value" id="currentMutate"><?php echo $current_character['mutate_value']; ?></div>
    </div>
            <div class="character-special">
                <div class="special-label">特殊领域</div>
                <div class="special-value"><?php echo htmlspecialchars($current_character['special_field']); ?></div>
            </div>

            <!-- 升级区域 -->
            <div class="upgrade-section">
                <div class="upgrade-header">
                    <div style="font-weight: bold; color: #ffc107;">角色升级</div>
                    <div class="upgrade-cost">消耗: <span id="upgradeCost"><?php echo $current_character['upgrade_cost']; ?></span> 金币</div>
                </div>
                <!-- 在升级区域添加异化值对比 -->
<div class="upgrade-stats">
    <div class="stat-comparison">
        <div class="current-stat">HP: <span id="currentHpValue"><?php echo $current_character['current_hp']; ?></span></div>
        <div class="current-stat">ATK: <span id="currentAttackValue"><?php echo $current_character['current_attack']; ?></span></div>
        <div class="current-stat">DEF: <span id="currentDefenseValue"><?php echo $current_character['current_defense']; ?></span></div>
        <div class="current-stat">MUT: <span id="currentMutateValue"><?php echo $current_character['mutate_value']; ?></span></div>
    </div>
    <div class="arrow">⇒</div>
    <div class="stat-comparison">
        <div class="next-stat">HP: <span id="nextHpValue"><?php echo $current_character['next_hp']; ?></span></div>
        <div class="next-stat">ATK: <span id="nextAttackValue"><?php echo $current_character['next_attack']; ?></span></div>
        <div class="next-stat">DEF: <span id="nextDefenseValue"><?php echo $current_character['next_defense']; ?></span></div>
        <div class="next-stat">MUT: <span id="nextMutateValue"><?php echo $current_character['next_mutate']; ?></span></div>
    </div>
</div>
                <button class="upgrade-btn" 
                        id="upgradeBtn"
                        onclick="upgradeCharacter(<?php echo $current_character['user_character_id']; ?>)"
                        <?php echo $user_coins < $current_character['upgrade_cost'] ? 'disabled' : ''; ?>>
                   升级到 Lv.<?php echo $current_character['char_level'] + 1; ?>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- 角色列表 -->
        <div class="characters-list">
            <div class="section-title">我的角色库</div>
            
            <?php if (empty($user_characters)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🎮</div>
                    <div>还没有任何角色</div>
                    <div style="font-size: 12px; margin-top: 10px;">去抽奖获得更多角色吧！</div>
                </div>
            <?php else: ?>
                <div class="coins-info">当前金币: <span id="coinsDisplay"><?php echo $user_coins; ?></span></div>
                <div class="characters-grid">
                    <?php foreach ($user_characters as $character): ?>
                    <div class="character-card <?php echo $character['is_selected'] ? 'selected' : ''; ?>" id="characterCard<?php echo $character['user_character_id']; ?>">
                        <div class="card-header">
                            <div class="card-name">
                                <?php echo htmlspecialchars($character['name']); ?>
                                <span class="race-badge race-<?php echo $character['race']; ?>">
                                    <?php 
                                    $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
                                    echo $race_names[$character['race']] ?? '未知';
                                    ?>
                                </span>
                            </div>
                            <div class="card-level" id="cardLevel<?php echo $character['user_character_id']; ?>">Lv.<?php echo $character['char_level']; ?></div>
                        </div>
                        <div class="card-stats">
                            <div class="card-stat">
                                <div>HP: <span id="cardHp<?php echo $character['user_character_id']; ?>"><?php echo $character['current_hp']; ?></span></div>
                            </div>
                            <div class="card-stat">
                                <div>ATK: <span id="cardAttack<?php echo $character['user_character_id']; ?>"><?php echo $character['current_attack']; ?></span></div>
                            </div>
                            <div class="card-stat">
                                <div>DEF: <span id="cardDefense<?php echo $character['user_character_id']; ?>"><?php echo $character['current_defense']; ?></span></div>
                            </div>
                        </div>
                        
                        <!-- 角色升级区域 -->
                        <div class="upgrade-section" style="margin: 8px 0; padding: 10px;">
                            <div style="font-size: 11px; color: #ffc107; margin-bottom: 5px;">
                                升级: <span id="cardCost<?php echo $character['user_character_id']; ?>"><?php echo $character['upgrade_cost']; ?></span> 金币
                            </div>
                            <button class="upgrade-btn" 
                                    style="padding: 6px; font-size: 11px;"
                                    id="cardUpgradeBtn<?php echo $character['user_character_id']; ?>"
                                    onclick="upgradeCharacter(<?php echo $character['user_character_id']; ?>)"
                                    <?php echo $user_coins < $character['upgrade_cost'] ? 'disabled' : ''; ?>>
                                升级
                            </button>
                        </div>

                        <div class="card-special">
                            特殊: <?php echo htmlspecialchars($character['special_field']); ?>
                        </div>
                        <button class="select-btn <?php echo $character['is_selected'] ? 'selected' : ''; ?>" 
                                onclick="selectCharacter(<?php echo $character['user_character_id']; ?>)" 
                                <?php echo $character['is_selected'] ? 'disabled' : ''; ?>>
                            <?php echo $character['is_selected'] ? '当前出战' : '选择出战'; ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="message" class="message"></div>
    </div>

    <script>
        async function selectCharacter(userCharacterId) {
            const messageEl = document.getElementById('message');
            messageEl.textContent = '';
            messageEl.className = 'message';

            try {
                const response = await fetch('api/select_character.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_character_id: userCharacterId })
                });

                const result = await response.json();

                if (result.success) {
                    messageEl.textContent = '切换角色成功！';
                    messageEl.className = 'message success';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    messageEl.textContent = result.message || '切换失败';
                    messageEl.className = 'message error';
                }
            } catch (error) {
                
                messageEl.textContent = '网络错误，请重试';
                messageEl.className = 'message error';
                console.error('Error:', error);
            }
        }

        async function upgradeCharacter(userCharacterId) {
    const upgradeBtn = document.getElementById(`cardUpgradeBtn${userCharacterId}`) || document.getElementById('upgradeBtn');
    const messageEl = document.getElementById('message');
    
    // 设置加载状态
    upgradeBtn.classList.add('loading');
    upgradeBtn.disabled = true;
    messageEl.textContent = '升级中...';
    messageEl.className = 'message';

    try {
        console.log('发送升级请求，角色ID:', userCharacterId);
        
        const response = await fetch('api/upgrade_character.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user_character_id: userCharacterId })
        });

        console.log('收到响应，状态:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP错误: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('解析后的结果:', result);

        if (result.success) {
            console.log('升级成功，开始更新UI');
            
            // 显示成功消息
            messageEl.textContent = `升级成功！当前等级: Lv.${result.new_level}`;
            messageEl.className = 'message success';
            
            // 显示成功通知
            showSuccessNotification(result);
            
            // 更新UI
            updateCharacterUI(userCharacterId, result);
            
        } else {
            console.log('升级失败:', result.message);
            // 服务器返回的失败
            messageEl.textContent = result.message || '升级失败';
            messageEl.className = 'message error';
            
            // 恢复按钮状态
            upgradeBtn.classList.remove('loading');
            upgradeBtn.disabled = false;
        }
        
    } catch (error) {
        console.error('升级过程出现异常:', error);
        
        // 只在真正的网络错误或解析错误时显示网络错误
        messageEl.textContent = '网络错误，请重试';
        messageEl.className = 'message error';
        
        // 恢复按钮状态
        upgradeBtn.classList.remove('loading');
        upgradeBtn.disabled = false;
    }
}

        function showSuccessNotification(result) {
            const notification = document.createElement('div');
            notification.className = 'success-notification';
            notification.innerHTML = `
                <div>🎉 升级成功！</div>
                <div class="stats-increase">
                    等级: Lv.${result.new_level - 1} → Lv.${result.new_level}
                </div>
                <div class="stats-increase">
                    HP: +20 | ATK: +5 | DEF: +3
                </div>
            `;
            document.body.appendChild(notification);
            
            // 3秒后移除通知
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

       function updateCharacterUI(userCharacterId, result) {
    try {
        console.log('开始更新UI，角色ID:', userCharacterId);
        
        // 更新金币显示
        const currentCoinsEl = document.getElementById('currentCoins');
        const coinsDisplayEl = document.getElementById('coinsDisplay');
        
        if (currentCoinsEl) currentCoinsEl.textContent = result.new_coins;
        if (coinsDisplayEl) coinsDisplayEl.textContent = result.new_coins;
        
        // 如果是当前出战角色，更新主区域
        if (document.getElementById('characterLevel')) {
            console.log('更新主区域');
            
            // 更新等级
            const levelEl = document.getElementById('characterLevel');
            if (levelEl) {
                levelEl.textContent = `Lv.${result.new_level}`;
                levelEl.classList.add('level-up-animation');
            }
            
            // 更新当前属性
            const hpEl = document.getElementById('currentHp');
            const attackEl = document.getElementById('currentAttack');
            const defenseEl = document.getElementById('currentDefense');
            const mutateEl = document.getElementById('currentMutate');
            
            if (hpEl) hpEl.textContent = result.new_hp;
            if (attackEl) attackEl.textContent = result.new_attack;
            if (defenseEl) defenseEl.textContent = result.new_defense;
            if (mutateEl) mutateEl.textContent = result.new_mutate;
            
            // 更新升级对比区域
            const currentHpValueEl = document.getElementById('currentHpValue');
            const currentAttackValueEl = document.getElementById('currentAttackValue');
            const currentDefenseValueEl = document.getElementById('currentDefenseValue');
            const currentMutateValueEl = document.getElementById('currentMutateValue');
            
            if (currentHpValueEl) currentHpValueEl.textContent = result.new_hp;
            if (currentAttackValueEl) currentAttackValueEl.textContent = result.new_attack;
            if (currentDefenseValueEl) currentDefenseValueEl.textContent = result.new_defense;
            if (currentMutateValueEl) currentMutateValueEl.textContent = result.new_mutate;
            
            // 更新下一级属性
            if (result.next_level_info) {
                const nextHpValueEl = document.getElementById('nextHpValue');
                const nextAttackValueEl = document.getElementById('nextAttackValue');
                const nextDefenseValueEl = document.getElementById('nextDefenseValue');
                const nextMutateValueEl = document.getElementById('nextMutateValue');
                const upgradeCostEl = document.getElementById('upgradeCost');
                
                if (nextHpValueEl) nextHpValueEl.textContent = result.next_level_info.hp;
                if (nextAttackValueEl) nextAttackValueEl.textContent = result.next_level_info.attack;
                if (nextDefenseValueEl) nextDefenseValueEl.textContent = result.next_level_info.defense;
                if (nextMutateValueEl) nextMutateValueEl.textContent = result.next_level_info.mutate;
                if (upgradeCostEl) upgradeCostEl.textContent = result.next_level_info.upgrade_cost;
            }
            
            // 更新升级按钮
            const upgradeBtn = document.getElementById('upgradeBtn');
            if (upgradeBtn) {
                upgradeBtn.textContent = `升级到 Lv.${result.new_level + 1}`;
                upgradeBtn.classList.remove('loading');
                
                // 检查金币是否足够下一次升级
                if (result.new_coins < result.new_upgrade_cost) {
                    upgradeBtn.disabled = true;
                } else {
                    upgradeBtn.disabled = false;
                }
            }
            
            // 添加发光效果
            const currentCharacterEl = document.getElementById('currentCharacter');
            if (currentCharacterEl) {
                currentCharacterEl.classList.add('glow-effect');
                setTimeout(() => {
                    currentCharacterEl.classList.remove('glow-effect');
                }, 1000);
            }
        }
        
        // 更新卡片区域
        const card = document.getElementById(`characterCard${userCharacterId}`);
        if (card) {
            console.log('更新卡片区域');
            
            // 更新卡片等级
            const cardLevelEl = document.getElementById(`cardLevel${userCharacterId}`);
            if (cardLevelEl) {
                cardLevelEl.textContent = `Lv.${result.new_level}`;
                cardLevelEl.classList.add('level-up-animation');
            }
            
            // 更新卡片属性
            const cardHpEl = document.getElementById(`cardHp${userCharacterId}`);
            const cardAttackEl = document.getElementById(`cardAttack${userCharacterId}`);
            const cardDefenseEl = document.getElementById(`cardDefense${userCharacterId}`);
            const cardMutateEl = document.getElementById(`cardMutate${userCharacterId}`);
            const cardCostEl = document.getElementById(`cardCost${userCharacterId}`);
            
            if (cardHpEl) cardHpEl.textContent = result.new_hp;
            if (cardAttackEl) cardAttackEl.textContent = result.new_attack;
            if (cardDefenseEl) cardDefenseEl.textContent = result.new_defense;
            if (cardMutateEl) cardMutateEl.textContent = result.new_mutate;
            if (cardCostEl) cardCostEl.textContent = result.new_upgrade_cost;
            
            // 更新卡片升级按钮
            const cardUpgradeBtn = document.getElementById(`cardUpgradeBtn${userCharacterId}`);
            if (cardUpgradeBtn) {
                cardUpgradeBtn.classList.remove('loading');
                
                // 检查金币是否足够下一次升级
                if (result.new_coins < result.new_upgrade_cost) {
                    cardUpgradeBtn.disabled = true;
                } else {
                    cardUpgradeBtn.disabled = false;
                }
            }
            
            // 添加卡片发光效果
            card.classList.add('glow-effect');
            setTimeout(() => {
                card.classList.remove('glow-effect');
            }, 1000);
        }
        
        console.log('UI更新完成');
        
    } catch (error) {
        console.error('更新UI时出现错误:', error);
        // 这里不显示错误消息，因为升级本身是成功的
    }
    
    // 无论UI更新是否成功，都移除加载状态
    setTimeout(() => {
        const upgradeBtn = document.getElementById('upgradeBtn');
        if (upgradeBtn) {
            upgradeBtn.classList.remove('loading');
        }
        const cardUpgradeBtn = document.getElementById(`cardUpgradeBtn${userCharacterId}`);
        if (cardUpgradeBtn) {
            cardUpgradeBtn.classList.remove('loading');
        }
    }, 500);
}

        // 金币飞走动画（可选）
        function createCoinAnimation(button) {
            const rect = button.getBoundingClientRect();
            for (let i = 0; i < 3; i++) {
                const coin = document.createElement('div');
                coin.className = 'coin-fly';
                coin.textContent = '💰';
                coin.style.left = (rect.left + rect.width / 2) + 'px';
                coin.style.top = (rect.top + rect.height / 2) + 'px';
                document.body.appendChild(coin);
                
                setTimeout(() => {
                    coin.remove();
                }, 1000);
            }
        }
    </script>
</body>
</html>