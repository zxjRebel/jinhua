<?php
include '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            cs.id as session_id,
            CASE 
                WHEN cs.user1_id = ? THEN cs.user2_id
                ELSE cs.user1_id
            END as friend_id,
            COALESCE(u.nickname, '系统通知') as nickname,
            COALESCE(u.race, 'system') as race,
            COALESCE(u.level, 999) as level,
            cs.last_message_at,
            (SELECT COUNT(*) FROM chat_messages cm WHERE cm.session_id = cs.id AND cm.to_user_id = ? AND cm.is_read = 0) as unread_count,
            (SELECT content FROM chat_messages cm WHERE cm.session_id = cs.id ORDER BY cm.created_at DESC LIMIT 1) as last_message,
            (SELECT message_type FROM chat_messages cm WHERE cm.session_id = cs.id ORDER BY cm.created_at DESC LIMIT 1) as last_message_type
        FROM chat_sessions cs
        LEFT JOIN users u ON (
            (cs.user1_id = ? AND u.id = cs.user2_id) OR 
            (cs.user2_id = ? AND u.id = cs.user1_id)
        )
        WHERE cs.user1_id = ? OR cs.user2_id = ?
        ORDER BY cs.last_message_at DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total unread
    $total_unread = 0;
    foreach ($sessions as $s) {
        $total_unread += $s['unread_count'];
    }

    echo json_encode([
        'success' => true, 
        'sessions' => $sessions, 
        'total_unread' => $total_unread
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
