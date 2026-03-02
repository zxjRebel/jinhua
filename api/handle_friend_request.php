<?php
// api/handle_friend_request.php
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
$request_id = $input['request_id'] ?? 0;
$action = $input['action'] ?? ''; // 'accept' or 'reject'

if (!$request_id || !in_array($action, ['accept', 'reject'])) {
    echo json_encode(['success' => false, 'message' => '参数无效']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();
    
    // 获取好友请求信息
    $stmt = $pdo->prepare("
        SELECT fr.*, u.nickname as from_user_nickname
        FROM friend_requests fr
        JOIN users u ON fr.from_user_id = u.id
        WHERE fr.id = ? AND fr.to_user_id = ? AND fr.status = 'pending'
    ");
    $stmt->execute([$request_id, $user_id]);
    $friend_request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$friend_request) {
        throw new Exception('好友请求不存在或已被处理');
    }
    
    if ($action === 'accept') {
        // 接受好友请求 - 创建双向好友关系
        $stmt = $pdo->prepare("
            INSERT INTO friends (user_id, friend_id, status) 
            VALUES (?, ?, 'accepted'), (?, ?, 'accepted')
        ");
        $stmt->execute([
            $friend_request['from_user_id'], $user_id,
            $user_id, $friend_request['from_user_id']
        ]);
        
        // 创建聊天会话
        $stmt = $pdo->prepare("
            INSERT INTO chat_sessions (user1_id, user2_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$friend_request['from_user_id'], $user_id]);
        
        $message = "已接受 {$friend_request['from_user_nickname']} 的好友请求";
    } else {
        // 拒绝好友请求
        $message = "已拒绝 {$friend_request['from_user_nickname']} 的好友请求";
    }
    
    // 更新好友请求状态
    $new_status = $action === 'accept' ? 'accepted' : 'rejected';
    $stmt = $pdo->prepare("
        UPDATE friend_requests 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$new_status, $request_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => $message
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '处理失败: ' . $e->getMessage()]);
}
?>