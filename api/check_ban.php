<?php
// 检查用户是否被封禁的通用函数
function checkUserBanStatus($pdo, $user_id) {
    try {
        // 查询用户的封禁状态
        $stmt = $pdo->prepare("SELECT is_banned, ban_reason, ban_expires_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['is_banned']) {
            // 检查封禁是否已到期
            if ($user['ban_expires_at'] && strtotime($user['ban_expires_at']) < time()) {
                // 封禁已到期，自动解封
                $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL, ban_expires_at = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                return ['banned' => false];
            } else {
                // 用户仍在封禁期内
                return [
                    'banned' => true,
                    'message' => '您的账号已被封禁，原因：' . $user['ban_reason']
                ];
            }
        }
        
        return ['banned' => false];
    } catch (PDOException $e) {
        return [
            'banned' => true,
            'message' => '服务器错误：' . $e->getMessage()
        ];
    }
}
?>