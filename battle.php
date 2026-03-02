<?php
// battle.php
include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];


// 获取玩家当前出战角色
$stmt = $pdo->prepare("
    SELECT c.*, uc.level as char_level, uc.mutate_value, u.rank_score 
    FROM user_characters uc 
    JOIN characters c ON uc.character_id = c.id 
    JOIN users u ON uc.user_id = u.id
    WHERE uc.user_id = ? AND uc.is_selected = 1
");
$stmt->execute([$user_id]);
$player_character = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player_character) {
    header('Location: dashboard.php');
    exit;
}

// 计算玩家角色实际属性（基于等级）
$player_stats = calculate_character_stats($player_character);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 战斗</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, #0c0c1a, #1a1a2e);
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
        .battle-arena {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .character-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .character-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .character-name {
            font-size: 18px;
            font-weight: bold;
            color: #4ecca3;
        }
        .character-level {
            background: #4ecca3;
            color: #1a1a2e;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            font-size: 12px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .vs-section {
            text-align: center;
            margin: 20px 0;
            font-size: 24px;
            font-weight: bold;
            color: #ff6b6b;
            text-shadow: 0 0 10px rgba(255, 107, 107, 0.5);
        }
        .battle-controls {
            text-align: center;
            margin: 30px 0;
        }
        .battle-btn {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            border: none;
            border-radius: 12px;
            padding: 20px 40px;
            color: white;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        .battle-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }
        .battle-btn:active {
            transform: translateY(-1px);
        }
        .battle-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .battle-log {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.4;
        }
        .log-entry {
            margin-bottom: 8px;
            padding: 5px;
            border-radius: 5px;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .log-player {
            background: rgba(78, 204, 163, 0.2);
            border-left: 3px solid #4ecca3;
        }
        .log-enemy {
            background: rgba(255, 107, 107, 0.2);
            border-left: 3px solid #ff6b6b;
        }
        .log-system {
            background: rgba(255, 193, 7, 0.2);
            border-left: 3px solid #ffc107;
            text-align: center;
            font-weight: bold;
        }

        /* 优化后的结果界面样式 */
        .result-screen {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 10, 20, 0.95);
            z-index: 1000;
            padding: 20px;
            backdrop-filter: blur(10px);
            animation: fadeIn 0.5s ease;
        }

        .result-content {
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.9), rgba(22, 33, 62, 0.9));
            border: 2px solid #4ecca3;
            border-radius: 20px;
            padding: 25px;
            max-width: 400px;
            margin: 0 auto;
            position: relative;
            top: 50%;
            transform: translateY(-50%);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .result-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .result-victory {
            color: #4ecca3;
            background: linear-gradient(135deg, #4ecca3, #3db393);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .result-defeat {
            color: #ff6b6b;
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .result-message {
            font-size: 16px;
            color: #ccc;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .rewards-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }

        .rewards-title {
            font-size: 18px;
            font-weight: bold;
            color: #4ecca3;
            margin-bottom: 15px;
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            justify-items: center;
        }

        .reward-item {
            background: linear-gradient(135deg, rgba(78, 204, 163, 0.2), rgba(61, 179, 147, 0.2));
            border: 1px solid rgba(78, 204, 163, 0.3);
            border-radius: 10px;
            padding: 12px 8px;
            text-align: center;
            min-width: 80px;
            transition: all 0.3s ease;
        }

        .reward-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(78, 204, 163, 0.3);
        }

        .reward-icon {
            font-size: 20px;
            margin-bottom: 5px;
            display: block;
        }

        .reward-value {
            font-size: 14px;
            font-weight: bold;
            color: #4ecca3;
        }

        .reward-label {
            font-size: 10px;
            color: #ccc;
            margin-top: 2px;
        }

        .penalty-item {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.2), rgba(255, 82, 82, 0.2));
            border: 1px solid rgba(255, 107, 107, 0.3);
        }

        .penalty-value {
            color: #ff6b6b;
        }

        .result-buttons {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-top: 20px;
        }

        .result-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 20px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            min-height: 50px;
        }

        .result-btn.continue {
            background: linear-gradient(135deg, #4ecca3, #3db393);
            color: #1a1a2e;
        }

        .result-btn.restart {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: #1a1a2e;
        }

        .result-btn.back {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .result-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .result-btn:active {
            transform: translateY(-1px);
        }

        .btn-icon {
            font-size: 18px;
        }

        .btn-text {
            flex: 1;
            text-align: center;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* 响应式调整 */
        @media (max-width: 480px) {
            .result-content {
                margin: 10px;
                padding: 20px 15px;
                top: 45%;
            }
            
            .result-title {
                font-size: 28px;
            }
            
            .rewards-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .reward-item {
                padding: 10px 6px;
                min-width: 70px;
            }
            
            .result-btn {
                padding: 12px 15px;
                font-size: 14px;
                min-height: 45px;
            }
            
            .btn-icon {
                font-size: 16px;
            }
        }

        @media (max-width: 360px) {
            .result-content {
                padding: 15px 10px;
            }
            
            .result-title {
                font-size: 24px;
            }
            
            .rewards-grid {
                grid-template-columns: 1fr;
            }
            
            .reward-item {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">返回大厅</a>
            <h2>进化挑战</h2>
            <div>段位积分: <?php echo $player_character['rank_score']; ?></div>
        </div>

        <div class="battle-arena">
            <!-- 玩家角色 -->
            <div class="character-card">
                <div class="character-header">
                    <div class="character-name"><?php echo htmlspecialchars($player_character['name']); ?></div>
                    <div class="character-level">Lv.<?php echo $player_character['char_level']; ?></div>
                </div>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span>生命值:</span>
                        <span><?php echo $player_stats['hp']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>攻击力:</span>
                        <span><?php echo $player_stats['attack']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>防御力:</span>
                        <span><?php echo $player_stats['defense']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>特殊领域:</span>
                        <span><?php echo htmlspecialchars($player_character['special_field']); ?></span>
                    </div>
                </div>
            </div>

            <div class="vs-section">VS</div>

            <!-- 敌人角色 -->
            <div class="character-card">
                <div class="character-header">
                    <div class="character-name" id="enemyName">未知敌人</div>
                    <div class="character-level" id="enemyLevel">Lv.?</div>
                </div>
                <div class="stats-grid" id="enemyStats">
                    <div class="stat-item">
                        <span>生命值:</span>
                        <span>?</span>
                    </div>
                    <div class="stat-item">
                        <span>攻击力:</span>
                        <span>?</span>
                    </div>
                    <div class="stat-item">
                        <span>防御力:</span>
                        <span>?</span>
                    </div>
                    <div class="stat-item">
                        <span>种族:</span>
                        <span>?</span>
                    </div>
                </div>
            </div>

            <div class="battle-controls">
                <button class="battle-btn" onclick="startBattle()" id="battleBtn">开始战斗</button>
            </div>

            <div class="battle-log" id="battleLog">
                <div class="log-entry log-system">等待战斗开始...</div>
            </div>
        </div>

        <!-- 优化后的战斗结果界面 -->
        <div class="result-screen" id="resultScreen">
            <div class="result-content">
                <div id="resultTitle" class="result-title"></div>
                <div class="result-message" id="resultMessage"></div>
                
                <div class="rewards-section">
                    <div class="rewards-title">战斗结果</div>
                    <div class="rewards-grid" id="rewardsList"></div>
                </div>
                
                <div class="result-buttons">
                    <button class="result-btn continue" onclick="continueBattle()">
                        <span class="btn-icon">⚔️</span>
                        <span class="btn-text">继续挑战</span>
                    </button>
                    <button class="result-btn restart" onclick="location.reload()">
                        <span class="btn-icon">🔄</span>
                        <span class="btn-text">重新开始</span>
                    </button>
                    <a href="dashboard.php" class="result-btn back">
                        <span class="btn-icon">🏠</span>
                        <span class="btn-text">返回大厅</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let battleInProgress = false;
        let currentEnemy = null;

        async function startBattle() {
    if (battleInProgress) return;
    
    const battleBtn = document.getElementById('battleBtn');
    const battleLog = document.getElementById('battleLog');
    
    battleBtn.disabled = true;
    battleBtn.textContent = '战斗中...';
    battleInProgress = true;
    
    // 清空战斗日志
    battleLog.innerHTML = '<div class="log-entry log-system">生成敌人中...</div>';

    try {
        // 1. 生成敌人
        const enemyResponse = await fetch('api/generate_enemy.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const enemyData = await enemyResponse.json();
        
        if (!enemyData.success) {
            throw new Error(enemyData.message || '生成敌人失败');
        }
        
        currentEnemy = enemyData.enemy;
        displayEnemyInfo(currentEnemy);
        
        // 更新战斗日志
        battleLog.innerHTML = `<div class="log-entry log-system">遇到了 ${currentEnemy.name}！准备战斗...</div>`;
        
        // 2. 开始战斗
        const battleResponse = await fetch('api/battle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ enemy_id: currentEnemy.id })
        });
        
        const battleResult = await battleResponse.json();
        
        if (!battleResult.success) {
            throw new Error(battleResult.message || '战斗过程出错');
        }
        
        // 3. 显示战斗过程
        await displayBattleProcess(battleResult.battle_log);
        
        // 4. 显示战斗结果
        setTimeout(() => {
            showBattleResult(battleResult);
        }, 1000);
        
    } catch (error) {
        console.error('Battle Error:', error);
        battleLog.innerHTML += `<div class="log-entry log-system" style="color: #ff6b6b;">错误: ${error.message}</div>`;
        battleBtn.disabled = false;
        battleBtn.textContent = '开始战斗';
        battleInProgress = false;
        
        // 显示重试按钮
        setTimeout(() => {
            battleLog.innerHTML += `<div class="log-entry log-system">请检查网络连接后重试</div>`;
        }, 1000);
    }
}

        function displayEnemyInfo(enemy) {
            document.getElementById('enemyName').textContent = enemy.name;
            document.getElementById('enemyLevel').textContent = `Lv.${enemy.level}`;
            
            const enemyStats = document.getElementById('enemyStats');
            enemyStats.innerHTML = `
                <div class="stat-item">
                    <span>生命值:</span>
                    <span>${enemy.hp}</span>
                </div>
                <div class="stat-item">
                    <span>攻击力:</span>
                    <span>${enemy.attack}</span>
                </div>
                <div class="stat-item">
                    <span>防御力:</span>
                    <span>${enemy.defense}</span>
                </div>
                <div class="stat-item">
                    <span>种族:</span>
                    <span>${enemy.race_name}</span>
                </div>
            `;
        }

        async function displayBattleProcess(battleLog) {
            const logContainer = document.getElementById('battleLog');
            logContainer.innerHTML = '<div class="log-entry log-system">战斗开始！</div>';
            
            for (const logEntry of battleLog) {
                await new Promise(resolve => setTimeout(resolve, 800)); // 延迟显示
                
                const logElement = document.createElement('div');
                logElement.className = `log-entry log-${logEntry.type}`;
                logElement.textContent = logEntry.message;
                logElement.style.animation = 'fadeIn 0.5s ease';
                
                logContainer.appendChild(logElement);
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        }

        function showBattleResult(result) {
            const resultScreen = document.getElementById('resultScreen');
            const resultTitle = document.getElementById('resultTitle');
            const resultMessage = document.getElementById('resultMessage');
            const rewardsList = document.getElementById('rewardsList');
            
            // 设置结果标题和消息
            if (result.victory) {
                resultTitle.className = 'result-title result-victory';
                resultTitle.textContent = '胜利！';
                resultMessage.textContent = '恭喜你赢得了这场战斗！继续挑战变得更强大吧！';
            } else {
                resultTitle.className = 'result-title result-defeat';
                resultTitle.textContent = '失败！';
                resultMessage.textContent = '不要气馁，总结经验，下次一定会更好！';
            }
            
            // 设置奖励/惩罚显示
            let rewardsHtml = '';
            
            if (result.victory) {
                if (result.rewards.exp > 0) {
                    rewardsHtml += `
                        <div class="reward-item">
                            <span class="reward-icon">⭐</span>
                            <div class="reward-value">+${result.rewards.exp}</div>
                            <div class="reward-label">经验</div>
                        </div>
                    `;
                }
                if (result.rewards.coins > 0) {
                    rewardsHtml += `
                        <div class="reward-item">
                            <span class="reward-icon">💰</span>
                            <div class="reward-value">+${result.rewards.coins}</div>
                            <div class="reward-label">金币</div>
                        </div>
                    `;
                }
                if (result.rewards.rank_score > 0) {
                    rewardsHtml += `
                        <div class="reward-item">
                            <span class="reward-icon">🏆</span>
                            <div class="reward-value">+${result.rewards.rank_score}</div>
                            <div class="reward-label">积分</div>
                        </div>
                    `;
                }
            } else {
                if (result.rewards.exp > 0) {
                    rewardsHtml += `
                        <div class="reward-item">
                            <span class="reward-icon">⭐</span>
                            <div class="reward-value">+${result.rewards.exp}</div>
                            <div class="reward-label">经验</div>
                        </div>
                    `;
                }
                if (result.rewards.rank_score < 0) {
                    rewardsHtml += `
                        <div class="reward-item penalty-item">
                            <span class="reward-icon">📉</span>
                            <div class="reward-value penalty-value">${result.rewards.rank_score}</div>
                            <div class="reward-label">积分</div>
                        </div>
                    `;
                }
            }
            
            // 如果没有奖励，显示提示
            if (!rewardsHtml) {
                rewardsHtml = '<div style="color: #ccc; font-size: 14px;">本次战斗没有获得奖励</div>';
            }
            
            rewardsList.innerHTML = rewardsHtml;
            
            // 显示结果界面
            resultScreen.style.display = 'block';
            setTimeout(() => {
                resultScreen.classList.add('active');
            }, 10);
            
            // 重置战斗按钮
            const battleBtn = document.getElementById('battleBtn');
            battleBtn.disabled = false;
            battleBtn.textContent = '开始战斗';
            battleInProgress = false;
        }

        function continueBattle() {
            const resultScreen = document.getElementById('resultScreen');
            resultScreen.classList.remove('active');
            setTimeout(() => {
                resultScreen.style.display = 'none';
                document.getElementById('battleLog').innerHTML = '<div class="log-entry log-system">准备新的战斗...</div>';
            }, 300);
        }
    </script>
</body>
</html>
<?php
// 计算角色属性的函数
function calculate_character_stats($character) {
    $level = $character['char_level'];
    return [
        'hp' => $character['base_hp'] + ($level - 1) * 20,
        'attack' => $character['base_attack'] + ($level - 1) * 5,
        'defense' => $character['base_defense'] + ($level - 1) * 3,
        'mutate_value' => $character['mutate_value'] // 使用用户角色的实际异化值
    ];
}
?>