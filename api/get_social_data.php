<?php
// api/get_social_data.php
include '../config.php';

// 设置JSON头
header('Content-Type: application/json');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 获取好友数量
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as friend_count 
        FROM friends f
        JOIN users u ON f.friend_id = u.id
        WHERE f.user_id = ? AND f.status = 'accepted'
    ");
    $stmt->execute([$user_id]);
    $friend_count = $stmt->fetchColumn();

    // 获取待处理的好友请求数量
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as request_count 
        FROM friend_requests fr
        JOIN users u ON fr.from_user_id = u.id
        WHERE fr.to_user_id = ? AND fr.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $request_count = $stmt->fetchColumn();

    // 获取未读消息数量
    // 只有当发送者存在(或是系统/管理员)时才计算
    // 简单起见，这里假设消息都有效，或者我们也可以JOIN过滤
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM chat_messages cm
        LEFT JOIN users u ON cm.from_user_id = u.id
        WHERE cm.to_user_id = ? AND cm.is_read = 0 
        AND (cm.from_user_id <= 0 OR u.id IS NOT NULL)
    ");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();

    // 获取最新世界聊天消息ID
    $latest_world_chat_id = 0;
    
    // 检查聊天模式
    $chat_mode = $chat_config['mode'] ?? 'local';
    
    // 远程/混合模式：优先尝试从远程API获取
    if ($chat_mode === 'remote' || $chat_mode === 'hybrid') {
        $url = $chat_config['api_url'] . '?action=latest_id';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 缩短超时时间，避免阻塞
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . ($chat_config['api_key'] ?? '')
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            if ($result && ($result['success'] ?? false)) {
                $latest_world_chat_id = (int)$result['latest_id'];
            }
        }
    } 
    
    // 混合模式远程失败，或者本地模式，使用本地数据
    if ($latest_world_chat_id == 0 && ($chat_mode === 'local' || $chat_mode === 'hybrid')) {
        // 本地模式
        $stmt = $pdo->prepare("SELECT MAX(id) as max_id FROM world_chat_messages");
        $stmt->execute();
        $latest_world_chat_id = (int)$stmt->fetchColumn();
    }

    // 返回数据
    echo json_encode([
        'success' => true,
        'data' => [
            'friend_count' => (int)$friend_count,
            'request_count' => (int)$request_count,
            'unread_count' => (int)$unread_count,
            'latest_world_chat_id' => $latest_world_chat_id
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => '获取数据失败: ' . $e->getMessage()
    ]);
}
?>