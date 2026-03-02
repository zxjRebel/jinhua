<?php
// api/send_private_message.php
include '../config.php';

// 设置JSON头
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 检查用户是否登录
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$session_id = $input['session_id'] ?? 0;
$to_user_id = $input['to_user_id'] ?? 0;
$message = trim($input['message'] ?? '');

if (!$session_id || !$to_user_id) {
    echo json_encode(['success' => false, 'message' => '参数无效']);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => '消息内容不能为空']);
    exit;
}

if (strlen($message) > 500) {
    echo json_encode(['success' => false, 'message' => '消息长度不能超过500字']);
    exit;
}

$from_user_id = $_SESSION['user_id'];

try {
    // 验证会话是否属于当前用户
    $stmt = $pdo->prepare("
        SELECT id FROM chat_sessions 
        WHERE id = ? AND (user1_id = ? OR user2_id = ?)
    ");
    $stmt->execute([$session_id, $from_user_id, $from_user_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'message' => '聊天会话不存在']);
        exit;
    }
    
    // 插入消息
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (session_id, from_user_id, to_user_id, content, message_type) 
        VALUES (?, ?, ?, ?, 'text')
    ");
    $stmt->execute([$session_id, $from_user_id, $to_user_id, $message]);
    $message_id = $pdo->lastInsertId();
    
    // 更新会话最后活动时间
    $stmt = $pdo->prepare("
        UPDATE chat_sessions 
        SET last_message_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$session_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => '消息发送成功',
        'message_id' => $message_id
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '发送失败: ' . $e->getMessage()]);
}
?>