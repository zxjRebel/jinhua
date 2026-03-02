<?php
// 直接执行封禁系统设置的PHP脚本
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';

try {
    echo "开始设置封禁系统...\n\n";
    
    // 为用户表添加封禁相关字段
    $sql = "ALTER TABLE `users` 
    ADD COLUMN `is_banned` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '是否被封禁，0:正常，1:封禁' AFTER `coins`,
    ADD COLUMN `ban_reason` VARCHAR(255) NULL COMMENT '封禁原因' AFTER `is_banned`,
    ADD COLUMN `banned_at` DATETIME NULL COMMENT '封禁时间' AFTER `ban_reason`,
    ADD COLUMN `banned_by` INT(11) NULL COMMENT '封禁操作人ID' AFTER `banned_at`,
    ADD COLUMN `ban_expires_at` DATETIME NULL COMMENT '封禁到期时间' AFTER `banned_by`;
    ";
    $pdo->exec($sql);
    echo "1. 成功添加封禁相关字段\n";
    
    // 创建管理员表（如果不存在）
    $sql = "CREATE TABLE IF NOT EXISTS `admins` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `username` VARCHAR(50) NOT NULL,
      `password` VARCHAR(255) NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sql);
    echo "2. 成功创建管理员表（如果不存在）\n";
    
    // 添加默认管理员（如果不存在）
    $sql = "INSERT INTO `admins` (`username`, `password`) 
    VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi') 
    ON DUPLICATE KEY UPDATE id=id;
    ";
    $pdo->exec($sql);
    echo "3. 成功添加默认管理员（如果不存在）\n\n";
    
    echo "封禁系统设置完成！\n";
    echo "现在可以开始使用完整的用户封禁功能。\n";
    
} catch (PDOException $e) {
    echo "错误：" . $e->getMessage() . "\n";
    echo "请检查数据库连接和SQL语句是否正确。\n";
    echo "如果您的环境不支持PHP命令行，您可以直接将setup_ban_system.sql文件中的SQL语句复制到MySQL客户端执行。\n";
}
?>