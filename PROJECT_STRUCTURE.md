# 项目结构说明

## 📁 目录结构

```
telegram-accounting-bot/
├── 📁 api/                    # API接口目录
│   ├── group_settings.php     # 群组设置API
│   └── group_stats.php        # 群组统计API
├── 📁 classes/                # 核心类文件
│   ├── AccountingManager.php  # 记账管理类
│   ├── BillFormatter.php      # 账单格式化类
│   ├── ButtonManager.php      # 按钮管理类
│   ├── Database.php           # 数据库连接类
│   ├── GroupManager.php       # 群组管理类
│   ├── MessageHandler.php     # 消息处理类
│   ├── RedisManager.php       # Redis管理类
│   ├── TelegramBot.php        # Telegram Bot类
│   └── UserManager.php        # 用户管理类
├── 📁 config/                 # 配置文件目录
│   └── config.php             # 主配置文件
├── 📁 database/               # 数据库相关文件
│   └── schema.sql             # 数据库结构文件
├── 📁 logs/                   # 日志文件目录
│   ├── error.log              # 错误日志
│   └── webhook.log            # Webhook日志
├── 📁 web/                    # Web界面文件
│   ├── admin_dashboard.php    # 管理后台
│   ├── bill_detail.php        # 账单详情页
│   └── multi_button_config.php # 按钮配置页
├── 📄 webhook.php             # Webhook入口文件
├── 📄 setup.php               # 安装脚本
├── 📄 setup_webhook.php       # Webhook设置脚本
├── 📄 index.php               # 首页
├── 📄 README.md               # 项目说明文档
└── 📄 PROJECT_STRUCTURE.md    # 项目结构说明
```

## 🔧 核心文件说明

### 入口文件
- **`webhook.php`** - Telegram Webhook处理入口，接收所有Telegram消息
- **`index.php`** - 项目首页，提供基本信息和状态检查

### 安装配置
- **`setup.php`** - 数据库初始化脚本，创建所有必要的表和初始数据
- **`setup_webhook.php`** - 设置或删除Telegram Webhook

### 核心类文件
- **`Database.php`** - 数据库连接管理，支持连接重试和自动重连
- **`TelegramBot.php`** - Telegram Bot API封装，处理消息发送和接收
- **`MessageHandler.php`** - 消息处理器，解析命令并调用相应功能
- **`AccountingManager.php`** - 记账核心逻辑，处理所有记账相关操作
- **`BillFormatter.php`** - 账单格式化，生成自定义格式的账单
- **`GroupManager.php`** - 群组管理，处理群组设置和权限
- **`UserManager.php`** - 用户管理，处理用户信息和权限
- **`ButtonManager.php`** - 按钮管理，处理内联键盘按钮
- **`RedisManager.php`** - Redis管理，提供缓存和分布式锁功能

### Web界面
- **`admin_dashboard.php`** - 全局管理后台，管理所有群组
- **`bill_detail.php`** - 账单详情页面，显示详细交易记录
- **`multi_button_config.php`** - 多按钮配置页面，管理群组按钮

### API接口
- **`api/group_settings.php`** - 群组设置API，供管理后台调用
- **`api/group_stats.php`** - 群组统计API，获取统计数据

## 🗄️ 数据库结构

### 主要数据表
- **`users`** - 用户信息表
- **`groups`** - 群组信息表
- **`group_settings`** - 群组配置表
- **`group_operators`** - 群组操作员表
- **`transactions`** - 交易记录表
- **`custom_rates`** - 自定义汇率表
- **`group_buttons`** - 群组按钮表

## 🔄 数据流程

### 消息处理流程
```
Telegram → webhook.php → MessageHandler → AccountingManager → Database
                                 ↓
                            BillFormatter → Telegram (发送账单)
```

### 缓存流程
```
请求 → RedisManager → Redis缓存 → 数据库 (缓存未命中)
```

### 权限验证流程
```
用户操作 → UserManager → 权限检查 → 允许/拒绝
```

## 🚀 性能优化

### 缓存策略
- **群组设置缓存**: 5分钟TTL，减少数据库查询
- **Redis分布式锁**: 防止重复操作
- **连接池管理**: 数据库连接重试和自动重连

### 并发处理
- **分布式锁**: 防止账单重复发送
- **事务管理**: 确保数据一致性
- **错误处理**: 完善的异常处理和日志记录

## 📝 开发规范

### 文件命名
- 类文件使用PascalCase: `ClassName.php`
- 配置文件使用snake_case: `config_file.php`
- 目录名使用小写: `api/`, `classes/`

### 代码规范
- 使用PSR-4自动加载规范
- 类名和文件名保持一致
- 方法名使用camelCase
- 常量使用UPPER_CASE

### 注释规范
- 类和方法使用PHPDoc注释
- 复杂逻辑添加行内注释
- 配置文件添加说明注释

## 🔒 安全考虑

### 文件权限
- 配置文件: 644 (仅所有者可写)
- 日志目录: 755 (可执行)
- 类文件: 644 (只读)

### 访问控制
- 管理后台需要登录验证
- API接口需要权限检查
- 敏感文件禁止直接访问

### 数据安全
- 使用预处理语句防止SQL注入
- 敏感信息加密存储
- 定期备份数据库
