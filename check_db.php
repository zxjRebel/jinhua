<?php
include 'config.php';

function checkTable($pdo, $table) {
    echo "Table: $table\n";
    $stmt = $pdo->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($columns);
    echo "\n";
}

try {
    checkTable($pdo, 'user_characters');
    checkTable($pdo, 'characters');
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
