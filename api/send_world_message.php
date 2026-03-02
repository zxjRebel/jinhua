<?php
// api/send_world_message.php
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
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => '消息内容不能为空']);
    exit;
}

if (strlen($message) > 200) {
    echo json_encode(['success' => false, 'message' => '消息长度不能超过200字']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 检查聊天模式
$chat_mode = $chat_config['mode'] ?? 'local';

try {
    // 1. 本地写入 (混合模式或本地模式)
    if ($chat_mode === 'local' || $chat_mode === 'hybrid') {
        $stmt = $pdo->prepare("
            INSERT INTO world_chat_messages (user_id, message) 
            VALUES (?, ?)
        ");
        $stmt->execute([$user_id, $message]);
    }
    
    // 2. 远程广播 (混合模式或远程模式)
    if ($chat_mode === 'remote' || $chat_mode === 'hybrid') {
        // 获取用户信息
        $stmt = $pdo->prepare("SELECT nickname, username, race, level FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $race_names = ['bug' => '虫族', 'spirit' => '灵族', 'ghost' => '鬼族', 'human' => '人族', 'god' => '神族'];
            $race_name = $race_names[$user['race']] ?? '未知';

            $post_data = [
                'user_id' => $user_id,
                'nickname' => $user['nickname'],
                'username' => $user['username'],
                'race' => $user['race'],
                'race_name' => $race_name,
                'level' => $user['level'],
                'message' => $message,
                'server_name' => $chat_config['server_name'] ?? '一区'
            ];

            // 异步发送或设置极短超时，避免阻塞本地体验
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $chat_config['api_url'] . '?action=send');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2秒超时
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-KEY: ' . ($chat_config['api_key'] ?? '')
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 如果是纯远程模式，必须确保远程发送成功
            if ($chat_mode === 'remote') {
                if ($http_code == 200) {
                    $result = json_decode($response, true);
                    if ($result && ($result['success'] ?? false)) {
                        echo json_encode(['success' => true, 'message' => '消息发送成功']);
                        exit;
                    }
                }
                throw new Exception('远程发送失败');
            }
        }
    }
    
    // 只要不是纯远程模式失败，或者本地写入成功，都视为成功
    echo json_encode([
        'success' => true, 
        'message' => '消息发送成功'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '发送失败: ' . $e->getMessage()]);
}
?>