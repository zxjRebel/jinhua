<?php
include 'config.php';

// Check if user is logged in (optional, but good for security)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    die("Please login first.");
}

try {
    // Enable insertion of ID 0
    $pdo->exec("SET sql_mode='NO_AUTO_VALUE_ON_ZERO'");
    
    // Check if user 0 exists
    $stmt = $pdo->query("SELECT id FROM users WHERE id = 0");
    if ($stmt->fetch()) {
        echo "System user (ID 0) already exists.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (id, nickname, username, password, race, level, created_at) VALUES (0, '系统通知', 'system', 'system_placeholder', 'god', 999, NOW())");
        $stmt->execute();
        echo "System user (ID 0) created successfully.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
