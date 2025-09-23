<?php
/**
 * 安装和配置脚本
 * 
 * @package TelegramAccountingBot
 * @author Your Name
 * @version 1.1.0
 * @since 2024-01-01
 */

require_once 'config/config.php';

echo "🤖 Telegram记账机器人安装程序\n";
echo "================================\n\n";

// 检查PHP版本
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    die("❌ 需要PHP 7.4或更高版本，当前版本：" . PHP_VERSION . "\n");
}

// 检查必需的扩展
$requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die("❌ 缺少必需的PHP扩展：" . implode(', ', $missingExtensions) . "\n");
}

echo "✅ PHP环境检查通过\n\n";

// 创建必要的目录
$directories = ['logs', 'uploads', 'temp'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        echo "📁 创建目录：{$dir}\n";
    }
}

// 数据库连接测试
echo "🔗 测试数据库连接...\n";
try {
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ 数据库连接成功\n";
    
    // 创建数据库（如果不存在）
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ 数据库创建成功\n";
    
} catch (PDOException $e) {
    die("❌ 数据库连接失败：" . $e->getMessage() . "\n");
}

// 导入数据库结构
echo "📊 导入数据库结构...\n";
try {
    $sql = file_get_contents('database/schema.sql');
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 分割SQL语句并执行
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^(--|CREATE DATABASE|USE)/i', $statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "✅ 数据库结构导入成功\n";
    
} catch (Exception $e) {
    die("❌ 数据库结构导入失败：" . $e->getMessage() . "\n");
}

// 检查Bot Token配置
if (BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
    echo "\n⚠️  警告：请在 config/config.php 中配置您的Bot Token\n";
    echo "1. 向 @BotFather 发送 /newbot 创建新机器人\n";
    echo "2. 获取Token并替换 config/config.php 中的 BOT_TOKEN\n";
    echo "3. 设置Webhook：访问 setup_webhook.php\n\n";
} else {
    echo "✅ Bot Token已配置\n\n";
}

// 创建示例配置文件
if (!file_exists('.htaccess')) {
    $htaccess = "RewriteEngine On\n";
    $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
    $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
    $htaccess .= "RewriteRule ^(.*)$ index.php [QSA,L]\n";
    
    file_put_contents('.htaccess', $htaccess);
    echo "📝 创建 .htaccess 文件\n";
}

// 设置文件权限
if (function_exists('chmod')) {
    chmod('logs', 0755);
    chmod('uploads', 0755);
    chmod('temp', 0755);
    echo "🔐 设置目录权限\n";
}

echo "\n🎉 安装完成！\n\n";
echo "📋 下一步操作：\n";
echo "1. 配置 Bot Token（如果还未配置）\n";
echo "2. 访问 setup_webhook.php 设置Webhook\n";
echo "3. 将机器人添加到群组并设为管理员\n";
echo "4. 发送 /start 测试机器人\n\n";
echo "📖 详细文档：README.md\n";
echo "🐛 问题反馈：请联系开发者\n\n";
