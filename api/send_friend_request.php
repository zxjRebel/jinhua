<?php
// api/send_friend_request.php
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
$to_user_id = $input['to_user_id'] ?? 0;
$message = $input['message'] ?? '';

if (!$to_user_id) {
    echo json_encode(['success' => false, 'message' => '用户ID无效']);
    exit;
}

$from_user_id = $_SESSION['user_id'];

// 不能添加自己为好友
if ($from_user_id == $to_user_id) {
    echo json_encode(['success' => false, 'message' => '不能添加自己为好友']);
    exit;
}

try {
    // 检查目标用户是否存在
    $stmt = $pdo->prepare("SELECT id, nickname FROM users WHERE id = ?");
    $stmt->execute([$to_user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_user) {
        echo json_encode(['success' => false, 'message' => '用户不存在']);
        exit;
    }
    
    // 检查是否已经是好友
    $stmt = $pdo->prepare("
        SELECT id FROM friends 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->execute([$from_user_id, $to_user_id, $to_user_id, $from_user_id]);
    $existing_friend = $stmt->fetch();
    
    if ($existing_friend) {
        echo json_encode(['success' => false, 'message' => '你们已经是好友了']);
        exit;
    }
    
    // 检查是否已经有待处理的请求
    $stmt = $pdo->prepare("
        SELECT id FROM friend_requests 
        WHERE from_user_id = ? AND to_user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$from_user_id, $to_user_id]);
    $existing_request = $stmt->fetch();
    
    if ($existing_request) {
        echo json_encode(['success' => false, 'message' => '已经发送过好友请求了']);
        exit;
    }
    
    // 创建好友请求
    $stmt = $pdo->prepare("
        INSERT INTO friend_requests (from_user_id, to_user_id, message, status) 
        VALUES (?, ?, ?, 'pending')
    ");
    $stmt->execute([$from_user_id, $to_user_id, $message]);
    
    echo json_encode([
        'success' => true, 
        'message' => '好友请求已发送'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '发送失败: ' . $e->getMessage()]);
}
?>