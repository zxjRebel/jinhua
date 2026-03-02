<?php
// api/logout.php
include '../config.php';

// 清除所有session变量
$_SESSION = array();

// 删除session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 销毁session
session_destroy();

// 跳转到登录页
header('Location: ../index.php');
exit;
?>