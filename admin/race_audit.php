<?php
// admin/race_audit.php
// 种族更改审核页面

include 'auth.php';
include '../config.php';

// 确保种族审核表存在
function ensureRaceAuditTableExists($pdo) {
    try {
        // 创建种族审核表
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
    } catch (PDOException $e) {
        // 忽略错误
    }
}

// 调用函数确保表存在
ensureRaceAuditTableExists($pdo);

$success = '';
$error = '';

// 处理审核操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve']) || isset($_POST['reject'])) {
        $request_id = $_POST['request_id'];
        $action = isset($_POST['approve']) ? 'approved' : 'rejected';
        
        try {
            // 开始事务
            $pdo->beginTransaction();
            
            // 获取请求信息
            $stmt = $pdo->prepare("SELECT user_id, old_race, new_race FROM race_change_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                if ($action === 'approved') {
                    // 更新用户种族
                    $stmt = $pdo->prepare("UPDATE users SET race = ? WHERE id = ?");
                    $stmt->execute([$request['new_race'], $request['user_id']]);
                }
                
                // 更新请求状态
                $stmt = $pdo->prepare("UPDATE race_change_requests SET status = ? WHERE id = ?");
                $stmt->execute([$action, $request_id]);
                
                // 提交事务
                $pdo->commit();
                
                $success = '审核操作成功';
            } else {
                $error = '请求不存在';
            }
        } catch (PDOException $e) {
            // 回滚事务
            $pdo->rollBack();
            $error = '审核失败: ' . $e->getMessage();
        }
    }
}

// 获取所有种族更改请求
$requests = [];
try {
    $stmt = $pdo->prepare("SELECT 
        r.id, r.user_id, r.old_race, r.new_race, r.status, r.created_at, r.updated_at,
        u.nickname, u.username
    FROM race_change_requests r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = '获取请求列表失败: ' . $e->getMessage();
}

// 种族名称映射
$races = [
    'bug' => '虫族',
    'spirit' => '灵族',
    'ghost' => '鬼族',
    'human' => '人族',
    'god' => '神族'
];

// 状态名称映射
$status_map = [
    'pending' => '待审核',
    'approved' => '已通过',
    'rejected' => '已拒绝'
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>种族更改审核 - 管理员后台</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <!-- 侧边栏 -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- 主内容区 -->
        <main class="main-content">
            <div class="header">
                <h2>种族更改审核</h2>
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
            
            <div class="table-container">
                <?php if (empty($requests)): ?>
                    <div style="text-align: center; padding: 30px; color: #ccc; font-style: italic;">暂无种族更改请求</div>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>请求ID</th>
                                <th>用户信息</th>
                                <th>原种族</th>
                                <th>新种族</th>
                                <th>状态</th>
                                <th>提交时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?php echo $request['id']; ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($request['nickname']); ?></div>
                                        <div style="font-size: 12px; color: #ccc;"><?php echo htmlspecialchars($request['username']); ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-race-<?php echo $request['old_race']; ?>">
                                            <?php echo $races[$request['old_race']] ?? $request['old_race']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-race-<?php echo $request['new_race']; ?>">
                                            <?php echo $races[$request['new_race']] ?? $request['new_race']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $status_class = '';
                                            switch($request['status']) {
                                                case 'pending': $status_class = 'badge-warning'; break;
                                                case 'approved': $status_class = 'status-active'; break;
                                                case 'rejected': $status_class = 'status-banned'; break;
                                            }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_map[$request['status']]; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $request['created_at']; ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <div style="display: flex; gap: 5px;">
                                                    <button type="submit" name="approve" class="btn btn-success">通过</button>
                                                    <button type="submit" name="reject" class="btn btn-danger">拒绝</button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #ccc; font-size: 12px;">已处理</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
