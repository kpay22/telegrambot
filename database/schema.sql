-- Telegram记账机器人数据库结构

-- 创建数据库
CREATE DATABASE IF NOT EXISTS `telegram_accounting_bot` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `telegram_accounting_bot`;

-- 用户表
CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `telegram_id` bigint(20) NOT NULL UNIQUE,
    `username` varchar(100) DEFAULT NULL,
    `first_name` varchar(100) DEFAULT NULL,
    `last_name` varchar(100) DEFAULT NULL,
    `permission_level` tinyint(1) DEFAULT 3 COMMENT '权限等级: 0=超级管理员, 1=管理员, 2=操作员, 3=普通用户',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_telegram_id` (`telegram_id`),
    KEY `idx_permission` (`permission_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 群组表
CREATE TABLE `groups` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `telegram_group_id` bigint(20) NOT NULL UNIQUE,
    `group_name` varchar(200) DEFAULT NULL,
    `group_type` varchar(20) DEFAULT 'group',
    `is_active` tinyint(1) DEFAULT 1,
    `accounting_status` tinyint(1) DEFAULT 1 COMMENT '记账状态: 0=关闭, 1=开启',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_group_id` (`telegram_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 群组操作员表
CREATE TABLE `group_operators` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `added_by` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_group_user` (`group_id`, `user_id`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 账单记录表
CREATE TABLE `transactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `operator_id` int(11) NOT NULL COMMENT '操作员ID',
    `message_id` int(11) DEFAULT NULL COMMENT 'Telegram消息ID',
    `transaction_type` enum('income','expense','distribution','correction') NOT NULL COMMENT '交易类型',
    `amount` decimal(15,2) NOT NULL COMMENT '金额',
    `original_amount` decimal(15,2) DEFAULT NULL COMMENT '原始金额（修正前）',
    `fee_rate` decimal(5,2) DEFAULT NULL COMMENT '费率',
    `exchange_rate` decimal(10,4) DEFAULT NULL COMMENT '汇率',
    `currency` varchar(10) DEFAULT 'CNY' COMMENT '币种',
    `category` varchar(50) DEFAULT NULL COMMENT '分组/分类',
    `note` text COMMENT '备注',
    `is_pending` tinyint(1) DEFAULT 0 COMMENT '是否寄存',
    `is_deleted` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_group_date` (`group_id`, `created_at`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_operator_id` (`operator_id`),
    KEY `idx_transaction_type` (`transaction_type`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`operator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 群组配置表
CREATE TABLE `group_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `setting_key` varchar(50) NOT NULL,
    `setting_value` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_group_setting` (`group_id`, `setting_key`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 自定义汇率配置表
CREATE TABLE `custom_rates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `rate_name` varchar(20) NOT NULL COMMENT '自定义名称（如：欧元、港币、张三）',
    `fee_rate` decimal(5,2) DEFAULT NULL COMMENT '费率',
    `exchange_rate` decimal(10,4) DEFAULT NULL COMMENT '汇率',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_group_rate` (`group_id`, `rate_name`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 代付配置表
CREATE TABLE `payment_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `single_fee` decimal(10,2) DEFAULT NULL COMMENT '单笔手续费',
    `payment_fee_rate` decimal(5,2) DEFAULT NULL COMMENT '代付费率（负数）',
    `payment_exchange_rate` decimal(10,4) DEFAULT NULL COMMENT '代付汇率',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_group_payment` (`group_id`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 自定义按钮表
CREATE TABLE `custom_buttons` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `created_by` int(11) NOT NULL,
    `button_text` varchar(50) NOT NULL,
    `button_url` varchar(500) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 关键词回复表
CREATE TABLE `keyword_replies` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) DEFAULT NULL COMMENT 'NULL表示全局',
    `keyword` varchar(100) NOT NULL,
    `reply_text` text NOT NULL,
    `created_by` int(11) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_keyword` (`keyword`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 计时记录表
CREATE TABLE `work_time_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `group_id` int(11) NOT NULL,
    `start_time` timestamp DEFAULT CURRENT_TIMESTAMP,
    `end_time` timestamp NULL DEFAULT NULL,
    `duration` int(11) DEFAULT NULL COMMENT '工作时长（秒）',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_group` (`user_id`, `group_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 下发地址表
CREATE TABLE `distribution_addresses` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `address` varchar(100) NOT NULL,
    `created_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_group_address` (`group_id`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
