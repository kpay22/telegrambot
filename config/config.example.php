<?php
/**
 * 配置文件示例
 * 
 * 复制此文件为 config.php 并修改相应配置
 * 
 * @package TelegramAccountingBot
 * @author Your Name
 * @version 1.1.0
 * @since 2024-01-01
 */

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'telegram_accounting_bot');
define('DB_USER', 'bot_user');
define('DB_PASS', 'your_password_here');
define('DB_CHARSET', 'utf8mb4');

// Telegram Bot配置
define('BOT_TOKEN', 'your_bot_token_here');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// 默认配置
define('DEFAULT_FEE_RATE', 70); // 默认费率 70%
define('DEFAULT_EXCHANGE_RATE', 7.2); // 默认汇率

// 权限配置
define('ADMIN_USER_IDS', [123456789, 987654321]); // 管理员用户ID列表

// Redis配置 (可选)
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', ''); // Redis密码，如果没有则留空
define('REDIS_DATABASE', 0);

// 系统配置
define('DEBUG_MODE', false); // 调试模式
define('LOG_LEVEL', 'INFO'); // 日志级别: DEBUG, INFO, WARNING, ERROR

// 安全配置
define('SESSION_TIMEOUT', 3600); // 会话超时时间(秒)
define('MAX_LOGIN_ATTEMPTS', 5); // 最大登录尝试次数

// 性能配置
define('CACHE_TTL', 300); // 缓存生存时间(秒)
define('LOCK_TIMEOUT', 10); // 锁超时时间(秒)

// 汇率API配置
define('HUOBI_API_URL', 'https://api.huobi.pro/market/ticker');
define('OKEX_API_URL', 'https://www.okx.com/api/v5/market/ticker');

// 文件上传配置
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// 邮件配置 (可选)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');
define('SMTP_FROM_EMAIL', 'your_email@gmail.com');
define('SMTP_FROM_NAME', 'Telegram Accounting Bot');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告设置
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 日志设置
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');

// 确保日志目录存在
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}
