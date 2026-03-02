<?php
// api/gacha_draw.php
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
$count = $input['count'] ?? 1;

if (!in_array($count, [1, 10])) {
    echo json_encode(['success' => false, 'message' => '无效的抽奖次数']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_race = $_SESSION['race'];

if (!$user_race) {
    echo json_encode(['success' => false, 'message' => '请先选择种族']);
    exit;
}

// 抽奖配置
$gacha_config = [
    'single_cost' => 100,
    'multi_cost' => 900,
    'duplicate_compensation' => 30, // 重复角色补偿金币
    'guaranteed_quality' => 10 // 十连保底高品质角色ID范围
];

$cost = $count === 1 ? $gacha_config['single_cost'] : $gacha_config['multi_cost'];

try {
    // 获取用户金币
    $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_coins = $stmt->fetchColumn();
    
    if ($user_coins < $cost) {
        echo json_encode(['success' => false, 'message' => '金币不足']);
        exit;
    }
    
    // 获取用户已拥有的角色ID
    $stmt = $pdo->prepare("SELECT character_id FROM user_characters WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $owned_character_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 扣除金币
    $new_coins = $user_coins - $cost;
    $stmt = $pdo->prepare("UPDATE users SET coins = ? WHERE id = ?");
    $stmt->execute([$new_coins, $user_id]);
    
    $results = [];
    $total_compensation = 0;
    $new_characters_count = 0;
    
    // 十连保底：确保至少有一个高品质角色
    $guaranteed_high_quality = ($count === 10);
    
    for ($i = 0; $i < $count; $i++) {
        // 如果是十连最后一次且需要保底，选择高品质角色
        if ($guaranteed_high_quality && $i === 9 && $new_characters_count === 0) {
            $stmt = $pdo->prepare("
                SELECT * FROM characters 
                WHERE race = ? AND id <= ? 
                ORDER BY RAND() LIMIT 1
            ");
            $stmt->execute([$user_race, $gacha_config['guaranteed_quality']]);
        } else {
            // 普通抽奖
            $stmt = $pdo->prepare("SELECT * FROM characters WHERE race = ? ORDER BY RAND() LIMIT 1");
            $stmt->execute([$user_race]);
        }
        
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$character) {
            throw new Exception('抽奖失败，该种族暂无角色');
        }
        
        $is_new = !in_array($character['id'], $owned_character_ids);
        $compensation = 0;
        
        if ($is_new) {
            // 新角色：添加到用户角色库
            // 新角色：添加到用户角色库
$stmt = $pdo->prepare("
    INSERT INTO user_characters (user_id, character_id, level, is_selected, upgrade_cost, mutate_value) 
    VALUES (?, ?, 1, 0, 100, ?)
");
$stmt->execute([$user_id, $character['id'], $character['mutate_value']]);
            $owned_character_ids[] = $character['id']; // 更新已拥有列表
            $new_characters_count++;
        } else {
            // 重复角色：给予金币补偿
            $compensation = $gacha_config['duplicate_compensation'];
            $total_compensation += $compensation;
        }
        
        $race_names = [
            'bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 
            'human' => '人族', 'god' => '神族'
        ];
        
        $results[] = [
            'id' => $character['id'],
            'name' => $character['name'],
            'race' => $character['race'],
            'race_name' => $race_names[$character['race']] ?? '未知',
            'base_hp' => $character['base_hp'],
            'base_attack' => $character['base_attack'],
            'base_defense' => $character['base_defense'],
            'special_field' => $character['special_field'],
            'is_new' => $is_new,
            'compensation' => $compensation
        ];
    }
    
    // 如果有重复补偿，添加到用户金币
    if ($total_compensation > 0) {
        $new_coins += $total_compensation;
        $stmt = $pdo->prepare("UPDATE users SET coins = ? WHERE id = ?");
        $stmt->execute([$new_coins, $user_id]);
    }
    
    $pdo->commit();
    
    // 更新session中的金币信息
    $_SESSION['coins'] = $new_coins;
    
    echo json_encode([
        'success' => true,
        'message' => '抽奖成功！',
        'new_coins' => $new_coins,
        'total_compensation' => $total_compensation,
        'new_characters_count' => $new_characters_count,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '抽奖失败: ' . $e->getMessage()]);
}
?>