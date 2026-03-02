<?php
// admin/users.php
// 用户管理

// 检查管理员登录状态
include 'auth.php';
include '../config.php';

// 处理搜索和筛选
$search = $_GET['search'] ?? '';
$filter_race = $_GET['race'] ?? '';

// 构建查询条件
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(nickname LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_race)) {
    $where[] = "race = ?";
    $params[] = $filter_race;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// 分页设置
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 获取用户总数
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $where_clause");
$stmt->execute($params);
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $per_page);

// 获取用户列表
$stmt = $pdo->prepare("SELECT id, username, nickname, race, level, exp, rank_score, coins, is_banned, ban_reason, banned_at, banned_by, ban_expires_at, created_at FROM users $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 种族列表
$races = [
    'bug' => '虫族',
    'spirit' => '灵族',
    'ghost' => '鬼族',
    'human' => '人族',
    'god' => '神族'
];

// 处理删除用户请求
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    // 执行删除操作
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // 重定向回用户列表
    header('Location: users.php?success=用户删除成功');
    exit;
}

// 处理封禁用户请求
if (isset($_POST['ban'])) {
    $user_id = (int)$_POST['ban'];
    $ban_reason = $_POST['ban_reason'] ?? '违反游戏规则';
    $ban_duration = $_POST['ban_duration'] ?? null;
    
    // 计算封禁到期时间
    $ban_expires_at = null;
    if ($ban_duration) {
        $ban_expires_at = date('Y-m-d H:i:s', strtotime("+{$ban_duration}"));
    }
    
    // 执行封禁操作
    $stmt = $pdo->prepare("UPDATE users SET 
        is_banned = 1, 
        ban_reason = ?, 
        banned_at = NOW(), 
        banned_by = ?, 
        ban_expires_at = ? 
        WHERE id = ?");
    $stmt->execute([$ban_reason, $_SESSION['admin_id'], $ban_expires_at, $user_id]);
    
    // 重定向回用户列表
    header('Location: users.php?success=用户封禁成功');
    exit;
}

// 处理解封用户请求
if (isset($_GET['unban'])) {
    $user_id = (int)$_GET['unban'];
    
    // 执行解封操作
    $stmt = $pdo->prepare("UPDATE users SET 
        is_banned = 0, 
        ban_reason = NULL, 
        banned_at = NULL, 
        banned_by = NULL, 
        ban_expires_at = NULL 
        WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // 重定向回用户列表
    header('Location: users.php?success=用户解封成功');
    exit;
}

// 处理编辑用户请求
if (isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $username = $_POST['username'] ?? '';
    $nickname = $_POST['nickname'] ?? '';
    $level = (int)($_POST['level'] ?? 1);
    $exp = (int)($_POST['exp'] ?? 0);
    $rank_score = (int)($_POST['rank_score'] ?? 0);
    $coins = (int)($_POST['coins'] ?? 0);
    $race = $_POST['race'] ?? '';
    
    // 验证表单数据
    if (empty($username)) {
        header('Location: users.php?error=用户名不能为空');
        exit;
    }
    
    // 执行更新操作
    $stmt = $pdo->prepare("UPDATE users SET 
        username = ?, 
        nickname = ?, 
        level = ?, 
        exp = ?, 
        rank_score = ?, 
        coins = ?, 
        race = ? 
        WHERE id = ?");
    $stmt->execute([$username, $nickname, $level, $exp, $rank_score, $coins, $race, $user_id]);
    
    // 重定向回用户列表
    header('Location: users.php?success=用户信息更新成功');
    exit;
}

// 处理重置密码请求
if (isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'] ?? '';
    
    // 验证新密码
    if (empty($new_password) || strlen($new_password) < 6) {
        header('Location: users.php?error=新密码不能为空且长度不能少于6个字符');
        exit;
    }
    
    // 加密密码
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // 执行密码更新
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed_password, $user_id]);
    
    // 重定向回用户列表
    header('Location: users.php?success=密码重置成功');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 页面特定样式 */
        .search-filter {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .search-filter input[type="text"],
        .search-filter select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 150px;
            font-size: 16px;
        }

        .users-table th {
            font-size: 14px !important;
        }
        .users-table td {
            font-size: 14px !important;
        }

        .action-btn-group {
            display: flex;
            align-items: center;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 4px;
            border: none;
            color: white;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
            margin-right: 5px;
            font-size: 14px;
        }
        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .search-filter input[type="submit"] {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        /* 表格样式优化 */
        .users-table table {
            font-size: 16px; /* 加大字体 */
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th, .users-table td {
            padding: 15px 10px;
            text-align: center;
        }

        .action-btn-group {
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 16px;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-edit { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); }
        .btn-password { background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); }
        .btn-ban { background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); }
        .btn-unban { background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); }
        .btn-delete { background: linear-gradient(135deg, #F44336 0%, #D32F2F 100%); }
        
        .btn-icon i {
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 侧边栏 -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- 主内容区 -->
        <main class="main-content">
            <div class="header">
                <h2>用户管理</h2>
                <div class="user-info">
                    <span>欢迎，<?php echo $_SESSION['admin_username']; ?></span>
                    <a href="logout.php" class="logout-btn">退出登录</a>
                </div>
            </div>
            
            <!-- 消息显示 -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_GET['success']; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_GET['error']; ?>
                </div>
            <?php endif; ?>
            
            <!-- 搜索和筛选 -->
            <div class="search-filter">
                <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                    <input type="text" name="search" placeholder="搜索用户名或昵称" value="<?php echo htmlspecialchars($search); ?>">
                    <select name="race">
                        <option value="">所有种族</option>
                        <?php foreach ($races as $key => $name): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filter_race === $key ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" value="搜索">
                </form>
            </div>
            
            <!-- 封禁模态框 -->
            <div id="banModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>封禁用户</h3>
                        <span class="close" onclick="closeBanModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="banForm" method="POST" action="users.php">
                            <input type="hidden" name="ban" id="banUserId">
                            <div class="form-group">
                                <label for="ban_reason">封禁原因:</label>
                                <input type="text" id="ban_reason" name="ban_reason" class="form-control" placeholder="请输入封禁原因" required>
                            </div>
                            <div class="form-group">
                                <label for="ban_duration">封禁时长:</label>
                                <select id="ban_duration" name="ban_duration" class="form-control">
                                    <option value="">永久封禁</option>
                                    <option value="1 day">1天</option>
                                    <option value="3 days">3天</option>
                                    <option value="7 days">7天</option>
                                    <option value="30 days">30天</option>
                                    <option value="90 days">90天</option>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeBanModal()">取消</button>
                                <button type="submit" class="btn btn-primary">确认封禁</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 编辑用户模态框 (优化版) -->
            <div id="editModal" class="modal">
                <div class="modal-content" style="max-width: 600px;">
                    <div class="modal-header">
                        <h3>编辑用户信息</h3>
                        <span class="close" onclick="closeEditModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="editForm" method="POST" action="users.php">
                            <input type="hidden" name="edit_user" value="1">
                            <input type="hidden" name="user_id" id="editUserId">
                            
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="editUsername">用户名</label>
                                    <input type="text" id="editUsername" name="username" class="form-control" placeholder="请输入用户名" required>
                                </div>
                                <div class="form-group">
                                    <label for="editNickname">昵称</label>
                                    <input type="text" id="editNickname" name="nickname" class="form-control" placeholder="请输入昵称">
                                </div>
                            </div>

                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="editRace">种族</label>
                                    <select id="editRace" name="race" class="form-control">
                                        <option value="">未选择</option>
                                        <?php foreach ($races as $key => $name): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="editLevel">等级</label>
                                    <input type="number" id="editLevel" name="level" class="form-control" min="1" placeholder="请输入等级" required>
                                </div>
                            </div>

                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="editExp">经验值</label>
                                    <input type="number" id="editExp" name="exp" class="form-control" min="0" placeholder="请输入经验值" required>
                                </div>
                                <div class="form-group">
                                    <label for="editRankScore">积分</label>
                                    <input type="number" id="editRankScore" name="rank_score" class="form-control" min="0" placeholder="请输入积分" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="editCoins">金币</label>
                                <input type="number" id="editCoins" name="coins" class="form-control" min="0" placeholder="请输入金币" required>
                            </div>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
                                <button type="submit" class="btn btn-primary">保存修改</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 重置密码模态框 -->
            <div id="resetPasswordModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>重置用户密码</h3>
                        <span class="close" onclick="closeResetPasswordModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="resetPasswordForm" method="POST" action="users.php">
                            <input type="hidden" name="reset_password" value="1">
                            <input type="hidden" name="user_id" id="resetPasswordUserId">
                            <div class="form-group">
                                <label for="newPassword">新密码:</label>
                                <input type="password" id="newPassword" name="new_password" class="form-control" placeholder="请输入新密码" minlength="6" required>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">取消</button>
                                <button type="submit" class="btn btn-primary">确认重置</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- JavaScript -->
            <script>
                // 打开封禁模态框
                function openBanModal(userId) {
                    document.getElementById('banUserId').value = userId;
                    document.getElementById('banModal').style.display = 'flex';
                }
                
                // 关闭封禁模态框
                function closeBanModal() {
                    document.getElementById('banModal').style.display = 'none';
                    document.getElementById('banForm').reset();
                }
                
                // 打开编辑用户模态框
                function openEditModal(userId, username, nickname, race, level, exp, rank_score, coins) {
                    document.getElementById('editUserId').value = userId;
                    document.getElementById('editUsername').value = username;
                    document.getElementById('editNickname').value = nickname;
                    document.getElementById('editRace').value = race;
                    document.getElementById('editLevel').value = level;
                    document.getElementById('editExp').value = exp;
                    document.getElementById('editRankScore').value = rank_score;
                    document.getElementById('editCoins').value = coins;
                    document.getElementById('editModal').style.display = 'flex';
                }
                
                // 关闭编辑用户模态框
                function closeEditModal() {
                    document.getElementById('editModal').style.display = 'none';
                    document.getElementById('editForm').reset();
                }
                
                // 打开重置密码模态框
                function openResetPasswordModal(userId) {
                    document.getElementById('resetPasswordUserId').value = userId;
                    document.getElementById('resetPasswordModal').style.display = 'flex';
                }
                
                // 关闭重置密码模态框
                function closeResetPasswordModal() {
                    document.getElementById('resetPasswordModal').style.display = 'none';
                    document.getElementById('resetPasswordForm').reset();
                }
                
                // 点击模态框外部关闭模态框
                window.onclick = function(event) {
                    var modals = [
                        document.getElementById('banModal'),
                        document.getElementById('editModal'),
                        document.getElementById('resetPasswordModal')
                    ];
                    
                    if (event.target == modals[0]) {
                        closeBanModal();
                    } else if (event.target == modals[1]) {
                        closeEditModal();
                    } else if (event.target == modals[2]) {
                        closeResetPasswordModal();
                    }
                }
            </script>
            
            <!-- 用户列表 -->
            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>昵称</th>
                            <th>种族</th>
                            <th>等级</th>
                            <th>经验值</th>
                            <th>积分</th>
                            <th>金币</th>
                            <th>注册时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['nickname']); ?></td>
                                    <td>
                                        <?php if ($user['race']): ?>
                                            <span class="status-badge badge-race-<?php echo $user['race']; ?>">
                                                <?php echo $races[$user['race']]; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background-color: #eee; color: #666;">未选择</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $user['level']; ?></td>
                                    <td><?php echo $user['exp']; ?></td>
                                    <td><?php echo $user['rank_score']; ?></td>
                                    <td><?php echo $user['coins']; ?></td>
                                    <td><?php echo $user['created_at']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_banned'] ? 'status-banned' : 'status-active'; ?>">
                                            <?php echo $user['is_banned'] ? '已封禁' : '正常'; ?>
                                        </span>
                                        <?php if ($user['is_banned']): ?>
                                            <div class="ban-info">
                                                <?php echo '原因: ' . htmlspecialchars($user['ban_reason']); ?><br>
                                                <?php echo '封禁时间: ' . $user['banned_at']; ?><br>
                                                <?php if ($user['ban_expires_at']): ?>
                                                    <?php echo '到期时间: ' . $user['ban_expires_at']; ?>
                                                <?php else: ?>
                                                    <?php echo '到期时间: 永久'; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btn-group">
                                            <button class="btn-icon btn-edit" onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['nickname']); ?>', '<?php echo $user['race']; ?>', <?php echo $user['level']; ?>, <?php echo $user['exp']; ?>, <?php echo $user['rank_score']; ?>, <?php echo $user['coins']; ?>)" title="编辑">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-password" onclick="openResetPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="重置密码">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($user['is_banned']): ?>
                                                <a href="users.php?unban=<?php echo $user['id']; ?>" class="btn-icon btn-unban" onclick="return confirm('确定要解封这个用户吗？')" title="解封">
                                                    <i class="fas fa-unlock"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn-icon btn-ban" onclick="openBanModal(<?php echo $user['id']; ?>)" title="封禁">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('确定要删除这个用户吗？')" title="删除">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="no-data">没有找到用户</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li><a href="users.php?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">首页</a></li>
                        <li><a href="users.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">上一页</a></li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <a href="users.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li><a href="users.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">下一页</a></li>
                        <li><a href="users.php?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">末页</a></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>