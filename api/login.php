<?php
// api/login.php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => '请填写所有字段']);
    exit;
}

try {
    // 查找用户
    $stmt = $pdo->prepare("SELECT id, nickname, username, password, race, level, is_banned, ban_reason, ban_expires_at FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // 检查封禁状态
        if (isset($user['is_banned']) && $user['is_banned']) {
             // 检查封禁是否已到期
             if ($user['ban_expires_at'] && strtotime($user['ban_expires_at']) < time()) {
                 // 封禁已到期，自动解封 (更新数据库)
                 $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL, ban_expires_at = NULL WHERE id = ?");
                 $stmt->execute([$user['id']]);
             } else {
                 $ban_msg = '您的账号已被封禁';
                 if (!empty($user['ban_reason'])) {
                     $ban_msg .= '，原因：' . $user['ban_reason'];
                 }
                 if (!empty($user['ban_expires_at'])) {
                     $ban_msg .= '，解封时间：' . $user['ban_expires_at'];
                 } else {
                     $ban_msg .= '，期限：永久';
                 }
                 echo json_encode(['success' => false, 'message' => $ban_msg]);
                 exit;
             }
        }

        // 登录成功，设置session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nickname'] = $user['nickname'];
        $_SESSION['race'] = $user['race'];
        $_SESSION['level'] = $user['level'];
        
        echo json_encode([
            'success' => true, 
            'message' => '登录成功',
            'user' => [
                'id' => $user['id'],
                'nickname' => $user['nickname'],
                'race' => $user['race']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '账号或密码错误']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误: ' . $e->getMessage()]);
}
?>