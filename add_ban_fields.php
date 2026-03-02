<?php
// 添加用户封禁相关字段的PHP脚本
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';

try {
    echo "开始添加用户封禁相关字段...<br><br>";
    
    // 添加封禁相关字段
    $sql = "ALTER TABLE `users` ADD `is_banned` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '是否被封禁，0:正常，1:封禁'";
    $pdo->exec($sql);
    echo "1. 成功添加 is_banned 字段<br>";
    
    $sql = "ALTER TABLE `users` ADD `ban_reason` VARCHAR(255) NULL COMMENT '封禁原因'";
    $pdo->exec($sql);
    echo "2. 成功添加 ban_reason 字段<br>";
    
    $sql = "ALTER TABLE `users` ADD `banned_at` DATETIME NULL COMMENT '封禁时间'";
    $pdo->exec($sql);
    echo "3. 成功添加 banned_at 字段<br>";
    
    $sql = "ALTER TABLE `users` ADD `banned_by` INT(11) NULL COMMENT '封禁操作人ID'";
    $pdo->exec($sql);
    echo "4. 成功添加 banned_by 字段<br>";
    
    $sql = "ALTER TABLE `users` ADD `ban_expires_at` DATETIME NULL COMMENT '封禁到期时间'";
    $pdo->exec($sql);
    echo "5. 成功添加 ban_expires_at 字段<br><br>";
    
    // 创建管理员表（如果不存在）
    $sql = "CREATE TABLE IF NOT EXISTS `admins` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `username` VARCHAR(50) NOT NULL,
      `password` VARCHAR(255) NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "6. 成功创建 admins 表（如果不存在）<br>";
    
    // 添加默认管理员（如果不存在）
    $sql = "INSERT INTO `admins` (`username`, `password`) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi') ON DUPLICATE KEY UPDATE id=id";
    $pdo->exec($sql);
    echo "7. 成功添加默认管理员（如果不存在）<br><br>";
    
    echo "所有操作完成！<br>";
    echo "现在可以开始修改 users.php 文件，添加用户封禁功能。";
    
} catch (PDOException $e) {
    echo "错误：" . $e->getMessage() . "<br>";
    echo "请检查数据库连接和SQL语句是否正确。";
}
?>