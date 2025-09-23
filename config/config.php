<?php
/**
 * Telegram记账机器人配置文件
 */

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'abc8888');
define('DB_USER', 'abc8888');
define('DB_PASS', 'abc8888');
define('DB_CHARSET', 'utf8mb4');

// Telegram Bot配置
define('BOT_TOKEN', 'token'); // 请替换为您的Bot Token
define('BOT_USERNAME', '@laqie开发者'); // 请替换为您的Bot用户名

// API配置
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// 默认配置
define('DEFAULT_FEE_RATE', 10); // 默认费率 10%
define('DEFAULT_EXCHANGE_RATE', 7.2); // 默认汇率
define('DEFAULT_TIMEZONE', 'Asia/Shanghai');

// 权限等级
define('PERMISSION_SUPER_ADMIN', 0); // 超级管理员
define('PERMISSION_ADMIN', 1); // 管理员
define('PERMISSION_OPERATOR', 2); // 操作员
define('PERMISSION_USER', 3); // 普通用户

// 实时汇率API配置
define('HUOBI_API_URL', 'https://api.huobi.pro/market/detail/merged?symbol=usdtcny');
define('OKX_API_URL', 'https://www.okx.com/api/v5/market/ticker?instId=USDT-CNY');

// 时区设置
date_default_timezone_set(DEFAULT_TIMEZONE);

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

