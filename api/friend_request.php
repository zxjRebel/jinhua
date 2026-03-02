<?php
// api/friend_request.php
include '../config.php';

// 设置响应头
header('Content-Type: application/json');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'send') {
    $target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : 0;
    
    if ($target_id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的目标用户']);
        exit;
    }
    
    if ($target_id === $user_id) {
        echo json_encode(['success' => false, 'message' => '不能添加自己为好友']);
        exit;
    }
    
    try {
        // 检查目标用户是否存在
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$target_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => '用户不存在']);
            exit;
        }
        
        // 检查是否已经是好友
        $stmt = $pdo->prepare("SELECT id FROM friends WHERE user_id = ? AND friend_id = ?");
        $stmt->execute([$user_id, $target_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => '你们已经是好友了']);
            exit;
        }
        
        // 检查是否已经发送过请求（且未处理）
        $stmt = $pdo->prepare("SELECT id FROM friend_requests WHERE from_user_id = ? AND to_user_id = ? AND status = 'pending'");
        $stmt->execute([$user_id, $target_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => '已发送过好友请求，请耐心等待']);
            exit;
        }
        
        // 发送好友请求
        $stmt = $pdo->prepare("INSERT INTO friend_requests (from_user_id, to_user_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$user_id, $target_id]);
        
        echo json_encode(['success' => true, 'message' => '好友请求已发送']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '数据库错误: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '无效的操作']);
}
?>