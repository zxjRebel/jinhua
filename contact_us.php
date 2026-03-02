<?php
// contact_us.php
// 联系我们 (纯文本显示)
include 'config.php';

// 获取联系我们配置
$contact_config = [];
try {
    $stmt = $pdo->query("SELECT * FROM contact_us_config WHERE is_enabled = 1 LIMIT 1");
    $contact_config = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // 表可能不存在，忽略
}

// 如果没有配置或未启用，显示默认信息
if (!$contact_config) {
    $contact_config = [
        'title' => '联系我们',
        'description' => "如果您有任何问题或建议，请联系管理员。\n\n暂无具体联系方式配置。",
        'link' => ''
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($contact_config['title']); ?> - 我的进化之路</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 600px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin-top: 50px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .header h1 {
            font-size: 24px;
            color: #4ecca3;
        }
        .content {
            font-size: 16px;
            line-height: 1.8;
            color: #e0e0e0;
            white-space: pre-wrap; /* 保留换行符 */
            text-align: left;
        }
        .back-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 30px;
            font-weight: bold;
            transition: opacity 0.3s;
        }
        .back-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($contact_config['title']); ?></h1>
        </div>
        
        <div class="content"><?php echo htmlspecialchars($contact_config['description']); ?></div>
        
        <a href="javascript:history.back()" class="back-btn">返回上一页</a>
    </div>
    
    <!-- 底部导航栏 -->
    <?php include 'bottom_nav.php'; ?>
</body>
</html>
