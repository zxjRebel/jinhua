<?php
// admin/user_form.php
// 用户编辑表单

include 'auth.php';
include '../config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: users.php');
    exit;
}

$title = '编辑用户';
$success = '';
$error = '';

// 获取现有数据
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname']);
    $race = $_POST['race'];
    $level = (int)$_POST['level'];
    $exp = (int)$_POST['exp'];
    $coins = (int)$_POST['coins'];
    $rank_score = (int)$_POST['rank_score'];
    $password = trim($_POST['password']);

    if (empty($nickname)) {
        $error = '昵称不能为空';
    } else {
        // 构建更新SQL
        $sql = "UPDATE users SET nickname=?, race=?, level=?, exp=?, coins=?, rank_score=? WHERE id=?";
        $params = [$nickname, $race, $level, $exp, $coins, $rank_score, $id];

        // 如果修改了密码
        if (!empty($password)) {
            $sql = "UPDATE users SET nickname=?, race=?, level=?, exp=?, coins=?, rank_score=?, password=? WHERE id=?";
            $params = [$nickname, $race, $level, $exp, $coins, $rank_score, password_hash($password, PASSWORD_DEFAULT), $id];
        }

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            $success = '用户更新成功';
            // 刷新数据
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = '更新失败，请重试';
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
        .form-container { background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; max-width: 800px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
        .form-text { font-size: 12px; color: #666; margin-top: 5px; }
        .btn-submit { background-color: #4CAF50; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background-color 0.3s; }
        .btn-submit:hover { background-color: #45a049; }
        .btn-back { background-color: #9e9e9e; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; margin-right: 10px; }
        .btn-back:hover { background-color: #757575; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- 主内容 -->
        <div class="main-content">
            <div class="header">
                <h2><?php echo $title; ?></h2>
                <div class="user-info">管理员: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
            </div>

            <div class="form-container">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>用户名 (不可修改)</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>昵称</label>
                        <input type="text" name="nickname" class="form-control" value="<?php echo htmlspecialchars($user['nickname']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>种族</label>
                        <select name="race" class="form-control">
                            <?php foreach ($races as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $user['race'] == $key ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>等级</label>
                        <input type="number" name="level" class="form-control" value="<?php echo $user['level']; ?>" min="1">
                    </div>

                    <div class="form-group">
                        <label>经验值</label>
                        <input type="number" name="exp" class="form-control" value="<?php echo $user['exp']; ?>" min="0">
                    </div>

                    <div class="form-group">
                        <label>金币</label>
                        <input type="number" name="coins" class="form-control" value="<?php echo $user['coins']; ?>" min="0">
                    </div>

                    <div class="form-group">
                        <label>排位分</label>
                        <input type="number" name="rank_score" class="form-control" value="<?php echo $user['rank_score']; ?>">
                    </div>

                    <div class="form-group">
                        <label>修改密码</label>
                        <input type="password" name="password" class="form-control" placeholder="留空则不修改">
                        <div class="form-text">如果不需要修改密码，请保持为空</div>
                    </div>

                    <div class="form-group" style="margin-top: 30px;">
                        <a href="users.php" class="btn-back">返回列表</a>
                        <button type="submit" class="btn-submit">保存更改</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>