<?php
// api/get_world_messages.php
include '../config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// 检查聊天模式
$chat_mode = $chat_config['mode'] ?? 'local';

// 远程/混合模式：优先尝试从远程API获取
if ($chat_mode === 'remote' || $chat_mode === 'hybrid') {
    $url = $chat_config['api_url'] . '?action=get&last_id=' . $last_id;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3秒超时
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . ($chat_config['api_key'] ?? '')
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if ($result && ($result['success'] ?? false)) {
            // 直接返回结果，由前端处理显示
            echo json_encode($result);
            exit;
        }
    }
    
    // 如果是纯远程模式且失败了
    if ($chat_mode === 'remote') {
        echo json_encode(['success' => true, 'messages' => []]);
        exit;
    }
    
    // 如果是混合模式且远程失败了，自动降级执行下方的本地逻辑
}

try {
    // 获取比 last_id 大的消息（如果是0，则获取最新的50条）
    if ($last_id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                wcm.*,
                u.nickname,
                u.race,
                u.level
            FROM world_chat_messages wcm
            JOIN users u ON wcm.user_id = u.id
            WHERE wcm.id > ?
            ORDER BY wcm.created_at ASC
        ");
        $stmt->execute([$last_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                wcm.*,
                u.nickname,
                u.race,
                u.level
            FROM world_chat_messages wcm
            JOIN users u ON wcm.user_id = u.id
            ORDER BY wcm.created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
    }
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 如果是获取最新50条，因为是DESC查出来的，所以要反转顺序
    if ($last_id == 0) {
        $messages = array_reverse($messages);
    }
    
    // 格式化时间
    foreach ($messages as &$msg) {
        $msg['time_str'] = date('H:i', strtotime($msg['created_at']));
        $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
        $msg['race_name'] = $race_names[$msg['race']] ?? '未知';
        // 本地消息附加当前服务器名称
        $msg['server_name'] = $chat_config['server_name'] ?? '';
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误: ' . $e->getMessage()]);
}
?>