<?php
// admin/auth.php
// 管理员认证检查

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}