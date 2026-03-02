<?php
// admin/character_form.php
// 角色添加/编辑表单

include 'auth.php';
include '../config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;
$title = $is_edit ? '编辑角色' : '添加新角色';
$success = '';
$error = '';

// 初始化数据
$character = [
    'name' => '',
    'race' => 'bug',
    'base_hp' => 100,
    'base_attack' => 10,
    'base_defense' => 5,
    'special_field' => '',
    'mutate_value' => 0,
    'description' => ''
];

// 如果是编辑，获取现有数据
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
    $stmt->execute([$id]);
    $fetched_character = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched_character) {
        $character = $fetched_character;
    } else {
        header('Location: characters.php');
        exit;
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $race = $_POST['race'];
    $base_hp = (int)$_POST['base_hp'];
    $base_attack = (int)$_POST['base_attack'];
    $base_defense = (int)$_POST['base_defense'];
    $special_field = trim($_POST['special_field']);
    $mutate_value = (int)$_POST['mutate_value'];
    $description = trim($_POST['description']);

    if (empty($name)) {
        $error = '角色名称不能为空';
    } else {
        if ($is_edit) {
            $stmt = $pdo->prepare("UPDATE characters SET name=?, race=?, base_hp=?, base_attack=?, base_defense=?, special_field=?, mutate_value=?, description=? WHERE id=?");
            if ($stmt->execute([$name, $race, $base_hp, $base_attack, $base_defense, $special_field, $mutate_value, $description, $id])) {
                $success = '角色更新成功';
                // 更新当前变量以便显示
                $character = array_merge($character, $_POST);
            } else {
                $error = '更新失败，请重试';
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO characters (name, race, base_hp, base_attack, base_defense, special_field, mutate_value, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $race, $base_hp, $base_attack, $base_defense, $special_field, $mutate_value, $description])) {
                header('Location: characters.php?success=角色添加成功');
                exit;
            } else {
                $error = '添加失败，请重试';
            }
        }
    }
}

$races = [
    'bug' => '虫族',
    'spirit' => '灵族',
    'ghost' => '鬼族',
    'human' => '人族',
    'god' => '神族'
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - 进化之路后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; color: #333; }
        .container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid #ddd; }
        .header h2 { font-size: 28px; color: #333; }
        .user-info { display: flex; align-items: center; }
        .user-info span { margin-right: 15px; font-weight: bold; }
        .logout-btn { padding: 8px 15px; background-color: #f44336; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; }
        
        .form-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea { height: 100px; resize: vertical; }
        
        .btn-group { display: flex; gap: 10px; margin-top: 30px; }
        .btn { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; text-decoration: none; text-align: center; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h1>进化之路后台</h1>
            <ul class="nav-menu">
                <li><a href="index.php">仪表盘</a></li>
                <li><a href="users.php">用户管理</a></li>
                <li><a href="characters.php" class="active">角色管理</a></li>
                <li><a href="announcements.php">公告管理</a></li>
                <li><a href="world_chat.php">世界聊天</a></li>
                <li><a href="settings.php">系统设置</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h2><?php echo $title; ?></h2>
                <div class="user-info">
                    <span>管理员: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="logout.php" class="logout-btn">退出</a>
                </div>
            </div>

            <div class="form-container">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>角色名称</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($character['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>种族</label>
                        <select name="race">
                            <?php foreach ($races as $key => $name): ?>
                                <option value="<?php echo $key; ?>" <?php echo $character['race'] === $key ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>基础生命值</label>
                            <input type="number" name="base_hp" value="<?php echo $character['base_hp']; ?>" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>基础攻击力</label>
                            <input type="number" name="base_attack" value="<?php echo $character['base_attack']; ?>" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>基础防御力</label>
                            <input type="number" name="base_defense" value="<?php echo $character['base_defense']; ?>" required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>特殊领域 (Special Field)</label>
                            <input type="text" name="special_field" value="<?php echo htmlspecialchars($character['special_field']); ?>">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>变异值 (Mutate Value)</label>
                            <input type="number" name="mutate_value" value="<?php echo $character['mutate_value']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>描述</label>
                        <textarea name="description"><?php echo htmlspecialchars($character['description']); ?></textarea>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">保存</button>
                        <a href="characters.php" class="btn btn-secondary">返回列表</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>