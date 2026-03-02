<?php
// api/upgrade_character.php
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

$input = json_decode(file_get_contents('php://input'), true);
$user_character_id = $input['user_character_id'] ?? 0;

if (!$user_character_id) {
    echo json_encode(['success' => false, 'message' => '角色ID无效']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 获取角色信息和用户金币
    $stmt = $pdo->prepare("
        SELECT uc.*, c.base_hp, c.base_attack, c.base_defense, c.mutate_value as base_mutate, u.coins 
        FROM user_characters uc 
        JOIN characters c ON uc.character_id = c.id 
        JOIN users u ON uc.user_id = u.id
        WHERE uc.id = ? AND uc.user_id = ?
    ");
    $stmt->execute([$user_character_id, $user_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => '角色不存在或不属于您']);
        exit;
    }
    
    $current_level = $data['level'];
    $current_mutate = $data['mutate_value'];
    $upgrade_cost = $data['upgrade_cost'];
    $user_coins = $data['coins'];
    
    // 检查金币是否足够
    if ($user_coins < $upgrade_cost) {
        echo json_encode(['success' => false, 'message' => '金币不足！升级需要 ' . $upgrade_cost . ' 金币']);
        exit;
    }
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 扣除金币
    $new_coins = $user_coins - $upgrade_cost;
    $stmt = $pdo->prepare("UPDATE users SET coins = ? WHERE id = ?");
    $stmt->execute([$new_coins, $user_id]);
    
    // 升级角色
    $new_level = $current_level + 1;
    $new_upgrade_cost = $upgrade_cost + ($new_level * 50); // 升级成本递增
    
    // 计算新的异化值（每级增加基础异化值的5%）
    $mutate_increase = ceil($data['base_mutate'] * 0.05);
    $new_mutate = $current_mutate + $mutate_increase;
    
    $stmt = $pdo->prepare("
        UPDATE user_characters 
        SET level = ?, upgrade_cost = ?, mutate_value = ? 
        WHERE id = ?
    ");
    $stmt->execute([$new_level, $new_upgrade_cost, $new_mutate, $user_character_id]);
    
    // 计算新的属性
    $new_hp = $data['base_hp'] + ($new_level - 1) * 20;
    $new_attack = $data['base_attack'] + ($new_level - 1) * 5;
    $new_defense = $data['base_defense'] + ($new_level - 1) * 3;
    
    // 计算下一级的属性（用于前端显示）
    $next_level = $new_level + 1;
    $next_hp = $data['base_hp'] + ($next_level - 1) * 20;
    $next_attack = $data['base_attack'] + ($next_level - 1) * 5;
    $next_defense = $data['base_defense'] + ($next_level - 1) * 3;
    $next_mutate = $new_mutate + $mutate_increase;
    
    $pdo->commit();
    
    // 更新session中的金币信息
    $_SESSION['coins'] = $new_coins;
    
    echo json_encode([
    'success' => true, 
    'message' => '升级成功！',
    'new_level' => $new_level,
    'new_hp' => $new_hp,
    'new_attack' => $new_attack,
    'new_defense' => $new_defense,
    'new_mutate' => $new_mutate,
    'new_upgrade_cost' => $new_upgrade_cost,
    'new_coins' => $new_coins,
    'next_level_info' => [
        'level' => $next_level,
        'hp' => $next_hp,
        'attack' => $next_attack,
        'defense' => $next_defense,
        'mutate' => $next_mutate,
        'upgrade_cost' => $new_upgrade_cost + ($next_level * 50)
    ]
]);
// 在文件末尾添加，确保没有额外的输出
exit; // 确保在 json_encode 后立即退出
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '升级失败: ' . $e->getMessage()]);
}
?>