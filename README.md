# 我的进化之路 (jinhua)

> 一个使用 PHP/PDO 和 MySQL 构建的轻量级网页游戏框架，包含用户系统、抽卡、战斗、社交、聊天和后台管理等功能。

本仓库为“我的进化之路”游戏的源码。项目以简洁的前端配合 PHP 后端实现，是一个适合搭建私服、学习和二次开发的小型游戏项目。

## 🚀 主要特性

- 用户注册／登录、角色绑定、种族选择
- 仓库式角色抽卡（十连保底、重复补偿）
- 角色升级、金币与经验管理
- 好友系统、私信与聊天会话
- 世界聊天与公告功能（支持外部聊天服务器）
- 日常签到奖励与连击奖励
- 排行榜、封禁榜显示
- 管理后台：用户、角色、公告、邮件、签到、封禁、聊天室等
- API 目录提供 Ajax 接口，可供移动端或单页应用调用

## 🧩 技术栈

- 后端：PHP (PDO)
- 数据库：MySQL / MariaDB
- 前端：HTML5 + CSS3 + jQuery
- 轻量配置文件 (PHP 数组/返回)

## 📁 目录结构概览

```
/ (项目根)
├─ admin/             管理后台页面和逻辑
├─ api/               前端/客户端调用的 REST 风格接口
├─ uploads/           用户头像、资源上传目录
├─ config.php         全局配置（数据库、会话、邮件、小游戏配置）
├─ game_config.php    游戏数值可选覆盖文件
├─ email_config.php   SMTP 设置（示例）
├─ xiaomaw.cn.sql     数据库结构与示例数据导出
├─ *.php              前端页面（登录、聊天、角色、战斗等）
└─ README.md          项目说明（你在读的这份）
```

## 🛠 环境要求

1. PHP 7.0+（推荐 7.4/8.x）
2. MySQL 5.6+ / MariaDB 同样适用
3. Web 服务器（Apache、Nginx 或 PHP 内置服务器）
4. 可选：SMTP 服务用于邮件通知，聊天服务器接口

## 📦 安装步骤

1. 克隆仓库到 Web 根目录：
   ```bash
git clone https://github.com/zxjRebel/jinhua.git
cd jinhua
```
2. 创建数据库并导入结构：
   ```sql
   CREATE DATABASE jinhua3 DEFAULT CHARACTER SET utf8mb4;
   USE jinhua3;
   SOURCE /path/to/xiaomaw.cn.sql;
   ```
3. 编辑 `config.php` 填写数据库连接、初始会话设置等。
4. 根据需要创建或编辑以下可选配置文件：
   - `email_config.php`：覆盖邮件参数（SMTP 主机、端口、账号、密码）
   - `game_config.php`：覆盖默认签到奖励、段位定义等数值
   - `chat_config.php`：制定外部聊天室 API 地址和密钥
5. 如果想启用封禁与管理员后台：
   ```bash
   php run_ban_setup.php
   # 或者访问 http://yourserver/setup_ban_system.php
   ```
   该脚本会自动向 `users` 表添加封禁字段并创建默认管理员账号（`admin`/`password` 哈希）。
6. 载入站点，注册一个账号，选择种族并开始游戏。

> 💡 **提示**: 可以用 `php -S localhost:8000` 在根目录启动内置服务器进行本地开发。

## 🔧 管理后台

访问 `/admin/login.php` 输入管理员用户名/密码。后台提供：

- 用户管理、封禁与踢出
- 角色数据编辑与抽卡调整
- 公告、世界消息发布
- 邮件发送测试与 SMTP 配置
- 签到奖励、段位设置
- 聊天审计、世界聊天管理

管理员表位于 `admins`，初始密码为 `password` 的 bcrypt 哈希。

## 📡 API 端点

`/api/` 下的文件为 Ajax 脚本，常见接口如下：

| 路径 | 功能 |
|------|------|
| `register.php` | 用户注册 |
| `login.php` | 登录 |
| `logout.php` | 注销 |
| `get_world_messages.php` | 拉取世界聊天 |
| `send_world_message.php` | 发送世界聊天 |
| `gacha_draw.php` | 执行抽卡 |
| `battle.php` | 发起对战 |
| `upgrade_character.php` | 升级角色 |
| … | 其他社交/好友/消息接口 |

这些接口返回 JSON，前端通过 jQuery 的 `$.ajax` 进行调用。

## ⚙️ 自定义和扩展

- 修改 `game_config.php` 来调整奖励、段位、签到等；后台同样提供可视化编辑。
- 增加/修改角色请在 `characters` 数据表中添加条目。
- 聊天功能依赖外部 API，可替换为任意支持的服务器或直接在本地扩展。
- 前端页面统一使用 jQuery，可改写为 Vue/React 单页应用调用 API。

## 📋 注意事项

- 为生产部署，请确保 PHP 错误输出关闭并开启适当的权限控制。
- 对用户输入仅作基本验证，建议在重构时加强过滤与 CSRF 防护。
- `uploads/` 目录要设为可写但不可直接浏览。
- 充分备份数据库，`xiamaw.cn.sql` 只是示例数据。

## 📄 许可证

本项目未指定许可证，默认遵循 **MIT 许可（如需）** 或自行考虑开源条款。

---

欢迎按照自己的想法修改、学习或二次开发，如果有问题可以查看注释代码或联系原作者。祝你在“进化之路”中取得成功！
