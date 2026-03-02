<?php
// api/remove_friend.php
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
$friendship_id = $input['friendship_id'] ?? 0;

if (!$friendship_id) {
    echo json_encode(['success' => false, 'message' => '好友关系ID无效']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();
    
    // 获取好友关系信息
    $stmt = $pdo->prepare("
        SELECT f.*, u.nickname as friend_name
        FROM friends f
        JOIN users u ON f.friend_id = u.id
        WHERE f.id = ? AND f.user_id = ?
    ");
    $stmt->execute([$friendship_id, $user_id]);
    $friendship = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$friendship) {
        throw new Exception('好友关系不存在');
    }
    
    // 删除双向好友关系
    $stmt = $pdo->prepare("
        DELETE FROM friends 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->execute([
        $user_id, $friendship['friend_id'],
        $friendship['friend_id'], $user_id
    ]);
    
    // 删除聊天会话和相关消息
    $stmt = $pdo->prepare("
        SELECT id FROM chat_sessions 
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
    ");
    $stmt->execute([$user_id, $friendship['friend_id'], $friendship['friend_id'], $user_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        // 删除聊天消息
        $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE session_id = ?");
        $stmt->execute([$session['id']]);
        
        // 删除聊天会话
        $stmt = $pdo->prepare("DELETE FROM chat_sessions WHERE id = ?");
        $stmt->execute([$session['id']]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "已删除好友 {$friendship['friend_name']}"
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '删除失败: ' . $e->getMessage()]);
}
?>