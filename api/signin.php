<?php
// api/signin.php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

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
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '今天已经签到过了']);
        exit;
    }

    $pdo->beginTransaction();

    // 1. 记录签到
    $stmt = $pdo->prepare("INSERT INTO signin_records (user_id, signin_date) VALUES (?, ?)");
    $stmt->execute([$user_id, $today]);

    // 2. 获取连续签到信息
    $stmt = $pdo->prepare("SELECT * FROM signin_streak WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $streak_info = $stmt->fetch(PDO::FETCH_ASSOC);

    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $current_streak = 1;

    if ($streak_info) {
        // 检查是否是连续签到
        if ($streak_info['last_signin_date'] === $yesterday) {
            $current_streak = $streak_info['current_streak'] + 1;
        }
        
        // 更新连续签到记录
        $stmt = $pdo->prepare("
            UPDATE signin_streak 
            SET current_streak = ?, last_signin_date = ?, 
                longest_streak = GREATEST(longest_streak, ?),
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$current_streak, $today, $current_streak, $user_id]);
    } else {
        // 首次签到
        $stmt = $pdo->prepare("
            INSERT INTO signin_streak (user_id, current_streak, longest_streak, last_signin_date) 
            VALUES (?, 1, 1, ?)
        ");
        $stmt->execute([$user_id, $today]);
    }

    // 3. 计算奖励
    $day_of_week = (date('N') - 1) % 7 + 1; // 1-7 对应周一到周日
    $base_reward = $signin_config['daily_rewards'][$day_of_week];
    
    $rewards = [
        'coins' => $base_reward['coins'],
        'exp' => $base_reward['exp']
    ];

    // 4. 连续签到额外奖励
    foreach ($signin_config['streak_bonus'] as $streak_days => $bonus) {
        if ($current_streak >= $streak_days && $current_streak % $streak_days === 0) {
            $rewards['coins'] += $bonus['coins'];
            $rewards['exp'] += $bonus['exp'];
            $rewards['streak_bonus'] = $streak_days . '天连续签到奖励！';
        }
    }

    // 5. 发放奖励
    $stmt = $pdo->prepare("UPDATE users SET coins = coins + ?, exp = exp + ? WHERE id = ?");
    $stmt->execute([$rewards['coins'], $rewards['exp'], $user_id]);

    // 6. 获取更新后的用户数据
    $stmt = $pdo->prepare("SELECT coins, exp, level FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    // 更新session
    $_SESSION['coins'] = $user_data['coins'];
    $_SESSION['level'] = $user_data['level'];

    echo json_encode([
        'success' => true,
        'message' => '签到成功！',
        'rewards' => $rewards,
        'streak' => $current_streak,
        'user_data' => [
            'coins' => $user_data['coins'],
            'exp' => $user_data['exp'],
            'level' => $user_data['level']
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '签到失败: ' . $e->getMessage()]);
}
?>