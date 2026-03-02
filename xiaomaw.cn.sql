-- phpMyAdmin SQL Dump
-- version 4.9.5
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-01-21 17:06:48
-- 服务器版本： 5.6.50-log
-- PHP 版本： 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `jinhua3`
--

-- --------------------------------------------------------

--
-- 表的结构 `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `characters`
--

CREATE TABLE `characters` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `race` enum('bug','spirit','ghost','human','god') NOT NULL,
  `base_hp` int(11) NOT NULL,
  `base_attack` int(11) NOT NULL,
  `base_defense` int(11) NOT NULL,
  `special_field` varchar(50) NOT NULL,
  `mutate_value` int(11) NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `characters`
--

INSERT INTO `characters` (`id`, `name`, `race`, `base_hp`, `base_attack`, `base_defense`, `special_field`, `mutate_value`, `description`) VALUES
(1, '蛊王', 'bug', 850, 120, 40, '毒系精通', 15, '操控万蛊的虫族王者'),
(2, '天烈毒首', 'bug', 780, 135, 35, '剧毒爆发', 18, '浑身蕴含致命毒素的刺客'),
(3, '蚀骨虫母', 'bug', 920, 110, 45, '繁殖领域', 12, '能快速繁衍后代的母虫'),
(4, '虚空螳皇', 'bug', 750, 140, 38, '空间切割', 20, '掌握空间之力的螳螂君主'),
(5, '深渊蚁后', 'bug', 950, 105, 50, '军团统领', 10, '统领亿万蚁群的皇后'),
(6, '幻影蝶仙', 'bug', 700, 125, 32, '迷幻之舞', 22, '用美丽外表迷惑敌人的舞者'),
(7, '地穴蛛魔', 'bug', 880, 115, 48, '陷阱大师', 16, '擅长布置致命陷阱的猎手'),
(8, '熔岩甲王', 'bug', 900, 100, 55, '火焰抗性', 14, '拥有坚硬甲壳的防御者'),
(9, '风暴蜂后', 'bug', 720, 130, 36, '闪电速攻', 25, '速度极快的闪电刺客'),
(10, '腐化菌主', 'bug', 830, 118, 42, '再生领域', 17, '能够快速恢复生命的菌类主宰'),
(11, '幽魂之主', 'spirit', 900, 100, 50, '灵魂汲取', 20, '掌控灵魂之力的灵族领袖'),
(12, '元素圣灵', 'spirit', 850, 95, 55, '元素掌控', 22, '能够操控自然元素的精灵'),
(13, '梦境编织者', 'spirit', 800, 85, 48, '梦境领域', 25, '在梦境中战斗的大师'),
(14, '时光旅者', 'spirit', 780, 90, 52, '时间操控', 28, '能够影响时间流动的存在'),
(15, '星辰先知', 'spirit', 820, 88, 56, '预言能力', 24, '能够预见未来的智者'),
(16, '月光女祭司', 'spirit', 860, 92, 54, '月光治愈', 21, '借助月光之力的治疗者'),
(17, '森林守护者', 'spirit', 940, 86, 60, '自然亲和', 18, '与自然融为一体的守护者'),
(18, '虚空吟者', 'spirit', 760, 96, 46, '音波攻击', 26, '用声音战斗的艺术家'),
(19, '冰川之魂', 'spirit', 890, 84, 58, '冰霜领域', 23, '掌控极寒之力的灵魂'),
(20, '烈焰心魔', 'spirit', 810, 110, 44, '心火燃烧', 27, '用内心火焰灼烧敌人的存在'),
(21, '幽冥鬼帝', 'ghost', 800, 105, 45, '幽冥领域', 30, '统治幽冥界的帝王'),
(22, '血魂收割者', 'ghost', 750, 115, 40, '生命汲取', 32, '收割生命能量的存在'),
(23, '诅咒巫妖', 'ghost', 780, 98, 48, '诅咒大师', 28, '擅长各种诅咒法术'),
(24, '无头骑士', 'ghost', 850, 108, 50, '不死之身', 26, '拥有强大再生能力的骑士'),
(25, '怨念聚合体', 'ghost', 720, 120, 38, '怨念爆发', 35, '由无数怨念组成的怪物'),
(26, '镜中恶魔', 'ghost', 770, 102, 43, '镜像复制', 29, '能够复制敌人的恶魔'),
(27, '古堡吸血鬼', 'ghost', 830, 112, 46, '血液操控', 27, '古老的吸血鬼贵族'),
(28, '地狱三头犬', 'ghost', 880, 118, 52, '地狱火焰', 24, '来自地狱的看门犬'),
(29, '悲伤女妖', 'ghost', 690, 125, 35, '精神冲击', 33, '用哭声攻击灵魂的女妖'),
(30, '僵尸君王', 'ghost', 910, 95, 55, '瘟疫传播', 25, '能够传播瘟疫的僵尸之王'),
(31, '剑圣', 'human', 850, 110, 50, '剑术大师', 15, '人族最强的剑术大师'),
(32, '大法师', 'human', 780, 120, 42, '魔法精通', 18, '精通各类魔法的大师'),
(33, '圣骑士', 'human', 920, 95, 58, '神圣守护', 12, '信仰神圣之力的骑士'),
(34, '神射手', 'human', 800, 125, 40, '精准射击', 20, '百发百中的神射手'),
(35, '狂战士', 'human', 880, 130, 45, '狂暴状态', 16, '进入狂暴状态的战士'),
(36, '暗影刺客', 'human', 750, 135, 38, '暗杀技巧', 22, '擅长暗杀的刺客'),
(37, '帝国统帅', 'human', 900, 100, 54, '战术指挥', 14, '擅长指挥军队的统帅'),
(38, '炼金术士', 'human', 820, 115, 44, '炼金改造', 19, '能够改造物质的术士'),
(39, '龙骑士', 'human', 870, 118, 52, '龙族伙伴', 17, '与龙族签订契约的骑士'),
(40, '先知贤者', 'human', 790, 105, 46, '智慧之光', 21, '拥有超凡智慧的贤者'),
(41, '天王', 'god', 950, 130, 60, '天神下凡', 20, '神族的至高统治者'),
(42, '法相圣尊', 'god', 920, 125, 58, '法相天地', 22, '能够显现法相真身'),
(43, '雷霆神王', 'god', 880, 140, 55, '雷霆万钧', 25, '掌控雷霆之力的神王'),
(44, '光明女神', 'god', 900, 120, 62, '神圣治愈', 18, '带来光明与希望的女神'),
(45, '战争之神', 'god', 930, 135, 59, '战争狂热', 23, '执掌战争的神明'),
(46, '智慧神使', 'god', 870, 128, 56, '全知领域', 26, '拥有无限智慧的神使'),
(47, '海洋霸主', 'god', 890, 132, 57, '海洋掌控', 21, '统治海洋的神明'),
(48, '太阳神鸟', 'god', 860, 138, 54, '太阳真火', 27, '化身太阳的神鸟'),
(49, '命运编织者', 'god', 840, 122, 53, '命运操控', 29, '能够编织命运的存在'),
(50, '混沌古神', 'god', 980, 145, 63, '混沌本源', 30, '从混沌中诞生的古神');

-- --------------------------------------------------------

--
-- 表的结构 `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `message_type` enum('text','system','admin') DEFAULT 'text',
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `chat_sessions`
--

CREATE TABLE `chat_sessions` (
  `id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `last_message_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `chat_sessions`
--

INSERT INTO `chat_sessions` (`id`, `user1_id`, `user2_id`, `last_message_at`, `created_at`) VALUES
(1, 1, 1, '2026-01-21 15:36:51', '2026-01-20 16:30:18'),
(2, 1, 2, '2026-01-21 03:31:58', '2026-01-20 22:00:07'),
(3, 5, 1, '2026-01-21 15:50:53', '2026-01-21 15:32:35'),
(4, 0, 1, '2026-01-21 16:55:16', '2026-01-21 15:42:46'),
(5, -5, 1, '2026-01-21 16:15:45', '2026-01-21 15:55:40');

-- --------------------------------------------------------

--
-- 表的结构 `contact_us_config`
--

CREATE TABLE `contact_us_config` (
  `id` int(11) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT '1' COMMENT '是否在前端显示',
  `title` varchar(100) DEFAULT '联系我们' COMMENT '标题',
  `description` text COMMENT '描述信息',
  `link` varchar(255) DEFAULT NULL COMMENT '跳转链接',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `contact_us_config`
--

INSERT INTO `contact_us_config` (`id`, `is_enabled`, `title`, `description`, `link`, `created_at`, `updated_at`) VALUES
(1, 1, '联系我们', '如果您有任何问题或建议，请通过以下方式联系我们：\r\n\r\n客服QQ：\r\n客服邮箱：\r\n工作时间：周一至周五 9:00-18:00', '', '2026-01-20 16:27:12', '2026-01-21 14:06:46');

-- --------------------------------------------------------

--
-- 表的结构 `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `status` enum('pending','accepted','blocked') DEFAULT 'accepted',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `friend_requests`
--

CREATE TABLE `friend_requests` (
  `id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `message` varchar(255) DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `friend_requests`
--

INSERT INTO `friend_requests` (`id`, `from_user_id`, `to_user_id`, `status`, `message`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'pending', '', '2026-01-20 21:37:26', '2026-01-20 21:37:26');

-- --------------------------------------------------------

--
-- 表的结构 `race_change_requests`
--

CREATE TABLE `race_change_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_race` varchar(20) NOT NULL,
  `new_race` varchar(20) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `signin_records`
--

CREATE TABLE `signin_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `signin_date` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `signin_records`
--

INSERT INTO `signin_records` (`id`, `user_id`, `signin_date`, `created_at`) VALUES
(1, 1, '2026-01-20', '2026-01-20 17:40:42');

-- --------------------------------------------------------

--
-- 表的结构 `signin_streak`
--

CREATE TABLE `signin_streak` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `current_streak` int(11) DEFAULT '0',
  `longest_streak` int(11) DEFAULT '0',
  `last_signin_date` date DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `signin_streak`
--

INSERT INTO `signin_streak` (`id`, `user_id`, `current_streak`, `longest_streak`, `last_signin_date`, `updated_at`) VALUES
(1, 1, 1, 1, '2026-01-20', '2026-01-20 17:40:42');

-- --------------------------------------------------------

--
-- 表的结构 `system_announcements`
--

CREATE TABLE `system_announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('info','warning','important','update') DEFAULT 'info',
  `is_active` tinyint(1) DEFAULT '1',
  `start_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `end_time` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nickname` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `race` enum('bug','spirit','ghost','human','god') DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL COMMENT '用户头像',
  `level` int(11) DEFAULT '1',
  `exp` bigint(20) DEFAULT '0',
  `rank_score` int(11) DEFAULT '0',
  `coins` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_banned` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否被封禁，0:正常，1:封禁',
  `ban_reason` varchar(255) DEFAULT NULL COMMENT '封禁原因',
  `banned_at` datetime DEFAULT NULL COMMENT '封禁时间',
  `banned_by` int(11) DEFAULT NULL COMMENT '封禁操作人ID',
  `ban_expires_at` datetime DEFAULT NULL COMMENT '封禁到期时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `users`
--

INSERT INTO `users` (`id`, `nickname`, `username`, `password`, `race`, `avatar`, `level`, `exp`, `rank_score`, `coins`, `created_at`, `is_banned`, `ban_reason`, `banned_at`, `banned_by`, `ban_expires_at`) VALUES
(1, '彩臣', '彩臣', '$2y$10$SjGpaSf3JR7C4aGapIX4jOVqq2hO9NvCvhAREONq2d/Kv7VodVDti', 'spirit', NULL, 1, 30, 0, 15, '2026-01-20 16:26:41', 0, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `user_characters`
--

CREATE TABLE `user_characters` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `character_id` int(11) NOT NULL,
  `level` int(11) DEFAULT '1',
  `is_selected` tinyint(1) DEFAULT '0',
  `obtained_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `exp` int(11) DEFAULT '0',
  `mutate_value` int(11) NOT NULL DEFAULT '0',
  `upgrade_cost` int(11) DEFAULT '100'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `user_characters`
--

INSERT INTO `user_characters` (`id`, `user_id`, `character_id`, `level`, `is_selected`, `obtained_at`, `exp`, `mutate_value`, `upgrade_cost`) VALUES
(1, 1, 19, 1, 1, '2026-01-20 16:26:57', 0, 23, 100);

-- --------------------------------------------------------

--
-- 表的结构 `world_chat_messages`
--

CREATE TABLE `world_chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转储表的索引
--

--
-- 表的索引 `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `characters`
--
ALTER TABLE `characters`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `to_user_id` (`to_user_id`),
  ADD KEY `is_read` (`is_read`);

--
-- 表的索引 `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session` (`user1_id`,`user2_id`),
  ADD KEY `user1_id` (`user1_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- 表的索引 `contact_us_config`
--
ALTER TABLE `contact_us_config`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friendship` (`user_id`,`friend_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `friend_id` (`friend_id`);

--
-- 表的索引 `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `to_user_id` (`to_user_id`);

--
-- 表的索引 `race_change_requests`
--
ALTER TABLE `race_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- 表的索引 `signin_records`
--
ALTER TABLE `signin_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_signin` (`user_id`,`signin_date`),
  ADD KEY `user_id` (`user_id`);

--
-- 表的索引 `signin_streak`
--
ALTER TABLE `signin_streak`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- 表的索引 `system_announcements`
--
ALTER TABLE `system_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `start_time` (`start_time`),
  ADD KEY `end_time` (`end_time`);

--
-- 表的索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- 表的索引 `user_characters`
--
ALTER TABLE `user_characters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `character_id` (`character_id`);

--
-- 表的索引 `world_chat_messages`
--
ALTER TABLE `world_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `characters`
--
ALTER TABLE `characters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- 使用表AUTO_INCREMENT `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `chat_sessions`
--
ALTER TABLE `chat_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `contact_us_config`
--
ALTER TABLE `contact_us_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `race_change_requests`
--
ALTER TABLE `race_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `signin_records`
--
ALTER TABLE `signin_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `signin_streak`
--
ALTER TABLE `signin_streak`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `system_announcements`
--
ALTER TABLE `system_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `user_characters`
--
ALTER TABLE `user_characters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `world_chat_messages`
--
ALTER TABLE `world_chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 限制导出的表
--

--
-- 限制表 `race_change_requests`
--
ALTER TABLE `race_change_requests`
  ADD CONSTRAINT `race_change_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `user_characters`
--
ALTER TABLE `user_characters`
  ADD CONSTRAINT `user_characters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_characters_ibfk_2` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
