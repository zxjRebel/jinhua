<?php
// admin/login.php
include '../config.php';

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否已登录，如果是则重定向到仪表盘
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// 处理登录表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 简单的管理员认证（实际项目中应该使用更安全的方式）
    // 从数据库验证管理员
    
    // 检查是否有任何管理员存在，如果没有，创建一个默认的
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $default_username = 'admin';
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute([$default_username, $default_password]);
    }
    
    $stmt = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin['password'])) {
        // 登录成功，设置会话
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: index.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录</title>
    <style>
        :root {
            --primary-color: #667eea;
            --primary-hover: #764ba2;
            --bg-gradient-start: #1a1a2e;
            --bg-gradient-end: #16213e;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-color: #fff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, var(--bg-gradient-start), var(--bg-gradient-end));
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-color);
        }
        
        .login-container {
            background: var(--glass-bg);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            width: 100%;
            max-width: 400px;
        }
        
        .login-container h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #fff;
            font-weight: 300;
            letter-spacing: 2px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            font-size: 16px;
            color: #fff;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(0, 0, 0, 0.3);
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
        }
        
        .error-message {
            background-color: rgba(220, 53, 69, 0.2);
            color: #ff6b6b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid rgba(220, 53, 69, 0.3);
            font-size: 14px;
        }
        
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>管理员登录</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">用户名:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <label class="checkbox-group">
                <input type="checkbox" id="remember-me"> 记住账号密码
            </label>

            <button type="submit" class="login-btn">登录</button>
        </form>
    </div>

    <script>
        // Cookie 操作辅助函数
        function setCookie(cname, cvalue, exdays) {
            const d = new Date();
            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
            let expires = "expires="+d.toUTCString();
            document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
        }

        function getCookie(cname) {
            let name = cname + "=";
            let ca = document.cookie.split(';');
            for(let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                    return c.substring(name.length, c.length);
                }
            }
            return "";
        }

        function deleteCookie(cname) {
             document.cookie = cname + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        }

        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const rememberMeCheckbox = document.getElementById('remember-me');
            const loginForm = document.querySelector('form');

            // Load saved credentials from Cookies
            const savedUsername = getCookie('admin_username_cache');
            const savedPassword = getCookie('admin_password_cache');

            if (savedUsername && savedPassword) {
                usernameInput.value = savedUsername;
                passwordInput.value = savedPassword;
                rememberMeCheckbox.checked = true;
            }

            // Save credentials on submit
            loginForm.addEventListener('submit', function() {
                if (rememberMeCheckbox.checked) {
                    setCookie('admin_username_cache', usernameInput.value, 30);
                    setCookie('admin_password_cache', passwordInput.value, 30);
                } else {
                    deleteCookie('admin_username_cache');
                    deleteCookie('admin_password_cache');
                }
            });
        });
    </script>
</body>
</html>