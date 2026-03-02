<?php
// admin/rank_settings.php
// 段位配置管理

include 'auth.php';
include '../config.php';

$success = '';
$error = '';

// 获取现有配置
$game_config_path = __DIR__ . '/../game_config.php';
$current_game_config = [];
if (file_exists($game_config_path)) {
    $current_game_config = include $game_config_path;
}

// 确保rank_config存在
if (!isset($current_game_config['rank_config'])) {
    $current_game_config['rank_config'] = [
        'ranks' => []
    ];
}
$rank_config = $current_game_config['rank_config'];

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'update') {
            // 处理更新段位配置
            $ranks = [];
            $rank_names = $_POST['rank_name'] ?? [];
            $min_scores = $_POST['min_score'] ?? [];
            $max_scores = $_POST['max_score'] ?? [];
            $colors = $_POST['color'] ?? [];
            $bg_colors = $_POST['bg_color'] ?? [];
            $text_colors = $_POST['text_color'] ?? [];
            
            // 验证并处理每个段位
            foreach ($rank_names as $index => $name) {
                if (!empty($name)) {
                    // 转换颜色：如果是Hex，保持；如果是rgba，尝试保留或转为Hex
                    // 这里我们假设用户通过颜色选择器提交的是Hex
                    $bg_color = trim($bg_colors[$index] ?? '#ffffff');
                    
                    // 如果需要保留透明度，可以在这里做特殊处理，但input type=color只支持Hex
                    // 为了兼容旧数据（RGBA），如果用户没改动且原数据是RGBA，可能需要保留？
                    // 但前端input type=color会把RGBA显示为黑色或不显示。
                    // 这里简单处理：直接保存提交的值。
                    
                    $ranks[] = [
                        'name' => trim($name),
                        'min_score' => (int)($min_scores[$index] ?? 0),
                        'max_score' => (int)($max_scores[$index] ?? 999999),
                        'color' => trim($colors[$index] ?? '#6c757d'),
                        'bg_color' => $bg_color,
                        'text_color' => trim($text_colors[$index] ?? '#ffffff')
                    ];
                }
            }
            
            // 排序：按最低分数排序
            usort($ranks, function($a, $b) {
                return $a['min_score'] <=> $b['min_score'];
            });
            
            // 更新配置数组
            $current_game_config['rank_config']['ranks'] = $ranks;
            
            // 保存配置到文件
            $content = "<?php\nreturn " . var_export($current_game_config, true) . ";\n";
            if (file_put_contents($game_config_path, $content) === false) {
                throw new Exception("无法写入配置文件");
            }
            
            $success = '段位配置更新成功';
            
            // 更新当前页面使用的配置变量
            $rank_config['ranks'] = $ranks;
        }
    } catch (Exception $e) {
        $error = '更新失败: ' . $e->getMessage();
    }
}

// 获取当前段位配置
$ranks = $rank_config['ranks'] ?? [];

// 辅助函数：将RGBA转换为Hex（用于input type=color的value显示）
function rgbaToHex($rgba) {
    if (strpos($rgba, '#') === 0) {
        return $rgba; // 已经是Hex
    }
    if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $rgba, $matches)) {
        return sprintf("#%02x%02x%02x", $matches[1], $matches[2], $matches[3]);
    }
    return '#ffffff'; // 默认白色
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>段位配置 - 管理员后台</title>
    <style>
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
        
        /* 复用 sidebar.php 的样式，或者为了兼容性，保留一些基础样式 */
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
        
        /* 成功消息样式 */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* 错误消息样式 */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* 表单容器样式 */
        .form-container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #4ecca3;
            font-size: 20px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        /* 段位配置表格样式 */
        .ranks-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .ranks-table th,
        .ranks-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .ranks-table th {
            background-color: #f8f9fa;
            color: #666;
            font-weight: bold;
        }
        
        .ranks-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .ranks-table input[type="text"],
        .ranks-table input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .ranks-table input[type="color"] {
            width: 60px;
            height: 40px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            padding: 0;
            background: none;
        }
        
        /* 添加段位按钮样式 */
        .add-rank-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .add-rank-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* 删除段位按钮样式 */
        .delete-rank-btn {
            padding: 5px 10px;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .delete-rank-btn:hover {
            transform: translateY(-1px);
        }
        
        /* 保存按钮样式 */
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* 段位预览样式 */
        .rank-preview {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            text-align: center;
            min-width: 60px;
        }
    </style>
    <script>
        // 添加新段位行
        function addRankRow() {
            const tableBody = document.querySelector('.ranks-table tbody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" name="rank_name[]" placeholder="段位名称" required oninput="previewRankStyle(this.closest('tr'))"></td>
                <td><input type="number" name="min_score[]" placeholder="最低积分" min="0" required></td>
                <td><input type="number" name="max_score[]" placeholder="最高积分" min="0" required></td>
                <td><input type="color" name="color[]" value="#6c757d" oninput="previewRankStyle(this.closest('tr'))"></td>
                <td><input type="color" name="bg_color[]" value="#eeeeee" oninput="previewRankStyle(this.closest('tr'))"></td>
                <td><input type="color" name="text_color[]" value="#ffffff" oninput="previewRankStyle(this.closest('tr'))"></td>
                <td><button type="button" class="delete-rank-btn" onclick="deleteRankRow(this)">删除</button></td>
                <td>
                    <div class="rank-preview" style="color: #ffffff; background-color: #eeeeee; border: 2px solid #6c757d;">
                        预览
                    </div>
                </td>
            `;
            tableBody.appendChild(newRow);
        }
        
        // 删除段位行
        function deleteRankRow(button) {
            const row = button.closest('tr');
            const tableBody = row.closest('tbody');
            if (tableBody.rows.length > 1) {
                row.remove();
            } else {
                alert('至少需要保留一个段位');
            }
        }
        
        // 预览段位样式
        function previewRankStyle(row) {
            const name = row.querySelector('input[name="rank_name[]"]').value || '预览';
            const color = row.querySelector('input[name="color[]"]').value;
            const bgColor = row.querySelector('input[name="bg_color[]"]').value;
            const textColor = row.querySelector('input[name="text_color[]"]').value;
            
            const preview = row.querySelector('.rank-preview');
            if (preview) {
                preview.textContent = name;
                preview.style.color = textColor;
                preview.style.backgroundColor = bgColor;
                preview.style.border = `2px solid ${color}`;
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <!-- 侧边栏 -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- 主内容区 -->
        <main class="main-content">
            <div class="header">
                <h2>段位配置</h2>
                <div class="user-info">
                    <span>欢迎，<?php echo $_SESSION['admin_username']; ?></span>
                    <a href="logout.php" class="logout-btn">退出登录</a>
                </div>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- 段位配置表单 -->
            <div class="form-container">
                <div class="section-title">段位配置管理</div>
                <p style="color: #666; margin-bottom: 20px;">以下是当前的段位配置，您可以修改现有段位或添加新段位。</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    
                    <button type="button" class="add-rank-btn" onclick="addRankRow()">添加新段位</button>
                    
                    <table class="ranks-table">
                        <thead>
                            <tr>
                                <th>段位名称</th>
                                <th>最低积分</th>
                                <th>最高积分</th>
                                <th>颜色</th>
                                <th>背景颜色</th>
                                <th>文字颜色</th>
                                <th>操作</th>
                                <th>预览</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranks as $index => $rank): ?>
                                <tr>
                                    <td><input type="text" name="rank_name[]" placeholder="段位名称" value="<?php echo htmlspecialchars($rank['name']); ?>" required oninput="previewRankStyle(this.closest('tr'))"></td>
                                    <td><input type="number" name="min_score[]" placeholder="最低积分" value="<?php echo $rank['min_score']; ?>" min="0" required></td>
                                    <td><input type="number" name="max_score[]" placeholder="最高积分" value="<?php echo $rank['max_score']; ?>" min="0" required></td>
                                    <td><input type="color" name="color[]" value="<?php echo $rank['color']; ?>" oninput="previewRankStyle(this.closest('tr'))"></td>
                                    <td><input type="color" name="bg_color[]" value="<?php echo rgbaToHex($rank['bg_color'] ?? '#ffffff'); ?>" oninput="previewRankStyle(this.closest('tr'))"></td>
                                    <td><input type="color" name="text_color[]" value="<?php echo $rank['text_color']; ?>" oninput="previewRankStyle(this.closest('tr'))"></td>
                                    <td><button type="button" class="delete-rank-btn" onclick="deleteRankRow(this)">删除</button></td>
                                    <td>
                                        <div class="rank-preview" style="
                                            color: <?php echo $rank['text_color']; ?>;
                                            background-color: <?php echo rgbaToHex($rank['bg_color'] ?? '#ffffff'); ?>;
                                            border: 2px solid <?php echo $rank['color']; ?>;
                                        ">
                                            <?php echo htmlspecialchars($rank['name']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <button type="submit" class="submit-btn">保存配置</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
