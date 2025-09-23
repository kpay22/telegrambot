-- 添加多按钮支持
-- 创建按钮配置表

CREATE TABLE IF NOT EXISTS `group_buttons` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `button_text` varchar(100) NOT NULL COMMENT '按钮文本',
    `button_url` varchar(500) NOT NULL COMMENT '按钮链接',
    `button_type` enum('url','callback') DEFAULT 'url' COMMENT '按钮类型',
    `sort_order` int(11) DEFAULT 0 COMMENT '排序',
    `is_active` tinyint(1) DEFAULT 1 COMMENT '是否启用',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_group_id` (`group_id`),
    KEY `idx_sort_order` (`sort_order`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 迁移现有按钮配置到新表
INSERT INTO `group_buttons` (`group_id`, `button_text`, `button_url`, `button_type`, `sort_order`, `is_active`)
SELECT 
    g.id as group_id,
    COALESCE(gs_text.setting_value, '📊 查看详情') as button_text,
    COALESCE(gs_url.setting_value, CONCAT('https://', 'your-domain.com', '/bill_detail.php?token=', MD5(CONCAT(g.id, 'telegram_bot_token_2024')), '&group_id=', g.id)) as button_url,
    'url' as button_type,
    1 as sort_order,
    1 as is_active
FROM `groups` g
LEFT JOIN `group_settings` gs_text ON g.id = gs_text.group_id AND gs_text.setting_key = 'button_text'
LEFT JOIN `group_settings` gs_url ON g.id = gs_url.group_id AND gs_url.setting_key = 'button_url'
WHERE g.is_active = 1;
