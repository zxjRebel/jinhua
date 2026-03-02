<?php
// admin/profile.php
// 管理员个人资料 (Optimized)

include 'auth.php';
include '../config.php';

// 确保admins表有nickname和avatar字段
function ensureAdminFieldsExist($pdo) {
    try {
        $stmt = $pdo->query("DESCRIBE admins nickname");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN nickname VARCHAR(50) DEFAULT NULL COMMENT '昵称' AFTER username");
        }
        
        $stmt = $pdo->query("DESCRIBE admins avatar");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN avatar VARCHAR(255) DEFAULT NULL COMMENT '头像' AFTER nickname");
        }
    } catch (PDOException $e) {}
}
ensureAdminFieldsExist($pdo);

$success = '';
$error = '';
$admin_id = $_SESSION['admin_id'];

// 确保上传目录存在
$upload_dir = __DIR__ . '/uploads';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 获取当前管理员信息
$stmt = $pdo->prepare("SELECT username, nickname, avatar FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die('管理员不存在');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 处理头像上传
    $avatar = $admin['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // 使用 getimagesize 进行图片类型检测
        $image_info = @getimagesize($file['tmp_name']);
        $file_type = $image_info ? $image_info['mime'] : '';
        
        if (!$image_info || !in_array($file_type, $allowed_types)) {
            $error = '只支持JPG、PNG、GIF、WEBP格式';
        } else if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            $error = '图片大小不能超过5MB';
        } else {
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_name = 'admin_avatar_' . $admin_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . '/' . $unique_name;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // 删除旧头像
                if (!empty($admin['avatar']) && file_exists(__DIR__ . '/' . $admin['avatar'])) {
                    @unlink(__DIR__ . '/' . $admin['avatar']);
                }
                $avatar = 'uploads/' . $unique_name;
            } else {
                $error = '头像上传失败';
            }
        }
    }
    
    if (empty($error)) {
        // 验证密码（如果填写了新密码，或者需要验证当前密码才能修改资料）
        // 这里简化逻辑：修改资料不需要密码，但修改密码需要原密码
        
        $update_password = false;
        $new_hash = null;
        
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $error = '修改密码需要输入当前密码';
            } else {
                // 验证当前密码
                $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
                $stmt->execute([$admin_id]);
                $current_hash = $stmt->fetchColumn();
                
                if (!password_verify($current_password, $current_hash)) {
                    $error = '当前密码错误';
                } elseif (strlen($new_password) < 6) {
                    $error = '新密码长度至少需要6位';
                } elseif ($new_password !== $confirm_password) {
                    $error = '两次输入的新密码不一致';
                } else {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_password = true;
                }
            }
        }
    }
    
    if (empty($error)) {
        try {
            $sql = "UPDATE admins SET nickname = ?, avatar = ?";
            $params = [$nickname, $avatar];
            
            if ($update_password) {
                $sql .= ", password = ?";
                $params[] = $new_hash;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $admin_id;
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $success = '管理员资料更新成功';
                // 刷新数据
                $admin['nickname'] = $nickname;
                $admin['avatar'] = $avatar;
            } else {
                $error = '更新失败，请重试';
            }
        } catch (PDOException $e) {
            $error = '数据库错误: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员资料 - 管理员后台</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css"> <!-- 引用统一CSS -->
    <style>
        /* 页面特定样式 */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .page-title h2 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .page-title .subtitle {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
            display: block;
        }
        
        .user-action {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .welcome-text {
            font-weight: 500;
            color: #6c757d;
        }
        
        .btn-logout {
            padding: 8px 20px;
            background-color: #fff;
            color: #dc3545;
            border: 1px solid #dc3545;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-logout:hover {
            background-color: #dc3545;
            color: white;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .avatar-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .avatar-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 15px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            cursor: pointer;
        }
        
        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(0,0,0,0.6);
            color: white;
            font-size: 12px;
            padding: 5px 0;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .avatar-wrapper:hover .avatar-overlay {
            opacity: 1;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-control { 
            width: 100%; 
            padding: 12px 15px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            font-size: 15px; 
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.25);
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(102, 126, 234, 0.3);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .action-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            border: 1px solid #dee2e6;
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .action-icon {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .action-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .action-desc {
            font-size: 12px;
            color: #6c757d;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-header">
                <div class="page-title">
                    <h2>管理员资料</h2>
                    <span class="subtitle">管理您的管理员信息和安全设置</span>
                </div>
                <div class="user-action">
                    <span class="welcome-text">欢迎，<?php echo htmlspecialchars($admin['nickname'] ?: $admin['username']); ?></span>
                    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> 退出</a>
                </div>
            </header>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="profile-container">
                <!-- 左侧：编辑资料 -->
                <div class="profile-card">
                    <div class="section-title">
                        <i class="fas fa-user-edit"></i> 基本信息
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="avatar-section">
                            <div class="avatar-wrapper" onclick="document.getElementById('avatarInput').click()">
                                <img src="<?php echo !empty($admin['avatar']) ? $admin['avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($admin['username']).'&background=667eea&color=fff'; ?>" class="avatar-img" id="avatarPreview">
                                <div class="avatar-overlay">点击更换</div>
                            </div>
                            <input type="file" name="avatar" id="avatarInput" hidden accept="image/*" onchange="previewImage(this)">
                            <div style="font-size: 12px; color: #999;">支持 JPG, PNG, GIF (最大 5MB)</div>
                        </div>
                        
                        <div class="form-group">
                            <label>用户名 (登录账号)</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" disabled style="background-color: #f9f9f9; cursor: not-allowed;">
                        </div>
                        
                        <div class="form-group">
                            <label>昵称</label>
                            <input type="text" name="nickname" class="form-control" value="<?php echo htmlspecialchars($admin['nickname'] ?? ''); ?>" placeholder="设置您的昵称">
                        </div>
                        
                        <div class="section-title" style="margin-top: 30px;">
                            <i class="fas fa-lock"></i> 安全设置
                        </div>
                        
                        <div class="form-group">
                            <label>当前密码 (修改密码时必填)</label>
                            <input type="password" name="current_password" class="form-control" placeholder="输入当前密码">
                        </div>
                        
                        <div class="form-group">
                            <label>新密码</label>
                            <input type="password" name="new_password" class="form-control" placeholder="留空则不修改 (至少6位)">
                        </div>
                        
                        <div class="form-group">
                            <label>确认新密码</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="再次输入新密码">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存修改
                        </button>
                    </form>
                </div>
                
                <!-- 右侧：快捷管理 -->
                <div class="quick-actions">
                    <div class="profile-card">
                        <div class="section-title">
                            <i class="fas fa-cogs"></i> 管理功能
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <a href="characters.php" class="action-card">
                                <div class="action-icon"><i class="fas fa-user-shield"></i></div>
                                <div class="action-title">角色管理</div>
                                <div class="action-desc">管理游戏角色和属性</div>
                            </a>
                            
                            <a href="settings.php" class="action-card">
                                <div class="action-icon"><i class="fas fa-envelope"></i></div>
                                <div class="action-title">系统配置</div>
                                <div class="action-desc">配置邮箱服务器等系统参数</div>
                            </a>
                            
                            <a href="rank_settings.php" class="action-card">
                                <div class="action-icon"><i class="fas fa-trophy"></i></div>
                                <div class="action-title">段位配置</div>
                                <div class="action-desc">管理游戏段位规则</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
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
