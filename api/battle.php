<?php
// api/battle.php
include '../config.php';
include 'check_ban.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 检查用户是否被封禁
$ban_status = checkUserBanStatus($pdo, $_SESSION['user_id']);
if ($ban_status['banned']) {
    echo json_encode(['success' => false, 'message' => $ban_status['message']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$enemy_id = $input['enemy_id'] ?? 0;

if (!$enemy_id) {
    echo json_encode(['success' => false, 'message' => '敌人ID无效']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 获取玩家角色信息
    $stmt = $pdo->prepare("
        SELECT c.*, uc.level as char_level, u.rank_score, u.level as user_level, u.exp
        FROM user_characters uc 
        JOIN characters c ON uc.character_id = c.id 
        JOIN users u ON uc.user_id = u.id
        WHERE uc.user_id = ? AND uc.is_selected = 1
    ");
    $stmt->execute([$user_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        throw new Exception('未找到出战角色');
    }
    
    // 获取敌人信息（从characters表）
    $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
    $stmt->execute([$enemy_id]);
    $enemy_template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$enemy_template) {
        throw new Exception('敌人不存在');
    }
    
    // 计算双方属性
    $player_stats = calculate_character_stats($player);
    
    // 敌人等级在玩家角色等级附近
    $enemy_level = $player['char_level'] + rand(-2, 2);
    $enemy_level = max(1, $enemy_level);
    $enemy_stats = calculate_enemy_stats($enemy_template, $enemy_level);
    
    // 连战奖励系统
    if (!isset($_SESSION['battle_streak'])) {
        $_SESSION['battle_streak'] = 0;
    }

    // 每次战斗增加连战计数
    $_SESSION['battle_streak']++;

    // 如果超过1小时没有战斗，重置连战
    if (isset($_SESSION['last_battle_time'])) {
        $time_diff = time() - $_SESSION['last_battle_time'];
        if ($time_diff > 3600) { // 1小时
            $_SESSION['battle_streak'] = 1;
        }
    }
    $_SESSION['last_battle_time'] = time();
    
    // 计算胜率
    $win_rate = calculate_win_rate($player_stats, $enemy_stats, $player, $enemy_template);
    
    // 开始战斗
    $battle_log = [];
    $victory = simulate_battle($player_stats, $enemy_stats, $player, $enemy_template, $battle_log, $win_rate);
    
    // 计算奖励
    $rewards = calculate_rewards($victory, $player['char_level'], $enemy_level, $player['rank_score'], $player['user_level'], $_SESSION['battle_streak']);
    
    // 更新用户数据
    $pdo->beginTransaction();

    // 获取当前用户等级和经验
    $stmt = $pdo->prepare("SELECT level, exp FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $old_level = $user_data['level'];
    $old_exp = $user_data['exp'];
    
    if ($victory) {
        // 胜利：增加经验、金币、积分
        $stmt = $pdo->prepare("
            UPDATE users 
            SET exp = exp + ?, coins = coins + ?, rank_score = rank_score + ? 
            WHERE id = ?
        ");
        $stmt->execute([$rewards['exp'], $rewards['coins'], $rewards['rank_score'], $user_id]);
        
        // 检查升级（大幅增加升级难度）
        $new_exp = $old_exp + $rewards['exp'];
        $required_exp = calculate_required_exp($old_level);
        
        if ($new_exp >= $required_exp) {
            // 升级
            $new_level = $old_level + 1;
            $stmt = $pdo->prepare("UPDATE users SET level = ? WHERE id = ?");
            $stmt->execute([$new_level, $user_id]);
            $rewards['level_up'] = true;
            $rewards['new_level'] = $new_level;
            
            // 更新session中的等级
            $_SESSION['level'] = $new_level;
        } else {
            $rewards['level_up'] = false;
        }
        
        // 角色等级保持不变，只能通过金币升级
        
    } else {
        // 失败：增加少量经验，扣除积分（但不低于0）
        $new_rank_score = $player['rank_score'] + $rewards['rank_score'];
        $final_rank_score = max(0, $new_rank_score);
        $rank_change = $final_rank_score - $player['rank_score'];
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET exp = exp + ?, rank_score = ? 
            WHERE id = ?
        ");
        $stmt->execute([$rewards['exp'], $final_rank_score, $user_id]);
        
        $rewards['rank_score'] = $rank_change; // 更新实际积分变化
        $rewards['level_up'] = false;
        
        // 失败时重置连战计数
        $_SESSION['battle_streak'] = 0;
    }
    
    $pdo->commit();
    
    // 添加连战信息到返回结果
    $rewards['battle_streak'] = $_SESSION['battle_streak'];
    $rewards['streak_bonus'] = ($_SESSION['battle_streak'] >= 3) ? (1 + ($_SESSION['battle_streak'] * 0.05)) : 1;
    
    echo json_encode([
        'success' => true,
        'victory' => $victory,
        'rewards' => $rewards,
        'battle_log' => $battle_log,
        'win_rate' => round($win_rate * 100, 1)
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '战斗错误: ' . $e->getMessage()]);
}

// 新增函数：计算升级所需经验（指数增长）
function calculate_required_exp($current_level) {
    // 指数增长公式：100 * (1.5 ^ (等级-1))
    $base_exp = 100;
    $required_exp = $base_exp * pow(1.5, $current_level - 1);
    return ceil($required_exp);
}

function calculate_win_rate($player_stats, $enemy_stats, $player, $enemy) {
    // 使用玩家实际的异化值
    $player_power = ($player_stats['attack'] * 0.4 + $player_stats['hp'] * 0.3 + $player_stats['defense'] * 0.2 + $player_stats['mutate_value'] * 0.1);
    $enemy_power = ($enemy_stats['attack'] * 0.4 + $enemy_stats['hp'] * 0.3 + $enemy_stats['defense'] * 0.2 + $enemy['mutate_value'] * 0.1);
    
    $base_rate = $player_power / ($player_power + $enemy_power);
    
    // 特殊领域加成
    $special_bonus = 0;
    if ($player['special_field'] === '毒系精通' && $enemy['race'] === 'human') {
        $special_bonus = 0.1;
    } elseif ($player['special_field'] === '神圣治愈' && $enemy['race'] === 'ghost') {
        $special_bonus = 0.15;
    }
    
    return min(0.9, max(0.1, $base_rate + $special_bonus));
}

function simulate_battle($player_stats, $enemy_stats, $player, $enemy, &$battle_log, $win_rate) {
    $player_hp = $player_stats['hp'];
    $enemy_hp = $enemy_stats['hp'];
    
    $turn = 1;
    $max_turns = 8;
    
    $battle_log[] = [
        'type' => 'system',
        'message' => "战斗开始！{$player['name']} vs {$enemy['name']}"
    ];
    
    while ($player_hp > 0 && $enemy_hp > 0 && $turn <= $max_turns) {
        // 玩家攻击
        $player_damage = max(1, $player_stats['attack'] - $enemy_stats['defense'] * 0.3 + rand(-5, 5));
        $enemy_hp -= $player_damage;
        $enemy_hp = max(0, $enemy_hp);
        
        $battle_log[] = [
            'type' => 'player',
            'message' => "第{$turn}回合：{$player['name']} 发动攻击，造成 {$player_damage} 点伤害！"
        ];
        
        if ($enemy_hp <= 0) {
            $battle_log[] = [
                'type' => 'system', 
                'message' => "{$enemy['name']} 被击败！"
            ];
            return true;
        }
        
        // 敌人攻击
        $enemy_damage = max(1, $enemy_stats['attack'] - $player_stats['defense'] * 0.3 + rand(-5, 5));
        $player_hp -= $enemy_damage;
        $player_hp = max(0, $player_hp);
        
        $battle_log[] = [
            'type' => 'enemy',
            'message' => "第{$turn}回合：{$enemy['name']} 发动反击，造成 {$enemy_damage} 点伤害！"
        ];
        
        if ($player_hp <= 0) {
            $battle_log[] = [
                'type' => 'system',
                'message' => "{$player['name']} 被击败！"
            ];
            return false;
        }
        
        $battle_log[] = [
            'type' => 'system',
            'message' => "剩余生命：{$player['name']}({$player_hp}/{$player_stats['hp']}) vs {$enemy['name']}({$enemy_hp}/{$enemy_stats['hp']})"
        ];
        
        $turn++;
    }
    
    // 回合数用尽，根据胜率决定胜负
    $random = mt_rand(1, 100);
    $victory = ($random <= ($win_rate * 100));
    
    $battle_log[] = [
        'type' => 'system',
        'message' => "战斗超时！根据实力对比判定..."
    ];
    
    $battle_log[] = [
        'type' => 'system',
        'message' => $victory ? "经过激烈战斗，{$player['name']} 获得胜利！" : "经过激烈战斗，{$player['name']} 不幸落败！"
    ];
    
    return $victory;
}

function calculate_rewards($victory, $player_level, $enemy_level, $current_rank_score, $user_level, $battle_streak) {
    // 固定基础奖励（不随等级变化）
    $base_exp = 10;  // 固定10点基础经验
    $base_coins = max(3, $enemy_level * 8); // 金币保持不变，与敌人等级相关
    
    // 新手保护期奖励（适度降低）
    $newbie_bonus = 1.0;
    if ($user_level <= 5) {
        $newbie_bonus = 1.5; // 前5级1.5倍奖励
    } elseif ($user_level <= 10) {
        $newbie_bonus = 1.2; // 6-10级1.2倍奖励
    }
    
    if ($victory) {
        // 胜利奖励
        $rank_change = max(1, ceil($enemy_level / 3)); // 段位积分与敌人等级相关
        $exp_bonus = $base_exp * 1.5; // 胜利获得1.5倍经验
        $coin_bonus = $base_coins * 1.1; // 金币小幅加成
        
        $rewards = [
            'exp' => $exp_bonus,
            'coins' => $coin_bonus,
            'rank_score' => $rank_change
        ];
    } else {
        // 失败惩罚：扣除段位积分，幅度与胜利一致
        $rank_penalty = -max(1, ceil($enemy_level / 3)); // 与胜利获得的积分相同，但为负数
        
        $rewards = [
            'exp' => ceil($base_exp * 0.5), // 失败获得50%经验
            'coins' => ceil($base_coins * 0.2), // 失败获得20%金币
            'rank_score' => $rank_penalty
        ];
    }
    
    // 应用新手加成
    $rewards['exp'] = ceil($rewards['exp'] * $newbie_bonus);
    $rewards['coins'] = ceil($rewards['coins'] * $newbie_bonus);
    
    // 连战奖励
    if ($battle_streak >= 3) {
        $streak_bonus = 1 + ($battle_streak * 0.05); // 每场5%加成
        $rewards['exp'] = ceil($rewards['exp'] * $streak_bonus);
        $rewards['coins'] = ceil($rewards['coins'] * $streak_bonus);
        // 连战不影响段位积分
    }
    
    return $rewards;
}

function calculate_character_stats($character) {
    $level = $character['char_level'];
    return [
        'hp' => $character['base_hp'] + ($level - 1) * 20,
        'attack' => $character['base_attack'] + ($level - 1) * 5,
        'defense' => $character['base_defense'] + ($level - 1) * 3,
        'mutate_value' => $character['mutate_value'] // 使用用户角色的实际异化值
    ];
}

function calculate_enemy_stats($character, $level) {
    return [
        'hp' => $character['base_hp'] + ($level - 1) * 18,
        'attack' => $character['base_attack'] + ($level - 1) * 4,
        'defense' => $character['base_defense'] + ($level - 1) * 2
    ];
}
?>