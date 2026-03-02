<?php
// config.php
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'xiaomaw.cn';
$username = 'www.xiaomaw.cn';
$password = 'www.xiaomaw.cn';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 启动session
session_start();

// 邮箱配置
// 默认配置
$default_email_config = [
    'smtp_host' => 'smtp.qq.com', // SMTP服务器地址
    'smtp_port' => 465, // SMTP端口
    'smtp_username' => '', // 发件人邮箱
    'smtp_password' => '', // 发件人密码或授权码
    'smtp_encryption' => 'ssl', // 加密方式
    'smtp_from' => '', // 发件人邮箱
    'smtp_from_name' => '我的进化之路' // 发件人名称
];

// 加载外部邮箱配置（如果存在）
$email_config = $default_email_config;
$config_path = __DIR__ . '/email_config.php';
if (file_exists($config_path)) {
    $external_email_config = include $config_path;
    if (is_array($external_email_config)) {
        $email_config = array_merge($default_email_config, $external_email_config);
    }
}

// Donation config removed

// 签到配置
$default_signin_config = [
    'daily_rewards' => [
        1 => ['coins' => 30, 'exp' => 10],    // 第1天
        2 => ['coins' => 40, 'exp' => 30],    // 第2天  
        3 => ['coins' => 50, 'exp' => 50],   // 第3天
        4 => ['coins' => 60, 'exp' => 80],   // 第4天
        5 => ['coins' => 70, 'exp' => 120],   // 第5天
        6 => ['coins' => 80, 'exp' => 150],   // 第6天
        7 => ['coins' => 150, 'exp' => 300],   // 第7天（周奖励）
    ],
    'streak_bonus' => [
        7 => ['coins' => 200, 'exp' => 100],  // 连续7天额外奖励
        30 => ['coins' => 2500, 'exp' => 500] // 连续30天额外奖励
    ]
];

// 段位配置 - 优化为更适合星级显示的积分范围
$default_rank_config = [
    'ranks' => [
        [
            'name' => '平凡',
            'min_score' => 0,
            'max_score' => 199,  // 每40分一星
            'color' => '#6c757d',
            'bg_color' => 'rgba(108, 117, 125, 0.2)',
            'text_color' => '#ffffff'
        ],
        [
            'name' => '精英',
            'min_score' => 200,
            'max_score' => 599,  // 每80分一星
            'color' => '#28a745',
            'bg_color' => 'rgba(40, 167, 69, 0.2)',
            'text_color' => '#ffffff'
        ],
        [
            'name' => '史诗',
            'min_score' => 600,
            'max_score' => 1199, // 每120分一星
            'color' => '#6f42c1',
            'bg_color' => 'rgba(111, 66, 193, 0.2)',
            'text_color' => '#ffffff'
        ],
        [
            'name' => '传说',
            'min_score' => 1200,
            'max_score' => 1999, // 每160分一星
            'color' => '#fd7e14',
            'bg_color' => 'rgba(253, 126, 20, 0.2)',
            'text_color' => '#1a1a2e'
        ],
        [
            'name' => '神话',
            'min_score' => 2000,
            'max_score' => 2999, // 每200分一星
            'color' => '#dc3545',
            'bg_color' => 'rgba(220, 53, 69, 0.2)',
            'text_color' => '#ffffff'
        ],
        [
            'name' => '超神',
            'min_score' => 3000,
            'max_score' => 999999, // 每100分一星
            'color' => '#e83e8c',
            'bg_color' => 'rgba(232, 62, 140, 0.2)',
            'text_color' => '#ffffff'
        ]
    ]
];

// 加载外部游戏配置
$game_config_path = __DIR__ . '/game_config.php';
if (file_exists($game_config_path)) {
    $game_config = include $game_config_path;
    $signin_config = $game_config['signin_config'] ?? $default_signin_config;
    $rank_config = $game_config['rank_config'] ?? $default_rank_config;
} else {
    $signin_config = $default_signin_config;
    $rank_config = $default_rank_config;
}

// 加载聊天配置
$default_chat_config = [
    'mode' => 'hybrid',
    'api_url' => 'http://jinhuaapi.caichen8.cn/api.php',
    'api_key' => 'evolution_road_chat_secret',
    'server_name' => '一区'
];
$chat_config_path = __DIR__ . '/chat_config.php';

if (file_exists($chat_config_path)) {
    $chat_config = include $chat_config_path;
}

// 如果配置不存在，或者server_name为空，或者域名发生变化（二次识别），则尝试自动获取
$current_domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
$should_update_config = false;

if (!isset($chat_config)) {
    $chat_config = $default_chat_config;
    $should_update_config = true;
} elseif (empty($chat_config['server_name'])) {
    $should_update_config = true;
} elseif (!isset($chat_config['bound_domain']) || $chat_config['bound_domain'] !== $current_domain) {
    // 域名发生变化，需要二次识别
    $should_update_config = true;
}

if ($should_update_config) {
    // 自动生成唯一的服务器名称
    $auto_name = isset($chat_config['server_name']) && !empty($chat_config['server_name']) ? $chat_config['server_name'] : '一区'; // 默认保持原有或fallback
    
    // 尝试请求中央服务器分配区服名称
    $api_url = $chat_config['api_url'] ?? $default_chat_config['api_url'];
    $api_key = $chat_config['api_key'] ?? $default_chat_config['api_key'];

    if (!empty($api_url)) {
        try {
            $reg_url = $api_url . '?action=register_server&api_key=' . $api_key . '&domain=' . urlencode($current_domain);
            
            // 使用 curl 获取
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $reg_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                $result = json_decode($response, true);
                if ($result && ($result['success'] ?? false) && !empty($result['server_name'])) {
                    $auto_name = $result['server_name'];
                } else {
                    // 记录错误以便调试
                    error_log("Chat Server Auto-Assign Failed: " . ($result['message'] ?? 'Unknown error') . " | Response: " . substr($response, 0, 100));
                }
            } else {
                error_log("Chat Server Auto-Assign HTTP Error: " . $http_code);
            }
        } catch (Exception $e) {
            // 忽略错误，使用默认
            error_log("Chat Server Auto-Assign Exception: " . $e->getMessage());
        }
    }

    $chat_config['server_name'] = $auto_name;
    $chat_config['bound_domain'] = $current_domain; // 绑定当前域名
    
    // 尝试保存自动生成的配置
    try {
        file_put_contents($chat_config_path, "<?php\nreturn " . var_export($chat_config, true) . ";\n");
    } catch (Exception $e) {
        // 忽略写入权限错误
    }
}

// 获取详细的段位星数信息
function get_rank_info($score) {
    global $rank_config;
    
    $score = max(0, $score);
    
    foreach ($rank_config['ranks'] as $rank) {
        if ($score >= $rank['min_score'] && $score <= $rank['max_score']) {
            // 计算详细的星级信息
            if ($rank['name'] === '超神') {
                // 超神段位：每100积分一颗星
                $stars = floor(($score - $rank['min_score']) / 100) + 1;
                $current_progress = ($score - $rank['min_score']) % 100;
                $max_progress = 100;
                $max_stars = 999; // 理论上无限
            } else {
                // 其他段位：1-5星
                $range = $rank['max_score'] - $rank['min_score'] + 1;
                $progress = $score - $rank['min_score'];
                $stars_per_level = 5; // 每个段位5星
                $points_per_star = $range / $stars_per_level;
                
                $stars = floor($progress / $points_per_star) + 1;
                $stars = min($stars_per_level, max(1, $stars));
                $current_progress = $progress % $points_per_star;
                $max_progress = $points_per_star;
                $max_stars = $stars_per_level;
            }
            
            return [
                'name' => $rank['name'],
                'color' => $rank['color'],
                'bg_color' => $rank['bg_color'],
                'text_color' => $rank['text_color'],
                'score' => $score,
                'min_score' => $rank['min_score'],
                'max_score' => $rank['max_score'],
                'stars' => $stars,
                'max_stars' => $max_stars,
                'current_progress' => $current_progress,
                'max_progress' => $max_progress,
                'progress_percent' => ($current_progress / $max_progress) * 100,
                'display_text' => $rank['name'] . $stars . '星'
            ];
        }
    }
    
    // 默认返回最低段位
    return get_rank_info(0);
}

// 获取下一个段位信息
function get_next_rank_info($current_score) {
    global $rank_config;
    
    $current_rank = get_rank_info($current_score);
    $current_index = array_search($current_rank['name'], array_column($rank_config['ranks'], 'name'));
    
    if ($current_index < count($rank_config['ranks']) - 1) {
        $next_rank = $rank_config['ranks'][$current_index + 1];
        $needed_score = $next_rank['min_score'] - $current_score;
        
        return [
            'name' => $next_rank['name'],
            'color' => $next_rank['color'],
            'needed_score' => max(0, $needed_score),
            'min_score' => $next_rank['min_score']
        ];
    }
    
    // 已经是最高段位
    return null;
}
?>
