<?php
// api/generate_enemy.php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 获取玩家当前角色等级
    $stmt = $pdo->prepare("
        SELECT uc.level 
        FROM user_characters uc 
        WHERE uc.user_id = ? AND uc.is_selected = 1
    ");
    $stmt->execute([$user_id]);
    $player_level = $stmt->fetchColumn();
    
    if (!$player_level) {
        throw new Exception('未找到出战角色');
    }
    
    // 敌人等级在玩家等级±2范围内随机
    $min_level = max(1, $player_level - 2);
    $max_level = $player_level + 2;
    $enemy_level = rand($min_level, $max_level);
    
    // 随机选择一个种族
    $races = ['bug', 'spirit', 'ghost', 'human', 'god'];
    $enemy_race = $races[array_rand($races)];
    
    // 从该种族中随机选择一个角色作为敌人
    $stmt = $pdo->prepare("SELECT * FROM characters WHERE race = ? ORDER BY RAND() LIMIT 1");
    $stmt->execute([$enemy_race]);
    $enemy_character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$enemy_character) {
        // 如果该种族没有角色，随机选择任何角色
        $stmt = $pdo->prepare("SELECT * FROM characters ORDER BY RAND() LIMIT 1");
        $stmt->execute();
        $enemy_character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enemy_character) {
            throw new Exception('无法生成敌人，角色数据库为空');
        }
    }
    
    // 计算敌人属性（基于等级）
    $enemy_stats = calculate_enemy_stats($enemy_character, $enemy_level);
    
    $race_names = [
        'bug' => '虫族',
        'spirit' => '灵族', 
        'ghost' => '鬼族',
        'human' => '人族',
        'god' => '神族'
    ];
    
    echo json_encode([
        'success' => true,
        'enemy' => [
            'id' => $enemy_character['id'],
            'name' => $enemy_character['name'],
            'race' => $enemy_character['race'],
            'race_name' => $race_names[$enemy_character['race']] ?? '未知',
            'level' => $enemy_level,
            'hp' => $enemy_stats['hp'],
            'attack' => $enemy_stats['attack'],
            'defense' => $enemy_stats['defense'],
            'special_field' => $enemy_character['special_field'],
            'mutate_value' => $enemy_character['mutate_value']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function calculate_enemy_stats($character, $level) {
    return [
        'hp' => $character['base_hp'] + ($level - 1) * 18,
        'attack' => $character['base_attack'] + ($level - 1) * 4,
        'defense' => $character['base_defense'] + ($level - 1) * 2,
        'mutate_value' => $character['mutate_value'] // 使用基础异化值
    ];
}
?>