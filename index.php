<?php
/**
 * Telegram记账机器人 - 项目入口页面
 */

// 防止直接访问敏感文件
if (basename($_SERVER['PHP_SELF']) === 'webhook.php') {
    http_response_code(403);
    exit('Access Denied');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram 记账机器人</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        .header p {
            color: #7f8c8d;
            font-size: 1.2em;
        }
        .status {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        .status-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 10px 0;
            flex: 1;
            min-width: 200px;
            margin-right: 20px;
        }
        .status-item:last-child {
            margin-right: 0;
        }
        .status-ok {
            border-left: 4px solid #27ae60;
        }
        .status-error {
            border-left: 4px solid #e74c3c;
        }
        .status-warning {
            border-left: 4px solid #f39c12;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .feature {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        .feature h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .actions {
            text-align: center;
            margin-top: 40px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-success:hover {
            background: #229954;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Telegram 记账机器人</h1>
            <p>专业的群组记账解决方案</p>
        </div>

        <div class="status">
            <?php
            // 检查系统状态
            $phpVersion = PHP_VERSION;
            $phpOk = version_compare($phpVersion, '7.4.0', '>=');
            
            $configExists = file_exists('config/config.php');
            $dbConfigured = false;
            $botConfigured = false;
            
            if ($configExists) {
                require_once 'config/config.php';
                $dbConfigured = (DB_HOST !== 'localhost' || DB_USER !== 'root' || DB_PASS !== '');
                $botConfigured = (BOT_TOKEN !== 'YOUR_BOT_TOKEN_HERE');
            }
            
            $logsDirWritable = is_writable('logs');
            ?>
            
            <div class="status-item <?php echo $phpOk ? 'status-ok' : 'status-error'; ?>">
                <h4>PHP 环境</h4>
                <p><?php echo $phpOk ? '✅' : '❌'; ?> PHP <?php echo $phpVersion; ?></p>
                <?php if (!$phpOk): ?>
                <small>需要 PHP 7.4 或更高版本</small>
                <?php endif; ?>
            </div>
            
            <div class="status-item <?php echo $configExists ? 'status-ok' : 'status-error'; ?>">
                <h4>配置文件</h4>
                <p><?php echo $configExists ? '✅ 已存在' : '❌ 未找到'; ?></p>
                <?php if (!$configExists): ?>
                <small>请先运行安装程序</small>
                <?php endif; ?>
            </div>
            
            <div class="status-item <?php echo $botConfigured ? 'status-ok' : 'status-warning'; ?>">
                <h4>Bot 配置</h4>
                <p><?php echo $botConfigured ? '✅ 已配置' : '⚠️ 待配置'; ?></p>
                <?php if (!$botConfigured): ?>
                <small>需要设置 Bot Token</small>
                <?php endif; ?>
            </div>
            
            <div class="status-item <?php echo $logsDirWritable ? 'status-ok' : 'status-error'; ?>">
                <h4>日志目录</h4>
                <p><?php echo $logsDirWritable ? '✅ 可写' : '❌ 不可写'; ?></p>
                <?php if (!$logsDirWritable): ?>
                <small>请设置 logs 目录权限为 777</small>
                <?php endif; ?>
            </div>
        </div>

        <div class="features">
            <div class="feature">
                <h3>💰 智能记账</h3>
                <p>支持多种记账格式：基础记账、汇率记账、USDT记账、分组记账等</p>
            </div>
            
            <div class="feature">
                <h3>⚙️ 灵活配置</h3>
                <p>费率汇率管理、实时汇率、自定义币种配置、代付独立配置</p>
            </div>
            
            <div class="feature">
                <h3>👥 权限管理</h3>
                <p>多级权限控制、操作员管理、群组独立配置</p>
            </div>
            
            <div class="feature">
                <h3>📊 数据统计</h3>
                <p>实时账单、月度统计、分组统计、操作记录追踪</p>
            </div>
            
            <div class="feature">
                <h3>🔧 高级功能</h3>
                <p>关键词回复、自定义按钮、定时日切、工作计时</p>
            </div>
            
            <div class="feature">
                <h3>🛡️ 安全可靠</h3>
                <p>数据加密存储、操作日志记录、权限验证、备份恢复</p>
            </div>
        </div>

        <div class="actions">
            <?php if (!$configExists): ?>
            <a href="setup.php" class="btn btn-success">🚀 开始安装</a>
            <?php else: ?>
            <a href="setup_webhook.php" class="btn">⚙️ 设置 Webhook</a>
            <?php endif; ?>
            
            <?php if ($configExists): ?>
            <a href="test_basic.php" class="btn">🧪 测试功能</a>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>📚 查看 <a href="README.md" style="color: #3498db;">README.md</a> 了解详细使用说明</p>
            <p>🚀 查看 <a href="DEPLOYMENT.md" style="color: #3498db;">DEPLOYMENT.md</a> 了解部署指南</p>
            <p>版本 1.0.0 | 基于 PHP + MySQL 构建</p>
        </div>
    </div>
</body>
</html>
