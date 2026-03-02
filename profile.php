<?php
// profile.php - 个人信息管理 (Top-tier UI Optimized)
// 开启错误报告以调试白屏问题
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// 确保users表包含avatar字段
function ensureAvatarFieldExists($pdo) {
    try {
        $stmt = $pdo->query("DESCRIBE users avatar");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL COMMENT '用户头像' AFTER race");
        }
    } catch (PDOException $e) {}
}

// 确保种族审核表存在
function ensureRaceAuditTableExists($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS race_change_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            old_race VARCHAR(20) NOT NULL,
            new_race VARCHAR(20) NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
    } catch (PDOException $e) {}
}

ensureAvatarFieldExists($pdo);
ensureRaceAuditTableExists($pdo);

// 确保上传目录存在
$upload_dir = __DIR__ . '/uploads';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 获取当前用户信息
$stmt = $pdo->prepare("SELECT id, nickname, username, race, avatar, password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_info) {
    // 用户不存在或已被删除
    session_destroy();
    header('Location: index.php');
    exit;
}

// 种族配置
$races = [
    'bug' => ['name' => '虫族', 'color' => '#8bc34a', 'icon' => '🐛'],
    'spirit' => ['name' => '灵族', 'color' => '#00bcd4', 'icon' => '👻'],
    'ghost' => ['name' => '鬼族', 'color' => '#9c27b0', 'icon' => '💀'],
    'human' => ['name' => '人族', 'color' => '#ff9800', 'icon' => '👤'],
    'god' => ['name' => '神族', 'color' => '#f44336', 'icon' => '⚡']
];

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $race = $_POST['race'] ?? '';
    
    // 处理头像上传
    $avatar = $user_info['avatar'];
    
    // Check for Post Max Size overflow
    if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $error = '上传文件过大，超过了服务器限制 (' . ini_get('post_max_size') . ')';
    }
    else if (isset($_FILES['avatar'])) {
        if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            // 使用 getimagesize 进行图片类型检测，兼容性更好
            $image_info = @getimagesize($file['tmp_name']);
            $file_type = $image_info ? $image_info['mime'] : '';
            
            if (!$image_info || !in_array($file_type, $allowed_types)) {
                $error = '只支持JPG、PNG、GIF、WEBP格式';
            } else if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                $error = '图片大小不能超过5MB';
            } else {
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $unique_name = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . '/' . $unique_name;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // 删除旧头像
                    if (!empty($user_info['avatar']) && file_exists(__DIR__ . '/' . $user_info['avatar']) && strpos($user_info['avatar'], 'uploads/') === 0) {
                        @unlink(__DIR__ . '/' . $user_info['avatar']);
                    }
                    $avatar = 'uploads/' . $unique_name;
                } else {
                    $error = '头像上传失败: 无法移动文件 (请检查 uploads 目录权限)';
                }
            }
        } elseif ($_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            // 处理其他上传错误
            switch ($_FILES['avatar']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error = '上传文件超过了 php.ini 中 upload_max_filesize 选项限制的值 (' . ini_get('upload_max_filesize') . ')';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error = '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = '文件只有部分被上传';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error = '找不到临时文件夹';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error = '文件写入失败';
                    break;
                default:
                    $error = '头像上传发生未知错误: ' . $_FILES['avatar']['error'];
            }
        }
    }
    
    // 验证逻辑
    if (empty($error)) {
        if (empty($nickname)) $error = '昵称不能为空';
        elseif (empty($username)) $error = '用户名不能为空';
        elseif (strlen($nickname) > 20) $error = '昵称太长了';
        elseif (!array_key_exists($race, $races)) $error = '无效的种族选择';
        else {
            // 检查用户名重复
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $error = '用户名已被使用';
            }
        }
    }
    
    // 处理更新
    if (empty($error)) {
        try {
            $pdo->beginTransaction();
            
            // 密码验证
            $update_password = false;
            $hashed_password = $user_info['password'];
            
            if (!empty($new_password)) {
                if (!password_verify($current_password, $user_info['password'])) {
                    throw new Exception('当前密码错误');
                }
                if ($new_password !== $confirm_password) {
                    throw new Exception('两次新密码输入不一致');
                }
                if (strlen($new_password) < 6) {
                    throw new Exception('新密码至少需要6位');
                }
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_password = true;
            }
            
            // 种族变更逻辑
            $race_change_pending = false;
            $update_race_directly = false;
            
            if ($race !== $user_info['race']) {
                if (empty($user_info['race'])) {
                    // 初始种族选择，直接更新无需审核
                    $update_race_directly = true;
                } else {
                    // 修改现有种族，需要审核
                    $stmt = $pdo->prepare("SELECT id FROM race_change_requests WHERE user_id = ? AND status = 'pending'");
                    $stmt->execute([$user_id]);
                    if ($stmt->fetch()) {
                        throw new Exception('您已有待处理的种族变更申请');
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO race_change_requests (user_id, old_race, new_race) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $user_info['race'], $race]);
                    $race_change_pending = true;
                }
            }
            
            // 更新用户信息
            $sql = "UPDATE users SET nickname = ?, username = ?, avatar = ?, password = ?";
            $params = [$nickname, $username, $avatar, $hashed_password];
            
            if ($update_race_directly) {
                $sql .= ", race = ?";
                $params[] = $race;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $user_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $pdo->commit();
            
            $msg = '个人资料更新成功！';
            if ($update_race_directly) {
                $msg .= ' 种族已选择。';
            }
            if ($race_change_pending) {
                $msg .= ' 种族变更申请已提交，请等待管理员审核。';
            }
            if ($update_password) {
                $msg .= ' 密码已修改。';
            }
            $success = $msg;
            
            // 刷新用户信息
            $_SESSION['username'] = $username;
            $stmt = $pdo->prepare("SELECT id, nickname, username, race, avatar, password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>个人资料 - 我的进化之路</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4ecca3;
            --primary-dark: #3db393;
            --bg-dark: #1a1a2e;
            --bg-card: #16213e;
            --text-main: #ffffff;
            --text-muted: #a0a0a0;
            --border: rgba(255, 255, 255, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
            background: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 80px; /* Space for bottom nav */
        }
        
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .back-btn {
            color: var(--text-main);
            text-decoration: none;
            font-size: 1.2rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            transition: all 0.3s ease;
        }
        
        .back-btn:active {
            transform: scale(0.95);
            background: rgba(255,255,255,0.1);
        }
        
        .page-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 0 20px;
            animation: fadeIn 0.5s ease;
        }
        
        .profile-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 1px solid var(--border);
        }
        
        .avatar-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
        }
        
        .avatar-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary);
            box-shadow: 0 0 20px rgba(78, 204, 163, 0.3);
            margin-bottom: 15px;
        }
        
        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(0,0,0,0.6);
            color: white;
            font-size: 0.8rem;
            text-align: center;
            padding: 5px 0;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .avatar-wrapper:hover .upload-overlay {
            opacity: 1;
        }
        
        .upload-btn {
            background: rgba(78, 204, 163, 0.1);
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-btn:active {
            background: var(--primary);
            color: #fff;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        
        .form-input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            padding: 12px 15px 12px 45px;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255,255,255,0.1);
            box-shadow: 0 0 0 3px rgba(78, 204, 163, 0.2);
        }
        
        /* Race Selection Grid */
        .race-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        
        .race-option {
            position: relative;
            cursor: pointer;
        }
        
        .race-option input {
            display: none;
        }
        
        .race-card {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 15px 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            height: 100px;
        }
        
        .race-icon {
            font-size: 1.8rem;
            margin-bottom: 8px;
            filter: grayscale(0.5); /* Reduce grayscale to make it less washed out */
            transition: all 0.3s;
        }
        
        .race-name {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Dynamic Race Styles */
        <?php foreach ($races as $key => $info): ?>
        .race-option input[value="<?php echo $key; ?>"]:checked + .race-card {
            background: <?php echo $info['color']; ?>22; /* ~13% opacity */
            border-color: <?php echo $info['color']; ?>;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px <?php echo $info['color']; ?>33;
        }
        
        .race-option input[value="<?php echo $key; ?>"]:checked + .race-card .race-icon {
            filter: grayscale(0);
            transform: scale(1.1);
        }
        
        .race-option input[value="<?php echo $key; ?>"]:checked + .race-card .race-name {
            color: <?php echo $info['color']; ?>;
        }
        <?php endforeach; ?>
        
        .password-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px dashed var(--border);
        }
        
        .section-title {
            color: #ffc107;
            font-size: 1.1rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #1a1a2e;
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 30px;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(78, 204, 163, 0.3);
            transition: all 0.3s;
        }
        
        .submit-btn:active {
            transform: scale(0.98);
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease;
        }
        
        .message.success {
            background: rgba(78, 204, 163, 0.15);
            border: 1px solid rgba(78, 204, 163, 0.3);
            color: var(--primary);
        }
        
        .message.error {
            background: rgba(255, 107, 107, 0.15);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="social.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="page-title">编辑资料</div>
        <div style="width: 40px;"></div> <!-- Spacer -->
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="avatar-section">
                    <div class="avatar-wrapper" onclick="document.getElementById('avatarInput').click()">
                        <img id="avatarPreview" src="<?php echo !empty($user_info['avatar']) ? $user_info['avatar'] : 'https://via.placeholder.com/150'; ?>" class="avatar-img" alt="头像">
                        <div class="upload-overlay">点击更换</div>
                    </div>
                    <button type="button" class="upload-btn" onclick="document.getElementById('avatarInput').click()">
                        <i class="fas fa-camera"></i> 更换头像
                    </button>
                    <input type="file" name="avatar" id="avatarInput" accept="image/*" hidden onchange="previewImage(this)">
                </div>

                <div class="form-group">
                    <label class="form-label">昵称</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="nickname" class="form-input" value="<?php echo htmlspecialchars($user_info['nickname']); ?>" placeholder="输入昵称">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">用户名 (登录账号)</label>
                    <div class="input-wrapper">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($user_info['username']); ?>" placeholder="输入用户名">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">种族 (变更需审核)</label>
                    <div class="race-grid">
                        <?php foreach ($races as $key => $info): ?>
                            <label class="race-option">
                                <input type="radio" name="race" value="<?php echo $key; ?>" <?php echo $user_info['race'] === $key ? 'checked' : ''; ?>>
                                <div class="race-card">
                                    <div class="race-icon"><?php echo $info['icon']; ?></div>
                                    <div class="race-name"><?php echo $info['name']; ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="password-section">
                    <div class="section-title">
                        <i class="fas fa-lock"></i> 修改密码 (可选)
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">当前密码</label>
                        <div class="input-wrapper">
                            <i class="fas fa-key"></i>
                            <input type="password" name="current_password" class="form-input" placeholder="不修改请留空">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">新密码</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock-open"></i>
                            <input type="password" name="new_password" class="form-input" placeholder="至少6位字符">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">确认新密码</label>
                        <div class="input-wrapper">
                            <i class="fas fa-check-double"></i>
                            <input type="password" name="confirm_password" class="form-input" placeholder="再次输入新密码">
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">保存修改</button>
            </form>
        </div>
    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
