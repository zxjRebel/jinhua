<?php
// admin/contact_us.php
// 联系我们管理页面

include 'auth.php';
include '../config.php';

$success = '';
$error = '';

// 确保联系我们表存在
function ensureContactUsTableExists($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS contact_us_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            is_enabled BOOLEAN DEFAULT TRUE COMMENT '是否在前端显示',
            title VARCHAR(100) DEFAULT '联系我们' COMMENT '标题',
            description TEXT DEFAULT NULL COMMENT '描述信息',
            link VARCHAR(255) DEFAULT NULL COMMENT '跳转链接',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $pdo->exec($sql);
        
        // 检查是否已有配置数据
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM contact_us_config");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            // 插入默认配置
            $stmt = $pdo->prepare("INSERT INTO contact_us_config (is_enabled, title, description, link) VALUES (?, ?, ?, ?)");
            $stmt->execute([true, '联系我们', "如果您有任何问题或建议，请通过以下方式联系我们：\n\n客服QQ：123456789\n客服邮箱：support@evolution.com\n工作时间：周一至周五 9:00-18:00", '']);
        }
    } catch (PDOException $e) {
        // 忽略错误
    }
}

ensureContactUsTableExists($pdo);

// 获取当前配置
$stmt = $pdo->query("SELECT * FROM contact_us_config LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $link = trim($_POST['link'] ?? '');
    
    try {
        // 更新配置
        $stmt = $pdo->prepare("UPDATE contact_us_config SET is_enabled = ?, title = ?, description = ?, link = ? WHERE id = ?");
        $stmt->execute([$is_enabled, $title, $description, $link, $config['id']]);
        
        $success = '配置更新成功';
        
        // 重新获取配置
        $stmt = $pdo->query("SELECT * FROM contact_us_config LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = '更新失败: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>联系我们管理 - 管理员后台</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <!-- 侧边栏 -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- 主内容区 -->
        <main class="main-content">
            <div class="header">
                <h2>联系我们管理</h2>
                <div class="user-info">
                    <span>欢迎，<?php echo $_SESSION['admin_username']; ?></span>
                    <a href="logout.php" class="logout-btn">退出登录</a>
                </div>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" action="">
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_enabled" name="is_enabled" <?php echo $config['is_enabled'] ? 'checked' : ''; ?>>
                        <label for="is_enabled" style="margin-bottom: 0;">启用前台显示</label>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">标题</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($config['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">内容描述 (支持纯文本显示)</label>
                        <textarea id="description" name="description" class="form-control" rows="12" style="height: 300px;"><?php echo htmlspecialchars($config['description']); ?></textarea>
                    </div>
                    
                    <!-- 链接字段虽然保留但前端优化后可能不使用 -->
                    <div class="form-group">
                        <label for="link">跳转链接 (可选)</label>
                        <input type="text" id="link" name="link" class="form-control" value="<?php echo htmlspecialchars($config['link']); ?>" placeholder="如果不填则仅显示文字内容">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">保存配置</button>
                </form>
                
                <div class="preview-section">
                    <h3 class="preview-title">内容预览</h3>
                    <div class="preview-box">
                        <h4 style="margin-bottom: 10px;"><?php echo htmlspecialchars($config['title']); ?></h4>
                        <div style="white-space: pre-wrap; color: #555; line-height: 1.6;"><?php echo htmlspecialchars($config['description']); ?></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
