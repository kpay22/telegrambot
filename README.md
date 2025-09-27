# Telegram 记账机器人

一个功能完整的Telegram记账机器人，支持群组记账、费率汇率管理、操作员权限控制等功能。

如需高级功能定制，请联系Telegram：@laqie

# 你也可以直接使用我们的现有纯净无广告机器人：vsunorgBot

开源版本仍在开发中，未来陆续新增更多功能！

<img width="473" height="508" alt="image" src="https://github.com/user-attachments/assets/2a813d3d-b1c9-4e4e-8a4c-e0857b8b9262" />
<img width="466" height="708" alt="image" src="https://github.com/user-attachments/assets/8a68bb3c-5bc7-4651-9e2b-443c8fb3e301" />

## ✨ 功能特点

### 📊 基础记账功能
- ✅ 记账入账：`+10000`
- ✅ 代付减账：`-10000`
- ✅ 汇率记账：`+10000/7.8`
- ✅ USDT记账：`+7777u`（自动计算费汇率）
- ✅ 下发回分：`下发5000`
- ✅ 错误修正：`撤销`

### ⚙️ 配置管理
- ✅ 费率配置：`设置费率10`
- ✅ 汇率配置：`设置汇率8`
- ✅ 实时汇率：`设置火币汇率`、`设置欧易汇率`
- ✅ 自定义配置：支持欧元、港币等多币种
- ✅ 代付配置：独立的代付费率和手续费

### 👥 权限管理
- ✅ 操作员管理：`@username 添加操作员`
- ✅ 权限等级：超级管理员、管理员、操作员、普通用户
- ✅ 群组权限：独立的群组操作员系统

### 📋 账单管理
- ✅ 实时账单：`账单`
- ✅ 月度统计：`总账单`
- ✅ 分组统计：支持按用户/类别分组
- ✅ 自定义账单格式：支持自定义显示格式
- ✅ 账单详情页面：Web界面查看详细数据

### 🔧 高级功能
- ✅ 多群组支持：一个机器人管理多个群组
- ✅ 实时汇率：自动获取火币、欧易等交易所汇率
- ✅ 数据导出：支持Excel格式导出
- ✅ 操作日志：完整的操作记录和审计
- ✅ 并发优化：Redis分布式锁，支持高并发

## 🚀 快速部署

### 1. 环境准备

#### 服务器要求
- **操作系统**: Linux (Ubuntu 20.04+ 推荐)
- **PHP**: 7.4 或更高版本
- **MySQL**: 5.7 或更高版本
- **Web服务器**: Apache 或 Nginx
- **SSL证书**: 必须 (Telegram Webhook 要求 HTTPS)
- **Redis**: 可选，用于缓存和分布式锁

#### 必需的PHP扩展
```bash
sudo apt update
sudo apt install php php-mysql php-curl php-json php-mbstring php-xml php-redis
```

### 2. 创建Telegram机器人

1. 向 [@BotFather](https://t.me/BotFather) 发送 `/newbot`
2. 按提示设置机器人名称和用户名
3. 获取Bot Token (格式: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
4. 保存Token，稍后配置时需要

### 3. 下载和配置

#### 下载代码
```bash
git clone https://github.com/your-repo/telegram-accounting-bot.git
cd telegram-accounting-bot
```

#### 配置数据库
1. 创建MySQL数据库：
```sql
CREATE DATABASE telegram_accounting_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. 创建数据库用户：
```sql
CREATE USER 'bot_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON telegram_accounting_bot.* TO 'bot_user'@'localhost';
FLUSH PRIVILEGES;
```

#### 配置文件
1. 复制配置文件：
```bash
cp config/config.example.php config/config.php
```

2. 编辑 `config/config.php`：
```php
<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'telegram_accounting_bot');
define('DB_USER', 'bot_user');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');

// Telegram Bot配置
define('BOT_TOKEN', 'your_bot_token_here');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// 默认配置
define('DEFAULT_FEE_RATE', 70); // 默认费率 70%
define('DEFAULT_EXCHANGE_RATE', 7.2); // 默认汇率

// 权限配置
define('ADMIN_USER_IDS', [123456789, 987654321]); // 管理员用户ID列表
?>
```

### 4. 数据库初始化

运行数据库初始化脚本：
```bash
php setup.php
```

这将创建所有必要的数据库表和初始数据。

### 5. 设置Webhook

运行Webhook设置脚本：
```bash
php setup_webhook.php
```

确保您的服务器有SSL证书，并且可以通过HTTPS访问。

### 6. 配置Web服务器

#### Apache配置
创建 `.htaccess` 文件：
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# 安全设置
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>

<Files "*.log">
    Order Allow,Deny
    Deny from all
</Files>
```

#### Nginx配置
```nginx
server {
    listen 443 ssl;
    server_name your-domain.com;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    root /path/to/telegram-accounting-bot;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # 安全设置
    location ~ /(config|logs)/ {
        deny all;
    }
}
```

### 7. 启动服务

#### 启动Redis (可选但推荐)
```bash
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

#### 设置文件权限
```bash
chmod 755 /path/to/telegram-accounting-bot
chmod 644 /path/to/telegram-accounting-bot/config/config.php
chmod 755 /path/to/telegram-accounting-bot/logs
```

## 📖 使用指南

### 机器人激活流程

1. **邀请机器人进群**
2. **机器人自动发送**: "如需记账请回复/start"
3. **用户回复**: `/start`
4. **设置费率和汇率**:
   - `设置费率70` (设置70%费率)
   - `设置汇率7.2` (设置7.2汇率)
5. **开始记账**: 机器人确认"记账开始"

### 基本命令

#### 记账命令
- `+1000` - 入账1000元
- `-500` - 出账500元
- `+1000/7.8` - 按汇率7.8入账1000元
- `+7777u` - 入账7777 USDT（自动计算汇率）
- `下发5000` - 下发5000元

#### 配置命令
- `设置费率70` - 设置70%费率
- `设置汇率7.2` - 设置7.2汇率
- `设置火币汇率` - 使用火币实时汇率
- `设置欧易汇率` - 使用欧易实时汇率

#### 查询命令
- `账单` - 查看今日账单
- `总账单` - 查看本月总账单
- `我的账单` - 查看个人账单

#### 管理命令
- `@username 添加操作员` - 添加操作员
- `@username 删除操作员` - 删除操作员
- `显示操作员` - 显示所有操作员
- `开始记账` - 开启记账功能
- `关闭记账` - 关闭记账功能

### 后台管理

访问 `https://your-domain.com/admin_dashboard.php` 进行后台管理：

- **群组管理**: 查看所有群组状态
- **按钮配置**: 配置账单详情按钮
- **数据统计**: 查看交易统计
- **系统设置**: 管理全局配置

## 🔧 高级配置

### Redis配置 (推荐)

安装Redis以提升性能：
```bash
sudo apt install redis-server
```

Redis用于：
- 分布式锁（防止重复发送账单）
- 群组设置缓存
- 会话管理

### 性能优化

1. **启用OPcache**:
```bash
sudo apt install php-opcache
```

2. **配置MySQL**:
```ini
[mysqld]
innodb_buffer_pool_size = 256M
query_cache_size = 64M
```

3. **配置PHP**:
```ini
memory_limit = 256M
max_execution_time = 30
```

### 监控和日志

- **错误日志**: `logs/error.log`
- **Webhook日志**: `logs/webhook.log`
- **访问日志**: Web服务器日志

## 🛠️ 故障排除

### 常见问题

1. **机器人无响应**
   - 检查Webhook设置
   - 查看错误日志
   - 确认SSL证书有效

2. **数据库连接失败**
   - 检查数据库配置
   - 确认数据库服务运行
   - 验证用户权限

3. **账单重复发送**
   - 检查Redis连接
   - 查看分布式锁日志

### 调试模式

启用调试模式：
```php
// config/config.php
define('DEBUG_MODE', true);
```

## 📊 系统架构

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Telegram      │    │   Web Server    │    │   Database      │
│   Bot API       │◄──►│   (Apache/Nginx)│◄──►│   (MySQL)       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                              │
                              ▼
                       ┌─────────────────┐
                       │   Redis Cache   │
                       │   (Optional)    │
                       └─────────────────┘
```

## 🔒 安全建议

1. **定期更新**: 保持系统和依赖项更新
2. **访问控制**: 限制管理后台访问
3. **数据备份**: 定期备份数据库
4. **日志监控**: 监控异常访问和错误
5. **SSL证书**: 确保证书有效且及时更新

## 📝 更新日志

### v1.0.0
- 基础记账功能
- 群组管理
- 操作员权限
- 实时账单

### v1.1.0
- 添加Redis缓存
- 分布式锁优化
- 性能提升
- 并发处理改进

## 🤝 贡献

欢迎提交Issue和Pull Request！

## 📄 许可证

MIT License

## 📞 支持


如有问题，请提交Issue或联系开发者。





