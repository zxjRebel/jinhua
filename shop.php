<?php
// shop.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 获取用户金币和种族
$stmt = $pdo->prepare("SELECT coins, race FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_coins = $user_data['coins'];
$user_race = $user_data['race'];

// 获取用户已拥有的角色ID（用于判断重复）
$stmt = $pdo->prepare("SELECT character_id FROM user_characters WHERE user_id = ?");
$stmt->execute([$user_id]);
$owned_character_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 抽奖配置
$gacha_config = [
    'single_cost' => 100,  // 单抽消耗
    'multi_cost' => 900,   // 十连消耗（九折）
    'guaranteed_rarity' => 5 // 十连保底次数
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 角色抽奖</title>
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
        .shop-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            text-align: center;
        }
        .coins-display {
            font-size: 24px;
            font-weight: bold;
            color: #ffc107;
            margin-bottom: 10px;
        }
        .race-info {
            color: #4ecca3;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .gacha-options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        .gacha-btn {
            background: linear-gradient(135deg, #9c27b0, #673ab7);
            border: none;
            border-radius: 12px;
            padding: 20px;
            color: white;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .gacha-btn.multi {
            background: linear-gradient(135deg, #ff9800, #ff5722);
        }
        .gacha-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .gacha-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .gacha-cost {
            position: absolute;
            top: 10px;
            right: 15px;
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 14px;
            color: #ffc107;
        }
        .gacha-desc {
            font-size: 12px;
            color: #ccc;
            margin-top: 5px;
        }
        .results-section {
            display: none;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }
        .results-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .results-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 15px;
        }
        .results-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .result-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            animation: cardReveal 0.5s ease;
            position: relative;
        }
        @keyframes cardReveal {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }
        .result-card.new {
            border: 2px solid #4ecca3;
            background: rgba(78, 204, 163, 0.1);
        }
        .result-card.duplicate {
            border: 2px solid #ffc107;
            background: rgba(255, 193, 7, 0.1);
        }
        .character-name {
            font-size: 14px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 5px;
        }
        .character-race {
            font-size: 11px;
            color: #ccc;
            margin-bottom: 5px;
        }
        .character-stats {
            font-size: 10px;
            color: #ccc;
        }
        .result-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #4ecca3;
            color: #1a1a2e;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: bold;
        }
        .result-badge.duplicate {
            background: #ffc107;
        }
        .summary {
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }
        .new-count {
            color: #4ecca3;
            font-weight: bold;
        }
        .duplicate-count {
            color: #ffc107;
            font-weight: bold;
        }
        .continue-btn {
            width: 100%;
            background: linear-gradient(135deg, #4ecca3, #3db393);
            border: none;
            border-radius: 10px;
            padding: 15px;
            color: #1a1a2e;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        .continue-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(78, 204, 163, 0.4);
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .loading::after {
            content: '';
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .message {
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px;
            font-size: 14px;
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
            <a href="dashboard.php" class="back-btn">返回大厅</a>
            <h2>角色抽奖</h2>
            <div>种族: 
                <?php 
                $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
                echo $race_names[$user_race] ?? '未选择';
                ?>
            </div>
        </div>

        <div class="shop-info">
            <div class="coins-display">
                💰 <span id="currentCoins"><?php echo $user_coins; ?></span> 金币
            </div>
            <div class="race-info">
                只能抽取 <?php echo $race_names[$user_race] ?? '未知'; ?> 种族的角色
            </div>
            <div style="font-size: 14px; color: #ccc;">
                每次抽奖随机获得同种族角色，重复角色将获得金币补偿
            </div>
        </div>

        <div class="gacha-options">
            <button class="gacha-btn" onclick="gachaDraw(1)" id="singleGacha" 
                    <?php echo $user_coins < $gacha_config['single_cost'] ? 'disabled' : ''; ?>>
                单次抽奖
                <span class="gacha-cost"><?php echo $gacha_config['single_cost']; ?> 金币</span>
                <div class="gacha-desc">随机获得1个角色</div>
            </button>
            
            <button class="gacha-btn multi" onclick="gachaDraw(10)" id="multiGacha"
                    <?php echo $user_coins < $gacha_config['multi_cost'] ? 'disabled' : ''; ?>>
                十连抽奖
                <span class="gacha-cost"><?php echo $gacha_config['multi_cost']; ?> 金币</span>
                <div class="gacha-desc">随机获得10个角色（九折优惠）</div>
                <div class="gacha-desc">★ 保底获得高品质角色 ★</div>
            </button>
        </div>

        <div id="message" class="message"></div>

        <!-- 抽奖结果区域 -->
        <div class="results-section" id="resultsSection">
            <div class="results-title" id="resultsTitle">抽奖结果</div>
            <div class="results-grid" id="resultsGrid"></div>
            <div class="summary" id="resultsSummary"></div>
            <button class="continue-btn" onclick="closeResults()">继续抽奖</button>
        </div>
    </div>

    <script>
        async function gachaDraw(count) {
            const singleBtn = document.getElementById('singleGacha');
            const multiBtn = document.getElementById('multiGacha');
            const messageEl = document.getElementById('message');
            
            // 设置加载状态
            singleBtn.classList.add('loading');
            multiBtn.classList.add('loading');
            messageEl.textContent = '抽奖中...';
            messageEl.className = 'message';

            try {
                const response = await fetch('api/gacha_draw.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ count: count })
                });

                const result = await response.json();

                if (result.success) {
                    // 更新金币显示
                    document.getElementById('currentCoins').textContent = result.new_coins;
                    
                    // 显示抽奖结果
                    showGachaResults(result.results, count);
                    
                    messageEl.textContent = '';
                    
                    // 更新按钮状态
                    updateGachaButtons(result.new_coins);
                    
                } else {
                    messageEl.textContent = result.message || '抽奖失败';
                    messageEl.className = 'message error';
                    singleBtn.classList.remove('loading');
                    multiBtn.classList.remove('loading');
                }
            } catch (error) {
                messageEl.textContent = '网络错误，请重试';
                messageEl.className = 'message error';
                singleBtn.classList.remove('loading');
                multiBtn.classList.remove('loading');
                console.error('Error:', error);
            }
        }

        function showGachaResults(results, count) {
            const resultsSection = document.getElementById('resultsSection');
            const resultsTitle = document.getElementById('resultsTitle');
            const resultsGrid = document.getElementById('resultsGrid');
            const resultsSummary = document.getElementById('resultsSummary');
            
            // 清空之前的结果
            resultsGrid.innerHTML = '';
            
            // 统计结果
            let newCount = 0;
            let duplicateCount = 0;
            let totalCompensation = 0;
            
            // 显示结果卡片
            results.forEach((character, index) => {
                setTimeout(() => {
                    const card = document.createElement('div');
                    card.className = `result-card ${character.is_new ? 'new' : 'duplicate'}`;
                    
                    card.innerHTML = `
                        ${character.is_new ? '<div class="result-badge">NEW</div>' : '<div class="result-badge duplicate">重复</div>'}
                        <div class="character-name">${character.name}</div>
                        <div class="character-race">${character.race_name}</div>
                        <div class="character-stats">
                            HP:${character.base_hp} ATK:${character.base_attack} DEF:${character.base_defense}
                        </div>
                        ${!character.is_new ? `<div style="color: #ffc107; font-size: 10px; margin-top: 5px;">+${character.compensation}金币</div>` : ''}
                    `;
                    
                    resultsGrid.appendChild(card);
                    
                    // 统计
                    if (character.is_new) {
                        newCount++;
                    } else {
                        duplicateCount++;
                        totalCompensation += character.compensation;
                    }
                    
                    // 如果是最后一个，显示总结
                    if (index === results.length - 1) {
                        resultsSummary.innerHTML = `
                            <div>获得新角色: <span class="new-count">${newCount}</span> 个</div>
                            <div>重复角色: <span class="duplicate-count">${duplicateCount}</span> 个</div>
                            ${totalCompensation > 0 ? `<div>重复补偿: <span style="color: #ffc107;">+${totalCompensation}</span> 金币</div>` : ''}
                        `;
                    }
                }, index * 100); // 延迟显示，创造逐个出现的效果
            });
            
            resultsTitle.textContent = count === 1 ? '抽奖结果' : '十连抽奖结果';
            resultsSection.classList.add('active');
            
            // 移除加载状态
            document.getElementById('singleGacha').classList.remove('loading');
            document.getElementById('multiGacha').classList.remove('loading');
        }

        function closeResults() {
            const resultsSection = document.getElementById('resultsSection');
            resultsSection.classList.remove('active');
        }

        function updateGachaButtons(newCoins) {
            const singleCost = 100;
            const multiCost = 900;
            
            const singleBtn = document.getElementById('singleGacha');
            const multiBtn = document.getElementById('multiGacha');
            
            singleBtn.disabled = newCoins < singleCost;
            multiBtn.disabled = newCoins < multiCost;
        }

        // 初始按钮状态
        updateGachaButtons(<?php echo $user_coins; ?>);
    </script>
</body>
</html>