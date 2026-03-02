<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的进化之路 - 登录</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background: linear-gradient(to bottom, #1a1a2e, #16213e);
            color: #fff;
            margin: 0;
            padding: 20px;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            width: 90%;
            max-width: 400px;
            background: rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #4ecca3;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        .tab-container {
            margin-bottom: 20px;
        }
        .tab {
            display: flex;
            border-bottom: 1px solid #4ecca3;
        }
        .tab button {
            flex: 1;
            background: none;
            border: none;
            color: #fff;
            padding: 12px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .tab button.active {
            color: #4ecca3;
            border-bottom: 3px solid #4ecca3;
            font-weight: bold;
        }
        .tab button:hover:not(.active) {
            background: rgba(78, 204, 163, 0.1);
        }
        .form-container {
            display: none;
        }
        .form-container.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px;
            margin: 8px 0;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        input:focus {
            outline: none;
            border-color: #4ecca3;
            box-shadow: 0 0 0 2px rgba(78, 204, 163, 0.3);
            background: rgba(255, 255, 255, 0.2);
        }
        input::placeholder {
            color: #ccc;
        }
        .input-group {
            position: relative;
            margin-bottom: 5px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin: 10px 0;
            color: #ccc;
            font-size: 14px;
            cursor: pointer;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #4ecca3;
        }
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4ecca3, #3db393);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            font-weight: bold;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(78, 204, 163, 0.4);
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(78, 204, 163, 0.6);
        }
        .submit-btn:active {
            transform: translateY(1px);
        }
        .error-message {
            color: #ff6b6b;
            text-align: center;
            margin-top: 15px;
            min-height: 20px;
            font-size: 14px;
        }
        .info-text {
            text-align: center;
            margin-top: 20px;
            color: #aaa;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>我的进化之路</h1>
        
        <div class="tab-container">
            <div class="tab">
                <button class="active" onclick="switchTab('login')">登录</button>
                <button onclick="switchTab('register')">注册</button>
            </div>
        </div>
        
        <div id="login-form" class="form-container active">
            <div class="input-group">
                <input type="text" id="login-username" placeholder="请输入账号">
            </div>
            <div class="input-group">
                <input type="password" id="login-password" placeholder="请输入密码">
            </div>
            <button class="submit-btn" onclick="login()">立即开始</button>
            <div class="error-message" id="login-error"></div>
        </div>
        
        <div id="register-form" class="form-container">
            <div class="input-group">
                <input type="text" id="reg-nickname" placeholder="给自己起个名字">
            </div>
            <div class="input-group">
                <input type="text" id="reg-username" placeholder="设置账号 (至少4位)">
            </div>
            <div class="input-group">
                <input type="password" id="reg-password" placeholder="设置密码 (至少6位)">
            </div>
            <button class="submit-btn" onclick="register()">创建角色</button>
            <div class="error-message" id="register-error"></div>
        </div>
        
        <div class="info-text">
            &copy; 我的进化之路 - 开启你的进化之旅
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        $(document).ready(function() {
            // 检查是否有记住的账号密码 (优先使用 Cookie，兼容微信环境)
            const savedUsername = getCookie('evolution_username');
            const savedPassword = getCookie('evolution_password');
            
            if (savedUsername && savedPassword) {
                $('#login-username').val(savedUsername);
                $('#login-password').val(savedPassword);
                $('#remember-me').prop('checked', true);
            }
        });

        function switchTab(tab) {
            $('.tab button').removeClass('active');
            $('.form-container').removeClass('active');
            
            if (tab === 'login') {
                $('.tab button:first-child').addClass('active');
                $('#login-form').addClass('active');
            } else {
                $('.tab button:last-child').addClass('active');
                $('#register-form').addClass('active');
            }
            
            // 清空错误信息
            $('.error-message').text('');
        }
        
        function login() {
            const username = $('#login-username').val();
            const password = $('#login-password').val();
            
            if (!username || !password) {
                $('#login-error').text('请输入账号和密码');
                return;
            }
            
            $.post('api/login.php', {
                username: username,
                password: password
            }, function(res) {
                try {
                    const data = JSON.parse(res);
                    if (data.success) {
                        // 处理记住密码
                        if ($('#remember-me').is(':checked')) {
                            localStorage.setItem('evolution_username', username);
                            localStorage.setItem('evolution_password', password);
                        } else {
                            localStorage.removeItem('evolution_username');
                            localStorage.removeItem('evolution_password');
                        }

                        // 无论是否有种族，统一跳转到dashboard.php
                        // dashboard.php 内部会根据是否有种族来决定显示游戏界面还是选择种族界面
                        window.location.href = 'dashboard.php';
                    } else {
                        $('#login-error').text(data.message);
                    }
                } catch (e) {
                    $('#login-error').text('系统错误，请稍后再试');
                    console.error(res);
                }
            });
        }
        
        function register() {
            const nickname = $('#reg-nickname').val();
            const username = $('#reg-username').val();
            const password = $('#reg-password').val();
            
            if (!nickname || !username || !password) {
                $('#register-error').text('请填写所有字段');
                return;
            }
            
            if (username.length < 4) {
                $('#register-error').text('账号至少4位');
                return;
            }
            
            if (password.length < 6) {
                $('#register-error').text('密码至少6位');
                return;
            }
            
            $.post('api/register.php', {
                nickname: nickname,
                username: username,
                password: password
            }, function(res) {
                try {
                    const data = JSON.parse(res);
                    if (data.success) {
                        alert('注册成功，即将进入游戏！');
                        // 自动登录后跳转到dashboard.php (种族选择已集成在dashboard中)
                        window.location.href = 'dashboard.php';
                    } else {
                        $('#register-error').text(data.message);
                    }
                } catch (e) {
                    $('#register-error').text('系统错误，请稍后再试');
                    console.error(res);
                }
            });
        }
    </script>
</body>
</html>