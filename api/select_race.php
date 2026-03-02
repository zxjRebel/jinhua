<?php
// api/select_race.php
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
$race = $input['race'] ?? '';

$allowed_races = ['bug', 'spirit', 'ghost', 'human', 'god'];
if (!in_array($race, $allowed_races)) {
    echo json_encode(['success' => false, 'message' => '无效的种族选择']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 确保数据库字段存在
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
    } catch (PDOException $e) {}
}
ensureMutateValueFieldsExist($pdo);

try {
    // 检查用户是否已经选择了种族
    $stmt = $pdo->prepare("SELECT race FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['race']) {
        echo json_encode(['success' => false, 'message' => '您已经选择了种族，无法重复选择']);
        exit;
    }

    // 开始事务
    $pdo->beginTransaction();

    // 更新用户种族
    $stmt = $pdo->prepare("UPDATE users SET race = ? WHERE id = ?");
    $stmt->execute([$race, $user_id]);

    // 从该种族的角色中随机选择一个作为初始角色
    $stmt = $pdo->prepare("SELECT id, name, base_hp, base_attack, base_defense, special_field, mutate_value FROM characters WHERE race = ? ORDER BY RAND() LIMIT 1");
    $stmt->execute([$race]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        throw new Exception('该种族暂无可用角色，请联系管理员');
    }

    // 给用户添加初始角色 - 修复这里，确保包含所有必要字段
    $stmt = $pdo->prepare("
        INSERT INTO user_characters 
        (user_id, character_id, level, is_selected, exp, upgrade_cost, mutate_value) 
        VALUES (?, ?, 1, 1, 0, 100, ?)
    ");
    
    $result = $stmt->execute([
        $user_id, 
        $character['id'], 
        $character['mutate_value'] // 使用角色的基础异化值
    ]);
    
    if (!$result) {
        throw new Exception('添加初始角色失败');
    }

    // 更新session中的种族信息
    $_SESSION['race'] = $race;

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => '种族选择成功！',
        'character_name' => $character['name']
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("选择种族错误: " . $e->getMessage()); // 记录错误到日志
    echo json_encode([
        'success' => false, 
        'message' => '选择失败: ' . $e->getMessage()
    ]);
}

// 确保没有额外输出
exit;
?>