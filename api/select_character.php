<?php
// api/select_character.php
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
    // 验证角色是否属于该用户
    $stmt = $pdo->prepare("
        SELECT uc.id, c.name 
        FROM user_characters uc 
        JOIN characters c ON uc.character_id = c.id 
        WHERE uc.id = ? AND uc.user_id = ?
    ");
    $stmt->execute([$user_character_id, $user_id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$character) {
        echo json_encode(['success' => false, 'message' => '角色不存在或不属于您']);
        exit;
    }
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 先将所有角色设为非出战状态
    $stmt = $pdo->prepare("UPDATE user_characters SET is_selected = 0 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 将指定角色设为出战状态
    $stmt = $pdo->prepare("UPDATE user_characters SET is_selected = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$user_character_id, $user_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => '角色切换成功',
        'character_name' => $character['name']
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '切换失败: ' . $e->getMessage()]);
}
?>