<?php
// api/register.php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

$nickname = trim($_POST['nickname'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// 基本验证
if (empty($nickname) || empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => '请填写所有字段']);
    exit;
}

if (strlen($username) < 4 || strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => '账号至少4位，密码至少6位']);
    exit;
}

try {
    // 检查用户名是否已存在
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '账号已存在']);
        exit;
    }

    // 插入新用户
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (nickname, username, password, level, exp, rank_score, coins, created_at) VALUES (?, ?, ?, 1, 0, 0, 0, NOW())");
    
    if ($stmt->execute([$nickname, $username, $hashedPassword])) {
        // 注册成功后自动登录：设置 Session
        $userId = $pdo->lastInsertId();
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['nickname'] = $nickname;
        $_SESSION['race'] = null; // 新用户未选择种族
        $_SESSION['level'] = 1;
        
        echo json_encode(['success' => true, 'message' => '注册成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '注册失败，请重试']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误: ' . $e->getMessage()]);
}
?>