<?php
// api/get_announcements.php
include '../config.php';

header('Content-Type: application/json');

try {
    // 获取当前有效的公告（未过期且活跃的）
    $stmt = $pdo->prepare("
        SELECT id, title, content, type, start_time, end_time
        FROM system_announcements 
        WHERE is_active = 1 
        AND start_time <= NOW() 
        AND (end_time IS NULL OR end_time >= NOW())
        ORDER BY 
            CASE type 
                WHEN 'important' THEN 1
                WHEN 'warning' THEN 2
                WHEN 'update' THEN 3
                ELSE 4
            END,
            created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'announcements' => $announcements
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'announcements' => []
    ]);
}
?>