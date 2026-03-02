<?php
// test_email_config.php
// 测试邮箱配置是否正常加载

include 'config.php';

echo "<h1>邮箱配置测试</h1>";
echo "<h2>当前邮箱配置：</h2>";
echo "<pre>";
print_r($email_config);
echo "</pre>";

// 测试外部配置文件是否存在
if (file_exists('email_config.php')) {
    echo "<h2>外部配置文件存在</h2>";
    $external_config = include 'email_config.php';
    echo "<pre>";
    print_r($external_config);
    echo "</pre>";
} else {
    echo "<h2>外部配置文件不存在，使用默认配置</h2>";
}

// 测试配置是否正确合并
if ($email_config['smtp_from'] !== '') {
    echo "<h2>配置合并成功，发件人地址：" . $email_config['smtp_from'] . "</h2>";
} else {
    echo "<h2>配置合并失败，发件人地址为空</h2>";
}
?>