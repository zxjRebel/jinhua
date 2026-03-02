<?php
// 测试管理页面的错误
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Testing admin pages...<br><br>";

// 测试用户管理页面
echo "Testing users.php...<br>";
try {
    ob_start();
    include 'admin/users.php';
    $output = ob_get_clean();
    echo "users.php loaded successfully<br><br>";
} catch (Exception $e) {
    echo "Error in users.php: " . $e->getMessage() . "<br><br>";
}

// 测试角色管理页面
echo "Testing characters.php...<br>";
try {
    ob_start();
    include 'admin/characters.php';
    $output = ob_get_clean();
    echo "characters.php loaded successfully<br><br>";
} catch (Exception $e) {
    echo "Error in characters.php: " . $e->getMessage() . "<br><br>";
}