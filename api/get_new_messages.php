<?php
// api/get_new_messages.php
include '../config.php';

// 设置JSON头
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 检查用户是否登录
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$session_id = $_GET['session_id'] ?? 0;
$last_id = $_GET['last_id'] ?? 0;

if (!$session_id) {
    echo json_encode(['success' => false, 'message' => '会话ID无效']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 验证会话权限
    $stmt = $pdo->prepare("
        SELECT id FROM chat_sessions 
        WHERE id = ? AND (user1_id = ? OR user2_id = ?)
    ");
    $stmt->execute([$session_id, $user_id, $user_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'message' => '聊天会话不存在']);
        exit;
    }
    
    // 获取新消息
    $stmt = $pdo->prepare("
        SELECT 
            cm.id,
            cm.from_user_id,
            cm.content,
            cm.message_type,
            cm.created_at,
            COALESCE(u.nickname, '系统通知') as from_user_name,
            COALESCE(u.race, 'system') as from_user_race
        FROM chat_messages cm
        LEFT JOIN users u ON cm.from_user_id = u.id
        WHERE cm.session_id = ? AND cm.id > ? AND cm.to_user_id = ?
        ORDER BY cm.created_at ASC
    ");
    $stmt->execute([$session_id, $last_id, $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 标记消息为已读
    if (!empty($messages)) {
        $stmt = $pdo->prepare("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE session_id = ? AND id > ? AND to_user_id = ?
        ");
        $stmt->execute([$session_id, $last_id, $user_id]);
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '获取消息失败: ' . $e->getMessage()]);
}
?>