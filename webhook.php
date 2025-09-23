<?php
/**
 * Telegram Webhook处理入口
 * 
 * @package TelegramAccountingBot
 * @author Your Name
 * @version 1.1.0
 * @since 2024-01-01
 */

// 引入配置和类文件
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/TelegramBot.php';
require_once 'classes/UserManager.php';
require_once 'classes/GroupManager.php';
require_once 'classes/AccountingManager.php';
require_once 'classes/BillFormatter.php';
require_once 'classes/MessageHandler.php';

// 设置错误处理
error_reporting(E_ALL);
ini_set('display_errors', 0); // 生产环境关闭错误显示
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');

// 确保日志目录存在
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}

try {
    // 获取Telegram发送的数据
    $input = file_get_contents('php://input');
    if (empty($input)) {
        http_response_code(400);
        exit('Bad Request');
    }
    
    // 记录原始输入（调试用）
    file_put_contents('logs/webhook.log', date('Y-m-d H:i:s') . " - " . $input . "\n", FILE_APPEND);
    
    // 解析JSON数据
    $update = json_decode($input, true);
    if (!$update) {
        http_response_code(400);
        exit('Invalid JSON');
    }
    
    // 创建消息处理器
    $messageHandler = new MessageHandler();
    
    // 处理更新
    $messageHandler->handleUpdate($update);
    
    // 返回成功状态
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    // 记录错误
    error_log('Webhook错误: ' . $e->getMessage());
    error_log('错误追踪: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo 'Internal Server Error';
}
