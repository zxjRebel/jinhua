<?php
// admin/announcements.php
// 公告管理

// 检查管理员登录状态
include 'auth.php';
include '../config.php';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $type = $_POST['type'] ?? 'info';
    $end_time = $_POST['end_time'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (!empty($title) && !empty($content)) {
        // 检查是否是编辑还是新增
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // 更新公告
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE system_announcements SET title = ?, content = ?, type = ?, is_active = ?, end_time = ? WHERE id = ?");
            $stmt->execute([$title, $content, $type, $is_active, $end_time ?: null, $id]);
        } else {
            // 添加新公告
            $stmt = $pdo->prepare("INSERT INTO system_announcements (title, content, type, is_active, end_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $type, $is_active, $end_time ?: null]);
        }
        
        header('Location: announcements.php?success=操作成功');
        exit;
    }
}

// 处理删除公告请求
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM system_announcements WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: announcements.php?success=公告删除成功');
    exit;
}

// 获取所有公告
$stmt = $pdo->prepare("SELECT * FROM system_announcements ORDER BY created_at DESC");
$stmt->execute();
$all_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 公告类型
$announcement_types = [
    'info' => '普通公告',
    'warning' => '警告通知',
    'important' => '重要公告',
    'update' => '更新公告'
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>公告管理</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <!-- 侧边栏 -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- 主内容区 -->
        <main class="main-content">
            <div class="header">
                <h2>公告管理</h2>
                <div class="user-info">
                    <span>欢迎，<?php echo $_SESSION['admin_username']; ?></span>
                    <a href="logout.php" class="logout-btn">退出登录</a>
                </div>
            </div>
            
            <!-- 成功消息 -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_GET['success']; ?>
                </div>
            <?php endif; ?>
            
            <!-- 表单容器 -->
            <div class="card">
                <h3 id="formTitle">发布新公告</h3>
                <form method="POST">
                    <!-- 隐藏的ID字段，用于编辑 -->
                    <input type="hidden" id="announcement_id" name="id">
                    <div class="form-group">
                        <label for="title">标题:</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="content">内容:</label>
                        <textarea id="content" name="content" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="type">类型:</label>
                        <select id="type" name="type" class="form-control">
                            <?php foreach ($announcement_types as $key => $name): ?>
                                <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="end_time">结束时间 (可选):</label>
                        <input type="datetime-local" id="end_time" name="end_time" class="form-control">
                    </div>
                    <div class="form-group checkbox">
                        <input type="checkbox" id="is_active" name="is_active" checked>
                        <label for="is_active">立即生效</label>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" style="margin-right: 10px;" onclick="resetForm()">取消编辑</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">发布公告</button>
                    </div>
                </form>
            </div>
            
            <!-- 公告列表 -->
            <div class="announcement-list">
                <h3>所有公告</h3>
                <div class="announcement-items">
                    <?php if (count($all_announcements) > 0): ?>
                        <?php foreach ($all_announcements as $announcement): ?>
                            <div class="announcement-item">
                                <h4>
                                    <div>
                                        <span class="type-badge type-<?php echo $announcement['type']; ?>">
                                            <?php echo $announcement_types[$announcement['type']]; ?>
                                        </span>
                                        <span class="title"><?php echo htmlspecialchars($announcement['title']); ?></span>
                                    </div>
                                    <span class="status-badge status-<?php echo $announcement['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $announcement['is_active'] ? '活跃' : '关闭'; ?>
                                    </span>
                                </h4>
                                <div class="content">
                                    <?php echo htmlspecialchars($announcement['content']); ?>
                                </div>
                                <div class="meta">
                                    <div>
                                        创建时间: <?php echo $announcement['created_at']; ?>
                                        <?php if ($announcement['end_time']): ?>
                                            | 结束时间: <?php echo $announcement['end_time']; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="actions">
                                        <button class="btn btn-edit" onclick="editAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo addslashes($announcement['title']); ?>', '<?php echo addslashes($announcement['content']); ?>', '<?php echo $announcement['type']; ?>', <?php echo $announcement['is_active']; ?>, '<?php echo $announcement['end_time']; ?>')">编辑</button>
                                        <a href="announcements.php?delete=<?php echo $announcement['id']; ?>" class="btn btn-danger" onclick="return confirm('确定要删除这个公告吗？')">删除</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="announcement-item" style="text-align: center; color: #666;">
                            还没有发布任何公告
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // 编辑公告函数
        function editAnnouncement(id, title, content, type, isActive, endTime) {
            // 填充表单
            document.getElementById('announcement_id').value = id;
            document.getElementById('title').value = title;
            document.getElementById('content').value = content;
            document.getElementById('type').value = type;
            document.getElementById('is_active').checked = !!isActive;
            
            // 处理结束时间，转换为datetime-local格式
            if (endTime) {
                // 将MySQL datetime转换为datetime-local格式 (YYYY-MM-DDTHH:MM)
                const dt = new Date(endTime);
                const localDatetime = dt.toISOString().slice(0, 16);
                document.getElementById('end_time').value = localDatetime;
            } else {
                document.getElementById('end_time').value = '';
            }
            
            // 更新表单标题和按钮文本
            document.getElementById('formTitle').textContent = '编辑公告';
            document.getElementById('submitBtn').textContent = '更新公告';
            
            // 滚动到表单顶部
            document.querySelector('.form-container').scrollIntoView({ behavior: 'smooth' });
        }
        
        // 重置表单函数
        function resetForm() {
            // 清空表单
            document.getElementById('announcement_id').value = '';
            document.getElementById('title').value = '';
            document.getElementById('content').value = '';
            document.getElementById('type').value = 'info';
            document.getElementById('is_active').checked = true;
            document.getElementById('end_time').value = '';
            
            // 恢复表单标题和按钮文本
            document.getElementById('formTitle').textContent = '发布新公告';
            document.getElementById('submitBtn').textContent = '发布公告';
        }
    </script>
</body>
</html>