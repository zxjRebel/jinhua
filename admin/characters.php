<?php
// admin/characters.php
// 角色管理

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
    $where[] = "name LIKE ?";
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

// 获取角色总数
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM characters $where_clause");
$stmt->execute($params);
$total_characters = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_characters / $per_page);

// 获取角色列表
$stmt = $pdo->prepare("SELECT * FROM characters $where_clause ORDER BY id ASC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 种族列表
$races = [
    'bug' => '虫族',
    'spirit' => '灵族',
    'ghost' => '鬼族',
    'human' => '人族',
    'god' => '神族'
];

// 处理角色表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $character_id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $race = $_POST['race'] ?? '';
    $base_hp = (int)($_POST['base_hp'] ?? 0);
    $base_attack = (int)($_POST['base_attack'] ?? 0);
    $base_defense = (int)($_POST['base_defense'] ?? 0);
    $special_field = trim($_POST['special_field'] ?? '');
    $mutate_value = (int)($_POST['mutate_value'] ?? 0);
    
    // 验证必填字段
    if (empty($name) || empty($race)) {
        $error = '角色名称和种族不能为空';
    } else {
        try {
            if ($character_id > 0) {
                // 更新角色
                $stmt = $pdo->prepare("UPDATE characters SET name = ?, race = ?, base_hp = ?, base_attack = ?, base_defense = ?, special_field = ?, mutate_value = ? WHERE id = ?");
                $stmt->execute([$name, $race, $base_hp, $base_attack, $base_defense, $special_field, $mutate_value, $character_id]);
                $success = '角色更新成功';
            } else {
                // 添加角色
                $stmt = $pdo->prepare("INSERT INTO characters (name, race, base_hp, base_attack, base_defense, special_field, mutate_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $race, $base_hp, $base_attack, $base_defense, $special_field, $mutate_value]);
                $success = '角色添加成功';
            }
            
            // 重定向回角色列表
            header('Location: characters.php?success=' . urlencode($success));
            exit;
        } catch (PDOException $e) {
            $error = '操作失败: ' . $e->getMessage();
        }
    }
}

// 处理删除角色请求
if (isset($_GET['delete'])) {
    $character_id = (int)$_GET['delete'];
    
    // 执行删除操作
    $stmt = $pdo->prepare("DELETE FROM characters WHERE id = ?");
    $stmt->execute([$character_id]);
    
    // 重定向回角色列表
    header('Location: characters.php?success=角色删除成功');
    exit;
}

// 获取角色信息（用于编辑）
$edit_character = null;
if (isset($_GET['edit'])) {
    $character_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
    $stmt->execute([$character_id]);
    $edit_character = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>角色管理</title>
    <style>
        /* 基本样式，继承自index.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* 侧边栏样式 */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .sidebar h1 {
            font-size: 24px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-menu li {
            margin-bottom: 10px;
        }
        
        .nav-menu a {
            display: block;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        /* 主内容区样式 */
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .header h2 {
            font-size: 28px;
            color: #333;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info span {
            margin-right: 15px;
            font-weight: bold;
        }
        
        .logout-btn {
            padding: 8px 15px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
        }
        
        /* 搜索和筛选样式 */
        .search-filter {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .search-filter input[type="text"],
        .search-filter select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-filter input[type="submit"] {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        /* 成功消息样式 */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* 添加角色按钮 */
        .add-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }
        
        /* 表格样式 */
        .characters-table {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .characters-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .characters-table th,
        .characters-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .characters-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #666;
        }
        
        .characters-table tr:hover {
            background-color: #f5f5f5;
        }
        
        /* 按钮样式 */
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            margin-right: 5px;
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            list-style: none;
        }
        
        .pagination li {
            margin: 0 5px;
        }
        
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #667eea;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover,
        .pagination .active a {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        /* 无数据样式 */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
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
                <h2>角色管理</h2>
                <div class="user-info">
                    <span>欢迎，<?php echo $_SESSION['admin_username']; ?></span>
                    <a href="logout.php" class="logout-btn">退出登录</a>
                </div>
            </div>
            
            <!-- 成功消息 -->
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <?php echo $_GET['success']; ?>
                </div>
            <?php endif; ?>
            
            <!-- 添加角色按钮 -->
            <div style="margin-bottom: 20px;">
                <a href="characters.php?action=add" class="add-btn">添加角色</a>
            </div>
            
            <!-- 角色表单 -->
            <?php if (isset($_GET['action']) && ($_GET['action'] === 'add' || $_GET['action'] === 'edit') || $edit_character): ?>
                <div class="search-filter">
                    <h3><?php echo $edit_character ? '编辑角色' : '添加角色'; ?></h3>
                    <?php if (isset($error)): ?>
                        <div style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?php echo $edit_character['id'] ?? 0; ?>">
                        
                        <div style="margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                            <div style="width: 300px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">角色名称</label>
                                <input type="text" name="name" value="<?php echo $edit_character['name'] ?? ''; ?>" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            
                            <div style="width: 200px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">种族</label>
                                <select name="race" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                    <?php foreach ($races as $key => $name): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (isset($edit_character['race']) && $edit_character['race'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                            <div style="width: 150px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">基础生命值</label>
                                <input type="number" name="base_hp" value="<?php echo $edit_character['base_hp'] ?? 100; ?>" min="1" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            
                            <div style="width: 150px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">基础攻击力</label>
                                <input type="number" name="base_attack" value="<?php echo $edit_character['base_attack'] ?? 10; ?>" min="0" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            
                            <div style="width: 150px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">基础防御力</label>
                                <input type="number" name="base_defense" value="<?php echo $edit_character['base_defense'] ?? 5; ?>" min="0" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            
                            <div style="width: 150px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">异化值</label>
                                <input type="number" name="mutate_value" value="<?php echo $edit_character['mutate_value'] ?? 0; ?>" min="0" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">特殊领域</label>
                            <input type="text" name="special_field" value="<?php echo $edit_character['special_field'] ?? ''; ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" style="padding: 10px 20px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; border-radius: 5px; cursor: pointer;">
                                <?php echo $edit_character ? '更新角色' : '添加角色'; ?>
                            </button>
                            <a href="characters.php" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block;">
                                取消
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- 搜索和筛选 -->
            <div class="search-filter">
                <form method="GET">
                    <input type="text" name="search" placeholder="搜索角色名称" value="<?php echo htmlspecialchars($search); ?>">
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
            
            <!-- 角色列表 -->
            <div class="characters-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名称</th>
                            <th>种族</th>
                            <th>基础生命值</th>
                            <th>基础攻击力</th>
                            <th>基础防御力</th>
                            <th>特殊领域</th>
                            <th>异化值</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($characters) > 0): ?>
                            <?php foreach ($characters as $character): ?>
                                <tr>
                                    <td><?php echo $character['id']; ?></td>
                                    <td><?php echo htmlspecialchars($character['name']); ?></td>
                                    <td><?php echo $races[$character['race']]; ?></td>
                                    <td><?php echo $character['base_hp']; ?></td>
                                    <td><?php echo $character['base_attack']; ?></td>
                                    <td><?php echo $character['base_defense']; ?></td>
                                    <td><?php echo htmlspecialchars($character['special_field']); ?></td>
                                    <td><?php echo $character['mutate_value']; ?></td>
                                    <td>
                                        <a href="characters.php?edit=<?php echo $character['id']; ?>&action=edit" class="btn btn-edit">编辑</a>
                                        <a href="characters.php?delete=<?php echo $character['id']; ?>" class="btn btn-delete" onclick="return confirm('确定要删除这个角色吗？')">删除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="no-data">没有找到角色</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li><a href="characters.php?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">首页</a></li>
                        <li><a href="characters.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">上一页</a></li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <a href="characters.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li><a href="characters.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">下一页</a></li>
                        <li><a href="characters.php?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_race) ? '&race=' . $filter_race : ''; ?>">末页</a></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>