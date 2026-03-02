<?php
// api/get_signin_info.php
include '../config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    // 检查今天是否已经签到
    $stmt = $pdo->prepare("SELECT id FROM signin_records WHERE user_id = ? AND signin_date = ?");
    $stmt->execute([$user_id, $today]);
    $signed_today = (bool)$stmt->fetch();

    // 获取连续签到信息
    $stmt = $pdo->prepare("SELECT * FROM signin_streak WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $streak_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // 获取本月签到记录
    $first_day_of_month = date('Y-m-01');
    $stmt = $pdo->prepare("
        SELECT signin_date 
        FROM signin_records 
        WHERE user_id = ? AND signin_date >= ?
        ORDER BY signin_date
    ");
    $stmt->execute([$user_id, $first_day_of_month]);
    $month_records = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 计算本周奖励预览
    $rewards_preview = [];
    for ($i = 1; $i <= 7; $i++) {
        $rewards_preview[] = $signin_config['daily_rewards'][$i];
    }

    echo json_encode([
        'success' => true,
        'signed_today' => $signed_today,
        'streak_info' => $streak_info ?: ['current_streak' => 0, 'longest_streak' => 0],
        'month_records' => $month_records,
        'rewards_preview' => $rewards_preview
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '获取签到信息失败: ' . $e->getMessage()]);
}
?>