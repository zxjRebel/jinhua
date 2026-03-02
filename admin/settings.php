<?php
// admin/settings.php
// 系统设置

// 检查管理员登录状态
include 'auth.php';
include '../config.php';

$success = '';
$error = '';

// SMTP发送邮件函数
function smtpSendEmail($email_config, $to, $subject, $message) {
    // 提取配置信息
    $smtp_host = $email_config['smtp_host'];
    $smtp_port = $email_config['smtp_port'];
    $smtp_username = $email_config['smtp_username'];
    $smtp_password = $email_config['smtp_password'];
    $smtp_encryption = $email_config['smtp_encryption'];
    $from = $email_config['smtp_from'];
    $from_name = $email_config['smtp_from_name'];
    
    // 建立连接
    $socket = null;
    $log = []; // 记录日志
    $timeout = 30;
    
    try {
        $protocol = '';
        if ($smtp_encryption === 'ssl') {
            $protocol = 'ssl://';
        }
        
        $socket = stream_socket_client(
            $protocol . $smtp_host . ':' . $smtp_port,
            $errno,
            $errstr,
            $timeout
        );
        
        if (!$socket) {
            return ['success' => false, 'message' => "无法连接到SMTP服务器: $errstr ($errno)"];
        }
        
        // 读取服务器响应
        $response = fgets($socket, 515);
        if (empty($response) || substr($response, 0, 3) != '220') {
             return ['success' => false, 'message' => "连接响应错误: $response"];
        }
        
        // 发送EHLO命令
        fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        while ($line = fgets($socket, 515)) {
            if (substr($line, 3, 1) == " ") break;
        }
        
        // 处理TLS
        if ($smtp_encryption === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '220') {
                return ['success' => false, 'message' => "STARTTLS失败: $response"];
            }
            
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return ['success' => false, 'message' => "TLS加密建立失败"];
            }
            
            // TLS后再次EHLO
            fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            while ($line = fgets($socket, 515)) {
                if (substr($line, 3, 1) == " ") break;
            }
        }
        
        // 发送认证命令
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
             return ['success' => false, 'message' => "AUTH LOGIN失败: $response"];
        }
        
        // 发送用户名
        fwrite($socket, base64_encode($smtp_username) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
             return ['success' => false, 'message' => "用户名验证失败: $response"];
        }

        // 发送密码
        fwrite($socket, base64_encode($smtp_password) . "\r\n");
        $auth_response = fgets($socket, 515);
        
        if (substr($auth_response, 0, 3) != "235") {
            return ['success' => false, 'message' => "密码验证失败: " . $auth_response];
        }
        
        // 发送MAIL FROM命令
        fwrite($socket, "MAIL FROM: <" . $from . ">\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
             return ['success' => false, 'message' => "MAIL FROM失败: $response"];
        }
        
        // 发送RCPT TO命令
        fwrite($socket, "RCPT TO: <" . $to . ">\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
             return ['success' => false, 'message' => "RCPT TO失败: $response"];
        }
        
        // 发送DATA命令
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '354') {
             return ['success' => false, 'message' => "DATA命令失败: $response"];
        }
        
        // 构建邮件头和内容
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <" . $from . ">\r\n";
        $headers .= "To: " . $to . "\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "X-Mailer: Evolution-Road-Mailer\r\n";
        
        $email_content = $headers . "\r\n" . $message . "\r\n.\r\n";
        
        // 发送邮件内容
        fwrite($socket, $email_content);
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '250') {
             return ['success' => false, 'message' => "发送内容失败: $response"];
        }
        
        // 发送QUIT命令
        fwrite($socket, "QUIT\r\n");
        fgets($socket, 515);
        
        // 关闭连接
        fclose($socket);
        
        return ['success' => true, 'message' => "测试邮件发送成功，请检查收件箱"];
        
    } catch (Exception $e) {
        if ($socket) {
            fclose($socket);
        }
        return ['success' => false, 'message' => "SMTP异常: " . $e->getMessage()];
    }
}

// 发送测试邮件函数
function sendTestEmail($email_config, $test_email) {
    $to = $test_email;
    $subject = '测试邮件 - 我的进化之路';
    $message = '这是一封测试邮件，用于验证邮箱配置是否正常工作。如果您收到这封邮件，说明邮箱配置已成功！👎我是葫芦侠彩臣';
    
    // 使用SMTP函数发送邮件
    return smtpSendEmail($email_config, $to, $subject, $message);
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理邮箱配置保存
    if (isset($_POST['save_email_config']) || isset($_POST['test_email_config'])) {
        // 验证表单数据
        if (empty($_POST['smtp_host']) || empty($_POST['smtp_port']) || empty($_POST['smtp_username']) || empty($_POST['smtp_password']) || empty($_POST['smtp_from_name'])) {
            $error = '请填写所有必填字段';
        } else {
            // 创建邮箱配置数组
            $new_email_config = [
                'smtp_host' => $_POST['smtp_host'],
                'smtp_port' => (int)$_POST['smtp_port'],
                'smtp_username' => $_POST['smtp_username'],
                'smtp_password' => $_POST['smtp_password'],
                'smtp_encryption' => $_POST['smtp_encryption'],
                'smtp_from' => $_POST['smtp_username'], // 使用发件人邮箱作为发件地址
                'smtp_from_name' => $_POST['smtp_from_name']
            ];
            
            // 处理测试邮件发送
            if (isset($_POST['test_email_config'])) {
                // 验证测试邮箱
                if (empty($_POST['test_email']) || !filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL)) {
                    $error = '请输入有效的测试邮箱';
                } else {
                    // 保存配置
                    $config_content = "<?php\nreturn " . var_export($new_email_config, true) . ";\n";
                    
                    $email_config_file = dirname(__DIR__) . '/email_config.php';
                    if (file_put_contents($email_config_file, $config_content)) {
                        // 更新内存中的配置
                        $email_config = $new_email_config;
                        
                        // 发送测试邮件
                        $test_result = sendTestEmail($new_email_config, $_POST['test_email']);
                        if ($test_result['success']) {
                            $success = $test_result['message'] . '，配置已保存';
                        } else {
                            $error = $test_result['message'] . '，但配置已保存';
                        }
                    } else {
                        $error = '配置保存失败，无法写入文件';
                    }
                }
            } 
            // 处理保存配置
            else if (isset($_POST['save_email_config'])) {
                // 将配置写入文件
                $config_content = "<?php\nreturn " . var_export($new_email_config, true) . ";\n";
                
                $email_config_file = dirname(__DIR__) . '/email_config.php';
                if (file_put_contents($email_config_file, $config_content)) {
                    $success = '邮箱配置已保存';
                    $email_config = $new_email_config; // 更新内存中的配置
                } else {
                    $error = '邮箱配置保存失败，无法写入文件';
                }
            }
        }
    }
    // 处理游戏配置保存 (签到、段位)
    else if (isset($_POST['save_game_config'])) {
        $new_signin_config = ['daily_rewards' => [], 'streak_bonus' => []];
        $new_rank_config = ['ranks' => []];

        // 处理每日奖励
        if (isset($_POST['daily_coins']) && is_array($_POST['daily_coins'])) {
            foreach ($_POST['daily_coins'] as $day => $coins) {
                $exp = $_POST['daily_exp'][$day] ?? 0;
                $new_signin_config['daily_rewards'][$day] = [
                    'coins' => (int)$coins,
                    'exp' => (int)$exp
                ];
            }
        }

        // 处理连签奖励
        if (isset($_POST['streak_days']) && is_array($_POST['streak_days'])) {
            foreach ($_POST['streak_days'] as $index => $days) {
                if ($days > 0) {
                    $coins = $_POST['streak_coins'][$index] ?? 0;
                    $exp = $_POST['streak_exp'][$index] ?? 0;
                    $new_signin_config['streak_bonus'][$days] = [
                        'coins' => (int)$coins,
                        'exp' => (int)$exp
                    ];
                }
            }
        }

        // 处理段位配置
        if (isset($_POST['rank_name']) && is_array($_POST['rank_name'])) {
            foreach ($_POST['rank_name'] as $index => $name) {
                $new_rank_config['ranks'][] = [
                    'name' => $name,
                    'min_score' => (int)($_POST['rank_min'][$index] ?? 0),
                    'max_score' => (int)($_POST['rank_max'][$index] ?? 999999),
                    'color' => $_POST['rank_color'][$index] ?? '#000000',
                    'bg_color' => $_POST['rank_bg_color'][$index] ?? 'rgba(0,0,0,0.1)',
                    'text_color' => $_POST['rank_text_color'][$index] ?? '#ffffff'
                ];
            }
        }

        // 保存到 game_config.php
        $new_game_config = [
            'signin_config' => $new_signin_config,
            'rank_config' => $new_rank_config
        ];
        
        $game_config_content = "<?php\nreturn " . var_export($new_game_config, true) . ";\n";
        $game_config_file = dirname(__DIR__) . '/game_config.php';
        
        if (file_put_contents($game_config_file, $game_config_content)) {
            $success = '游戏配置已保存';
            // 更新内存
            $signin_config = $new_signin_config;
            $rank_config = $new_rank_config;
        } else {
            $error = '游戏配置保存失败';
        }
    }
    // 处理聊天配置保存
    else if (isset($_POST['save_chat_config'])) {
        $server_name = $_POST['server_name'];
        // 如果为空，允许为空，以便config.php自动获取
        
        $new_chat_config = [
            'mode' => $_POST['chat_mode'],
            'api_url' => $_POST['chat_api_url'],
            'api_key' => $_POST['chat_api_key'],
            'server_name' => $server_name
        ];
        
        $chat_config_content = "<?php\nreturn " . var_export($new_chat_config, true) . ";\n";
        $chat_config_file = dirname(__DIR__) . '/chat_config.php';
        
        if (file_put_contents($chat_config_file, $chat_config_content)) {
            $success = '聊天配置已保存';
            $chat_config = $new_chat_config;
        } else {
            $error = '聊天配置保存失败';
        }
    }
    // Donation config logic removed
}

// 获取当前配置
$current_config = [
    'host' => $host,
    'dbname' => $dbname,
    'signin_config' => $signin_config,
    'rank_config' => $rank_config,
    'email_config' => $email_config
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h2>系统设置</h2>
                <div class="user-info">
                    <span><?php echo $_SESSION['admin_username']; ?></span>
                    <a href="logout.php" class="logout-btn">退出</a>
                </div>
            </div>
            
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST" action="settings.php" enctype="multipart/form-data">
                
                <!-- 每日签到配置 -->
                <div class="settings-panel">
                    <details>
                        <summary>每日签到配置 (点击展开/折叠)</summary>
                        <div class="settings-content">
                            <table class="config-table">
                                <thead><tr><th>天数</th><th>金币</th><th>经验</th></tr></thead>
                                <tbody>
                                    <?php for($i=1; $i<=7; $i++): 
                                        $reward = $signin_config['daily_rewards'][$i] ?? ['coins'=>0, 'exp'=>0];
                                    ?>
                                    <tr>
                                        <td>第 <?php echo $i; ?> 天</td>
                                        <td><input type="number" name="daily_coins[<?php echo $i; ?>]" value="<?php echo $reward['coins']; ?>"></td>
                                        <td><input type="number" name="daily_exp[<?php echo $i; ?>]" value="<?php echo $reward['exp']; ?>"></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                            <div class="btn-group">
                                <button type="submit" name="save_game_config" class="btn btn-primary">保存配置</button>
                            </div>
                        </div>
                    </details>
                </div>

                <!-- 连续签到配置 -->
                <div class="settings-panel">
                    <details>
                        <summary>连续签到配置 (点击展开/折叠)</summary>
                        <div class="settings-content">
                            <table class="config-table">
                                <thead><tr><th>连签天数</th><th>金币</th><th>经验</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $index = 0;
                                    foreach($signin_config['streak_bonus'] as $days => $bonus): 
                                    ?>
                                    <tr>
                                        <td><input type="number" name="streak_days[<?php echo $index; ?>]" value="<?php echo $days; ?>"></td>
                                        <td><input type="number" name="streak_coins[<?php echo $index; ?>]" value="<?php echo $bonus['coins']; ?>"></td>
                                        <td><input type="number" name="streak_exp[<?php echo $index; ?>]" value="<?php echo $bonus['exp']; ?>"></td>
                                    </tr>
                                    <?php 
                                    $index++;
                                    endforeach; 
                                    ?>
                                    <tr>
                                        <td><input type="number" name="streak_days[<?php echo $index; ?>]" placeholder="新增天数"></td>
                                        <td><input type="number" name="streak_coins[<?php echo $index; ?>]" placeholder="金币"></td>
                                        <td><input type="number" name="streak_exp[<?php echo $index; ?>]" placeholder="经验"></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="btn-group">
                                <button type="submit" name="save_game_config" class="btn btn-primary">保存配置</button>
                            </div>
                        </div>
                    </details>
                </div>

                <!-- 段位配置 -->
                <div class="settings-panel">
                    <details>
                        <summary>段位配置 (点击展开/折叠)</summary>
                        <div class="settings-content">
                            <table class="config-table">
                                <thead><tr><th>段位名称</th><th>最低分</th><th>最高分</th><th>颜色</th><th>文字颜色</th></tr></thead>
                                <tbody>
                                    <?php foreach($rank_config['ranks'] as $index => $rank): ?>
                                    <tr>
                                        <td><input type="text" name="rank_name[<?php echo $index; ?>]" value="<?php echo $rank['name']; ?>"></td>
                                        <td><input type="number" name="rank_min[<?php echo $index; ?>]" value="<?php echo $rank['min_score']; ?>"></td>
                                        <td><input type="number" name="rank_max[<?php echo $index; ?>]" value="<?php echo $rank['max_score']; ?>"></td>
                                        <td>
                                            <input type="color" name="rank_color[<?php echo $index; ?>]" value="<?php echo $rank['color']; ?>" style="width: 50px; padding: 2px;">
                                        </td>
                                        <td>
                                            <input type="color" name="rank_text_color[<?php echo $index; ?>]" value="<?php echo $rank['text_color']; ?>" style="width: 50px; padding: 2px;">
                                            <input type="hidden" name="rank_bg_color[<?php echo $index; ?>]" value="<?php echo $rank['bg_color']; ?>">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="btn-group">
                                <button type="submit" name="save_game_config" class="btn btn-primary">保存配置</button>
                            </div>
                        </div>
                    </details>
                </div>

                <!-- 聊天配置 -->
                <div class="settings-panel">
                    <details>
                        <summary>聊天配置 (点击展开/折叠)</summary>
                        <div class="settings-content">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>聊天模式</label>
                                    <select name="chat_mode" class="form-control">
                                        <option value="local" <?php echo ($chat_config['mode'] ?? 'local') === 'local' ? 'selected' : ''; ?>>本地模式 (独立数据库)</option>
                                        <option value="hybrid" <?php echo ($chat_config['mode'] ?? 'local') === 'hybrid' ? 'selected' : ''; ?>>混合模式 (本地存储+跨服广播)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>API地址 (混合模式必填)</label>
                                    <input type="password" name="chat_api_url" id="chat_api_url" class="form-control" value="<?php echo htmlspecialchars($chat_config['api_url'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>API密钥 (混合模式必填)</label>
                                    <input type="password" name="chat_api_key" id="chat_api_key" class="form-control" value="<?php echo htmlspecialchars($chat_config['api_key'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>服务器名称 (当前服务器标识)</label>
                                    <input type="text" name="server_name" class="form-control" value="<?php echo htmlspecialchars($chat_config['server_name'] ?? ''); ?>" placeholder="如果不填将自动生成">
                                </div>
                            </div>
                            <div class="btn-group">
                                <button type="submit" name="save_chat_config" class="btn btn-primary">保存配置</button>
                            </div>
                        </div>
                    </details>
                </div>

<!-- Donation config UI removed -->
            </form>

            <!-- Email Config -->
            <form method="POST" action="settings.php">
                <div class="settings-panel">
                    <details>
                        <summary>邮箱配置 (点击展开/折叠)</summary>
                        <div class="settings-content">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>SMTP 服务器</label>
                                    <input type="text" name="smtp_host" class="form-control" value="<?php echo $email_config['smtp_host'] ?? ''; ?>" placeholder="smtp.example.com">
                                </div>
                                <div class="form-group">
                                    <label>SMTP 端口</label>
                                    <input type="text" name="smtp_port" class="form-control" value="<?php echo $email_config['smtp_port'] ?? '465'; ?>" placeholder="465">
                                </div>
                                <div class="form-group">
                                    <label>邮箱账号</label>
                                    <input type="text" name="smtp_username" class="form-control" value="<?php echo $email_config['smtp_username'] ?? ''; ?>" placeholder="user@example.com">
                                </div>
                                <div class="form-group">
                                    <label>邮箱密码/授权码</label>
                                    <input type="password" name="smtp_password" class="form-control" value="<?php echo $email_config['smtp_password'] ?? ''; ?>" placeholder="********">
                                </div>
                                <div class="form-group">
                                    <label>加密方式</label>
                                    <select name="smtp_encryption" class="form-control">
                                        <option value="ssl" <?php echo ($email_config['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="tls" <?php echo ($email_config['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>发件人名称</label>
                                    <input type="text" name="smtp_from_name" class="form-control" value="<?php echo $email_config['smtp_from_name'] ?? '我的进化之路'; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                                <label>测试邮箱 (保存前请先测试)</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="email" name="test_email" class="form-control" placeholder="输入接收测试邮件的邮箱地址">
                                    <button type="submit" name="test_email_config" class="btn btn-info" style="white-space: nowrap;">保存并测试</button>
                                </div>
                            </div>
                            
                            <div class="btn-group">
                                <button type="submit" name="save_email_config" class="btn btn-primary">保存配置</button>
                            </div>
                        </div>
                    </details>
                </div>
            </form>
            
        </main>
    </div>
</body>
</html>