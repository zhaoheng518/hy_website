-- =====================================================
-- Stitch Tech 独立站 MySQL 数据库架构
-- 引擎: InnoDB | 字符集: utf8mb4_unicode_ci
-- 创建时间: 2026-05-09
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- 1. users - 后台管理员表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(64) NOT NULL COMMENT '登录用户名',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'bcrypt加密密码',
    `email` VARCHAR(128) DEFAULT NULL COMMENT '管理员邮箱',
    `role` ENUM('super_admin', 'admin', 'editor', 'viewer') NOT NULL DEFAULT 'super_admin' COMMENT '角色权限',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否激活',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_users_username` (`username`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台管理员表';

-- -----------------------------------------------------
-- 2. categories - 产品分类主表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(128) NOT NULL COMMENT 'URL别名，全局唯一',
    `parent_id` INT UNSIGNED DEFAULT NULL COMMENT '父分类ID，NULL为顶级',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序权重',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_categories_slug` (`slug`),
    KEY `idx_categories_parent` (`parent_id`),
    CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) 
        REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品分类主表';

-- -----------------------------------------------------
-- 3. category_translations - 分类翻译表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `category_translations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL COMMENT '关联分类主表ID',
    `lang` CHAR(2) NOT NULL COMMENT '语言代码: en/cn/es',
    `name` VARCHAR(256) NOT NULL COMMENT '分类名称',
    `description` TEXT DEFAULT NULL COMMENT '分类描述',
    `meta_title` VARCHAR(256) DEFAULT NULL COMMENT 'SEO标题',
    `meta_description` TEXT DEFAULT NULL COMMENT 'SEO描述',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_cat_trans_unique` (`category_id`, `lang`),
    KEY `idx_cat_trans_lang` (`lang`),
    CONSTRAINT `fk_cat_trans_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分类翻译表';

-- -----------------------------------------------------
-- 4. products - 产品主表（全局字段）
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(128) NOT NULL COMMENT 'URL别名，全局唯一',
    `category_id` INT UNSIGNED DEFAULT NULL COMMENT '所属分类ID',
    `product_model` VARCHAR(160) DEFAULT NULL COMMENT '型号（B2B）',
    `product_series` VARCHAR(160) DEFAULT NULL COMMENT '系列（B2B）',
    `moq` VARCHAR(128) DEFAULT NULL COMMENT '最小起订量',
    `lead_time` VARCHAR(160) DEFAULT NULL COMMENT '交货期',
    `datasheet_files` JSON DEFAULT NULL COMMENT '附加资料 [{label,url}]，与 JSON 产品数据对齐',
    `customizable_options` JSON DEFAULT NULL COMMENT '可定制项 [{name,value}]',
    `download_center` JSON DEFAULT NULL COMMENT '下载中心 [{title,url,label}]',
    `related_products` JSON DEFAULT NULL COMMENT '关联产品 slug 列表',
    `images` JSON DEFAULT NULL COMMENT '图片数组: [{url, alt_text, is_main}]',
    `specs` JSON DEFAULT NULL COMMENT '技术参数: [{label, value}]',
    `datasheet` VARCHAR(512) DEFAULT NULL COMMENT 'PDF datasheet路径',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序权重',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `view_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览次数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_products_slug` (`slug`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_active` (`is_active`),
    KEY `idx_products_sort` (`sort_order`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品主表';

-- 若线上旧库缺少 category_id，可执行（见 database_migrations/add_products_category_id_legacy.sql）:
-- ALTER TABLE `products` ADD COLUMN `category_id` INT UNSIGNED DEFAULT NULL AFTER `slug`, ADD KEY `idx_products_category` (`category_id`);

-- -----------------------------------------------------
-- 5. product_translations - 产品翻译表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_translations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL COMMENT '关联产品主表ID',
    `lang` CHAR(2) NOT NULL COMMENT '语言代码',
    `name` VARCHAR(256) NOT NULL COMMENT '产品名称',
    `desc` TEXT DEFAULT NULL COMMENT '产品描述（富文本HTML）',
    `short_desc` VARCHAR(512) DEFAULT NULL COMMENT '简短描述（兼容旧版）',
    `short_description` TEXT DEFAULT NULL COMMENT 'B2B 摘要（可与 short_desc 同步）',
    `content` TEXT DEFAULT NULL COMMENT '详细产品内容（富文本HTML）',
    `product_structure` MEDIUMTEXT DEFAULT NULL COMMENT '产品结构',
    `technical_specs` MEDIUMTEXT DEFAULT NULL COMMENT '技术规格（富文本）',
    `electrical_characteristics` MEDIUMTEXT DEFAULT NULL COMMENT '电气性能',
    `mechanical_characteristics` MEDIUMTEXT DEFAULT NULL COMMENT '机械性能',
    `environmental_characteristics` MEDIUMTEXT DEFAULT NULL COMMENT '环境性能',
    `applications` MEDIUMTEXT DEFAULT NULL COMMENT '应用领域',
    `compliance_standards` MEDIUMTEXT DEFAULT NULL COMMENT '符合标准',
    `tdk_tags` TEXT DEFAULT NULL COMMENT '附加 meta，JSON: [{name,content}] 或 {property,content}',
    `seo_title` VARCHAR(256) DEFAULT NULL COMMENT 'SEO标题',
    `seo_desc` TEXT DEFAULT NULL COMMENT 'SEO描述',
    `seo_keywords` VARCHAR(512) DEFAULT NULL COMMENT 'SEO 关键词',
    `canonical_url` VARCHAR(512) DEFAULT NULL COMMENT '规范链接（绝对或站内路径）',
    `faq_json` MEDIUMTEXT DEFAULT NULL COMMENT 'FAQ JSON 备份，与结构化 faqs 对齐',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_prod_trans_unique` (`product_id`, `lang`),
    KEY `idx_prod_trans_lang` (`lang`),
    CONSTRAINT `fk_prod_trans_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品翻译表';

-- -----------------------------------------------------
-- 6. inquiries - 询盘/留言表（前台 Contact + 后台 Admin）
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `inquiries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `external_id` VARCHAR(64) DEFAULT NULL COMMENT '对外/兼容 ID，如 inq_*',
    `name` VARCHAR(128) NOT NULL COMMENT '询盘人姓名',
    `company` VARCHAR(256) DEFAULT NULL COMMENT '公司名称',
    `email` VARCHAR(256) NOT NULL COMMENT '电子邮箱',
    `phone` VARCHAR(64) DEFAULT NULL COMMENT '联系电话',
    `country` VARCHAR(128) DEFAULT NULL COMMENT '国家/地区',
    `product_id` INT UNSIGNED DEFAULT NULL COMMENT '关联产品ID',
    `product_slug` VARCHAR(128) DEFAULT NULL COMMENT '询盘产品 slug',
    `product_source` VARCHAR(256) DEFAULT NULL COMMENT '询盘来源产品文案',
    `product_name` VARCHAR(256) DEFAULT NULL COMMENT '询盘时产品名称快照',
    `source_url` VARCHAR(512) DEFAULT NULL COMMENT '提交页 URL',
    `lang` VARCHAR(8) DEFAULT NULL COMMENT '语言代码',
    `message` TEXT NOT NULL COMMENT '询盘内容',
    `status` VARCHAR(32) NOT NULL DEFAULT 'new' COMMENT 'new|contacted|quoted|closed',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已读',
    `read_at` DATETIME DEFAULT NULL COMMENT '阅读时间',
    `read_by` INT UNSIGNED DEFAULT NULL COMMENT '阅读人ID',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT '来访IP',
    `user_agent` VARCHAR(512) DEFAULT NULL COMMENT '浏览器UserAgent',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_inquiries_external_id` (`external_id`),
    KEY `idx_inquiries_email` (`email`),
    KEY `idx_inquiries_read` (`is_read`),
    KEY `idx_inquiries_status` (`status`),
    KEY `idx_inquiries_created` (`created_at`),
    KEY `idx_inquiries_product` (`product_id`),
    CONSTRAINT `fk_inquiries_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='询盘留言表';

-- -----------------------------------------------------
-- 7. settings - 系统设置表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(128) NOT NULL COMMENT '设置键名',
    `setting_value` TEXT DEFAULT NULL COMMENT '设置值',
    `type` ENUM('string', 'int', 'float', 'json', 'bool') NOT NULL DEFAULT 'string' COMMENT '值类型',
    `group_name` VARCHAR(64) DEFAULT 'general' COMMENT '设置分组',
    `is_public` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否公开',
    `description` VARCHAR(256) DEFAULT NULL COMMENT '设置描述',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_settings_key` (`setting_key`),
    KEY `idx_settings_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表';

-- -----------------------------------------------------
-- 8. cases - 案例表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cases` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(128) NOT NULL,
    `image` VARCHAR(512) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_cases_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='案例表';

-- -----------------------------------------------------
-- 9. case_translations - 案例翻译表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `case_translations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_id` INT UNSIGNED NOT NULL,
    `lang` CHAR(2) NOT NULL,
    `title` VARCHAR(256) NOT NULL,
    `desc` TEXT DEFAULT NULL,
    `content` TEXT DEFAULT NULL,
    `seo_title` VARCHAR(256) DEFAULT NULL,
    `seo_desc` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_case_trans_lang` (`case_id`, `lang`),
    CONSTRAINT `fk_case_trans_case` FOREIGN KEY (`case_id`) 
        REFERENCES `cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='案例翻译表';

-- -----------------------------------------------------
-- 10. blog_posts - 博客文章表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(128) NOT NULL,
    `image` VARCHAR(512) DEFAULT NULL,
    `author` VARCHAR(128) DEFAULT NULL,
    `published_at` DATETIME DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_blog_slug` (`slug`),
    KEY `idx_blog_published` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='博客文章表';

-- -----------------------------------------------------
-- 11. blog_translations - 博客翻译表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_translations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `lang` CHAR(2) NOT NULL,
    `title` VARCHAR(256) NOT NULL,
    `excerpt` TEXT DEFAULT NULL,
    `content` TEXT DEFAULT NULL,
    `seo_title` VARCHAR(256) DEFAULT NULL,
    `seo_desc` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_blog_trans_lang` (`post_id`, `lang`),
    CONSTRAINT `fk_blog_trans_post` FOREIGN KEY (`post_id`) 
        REFERENCES `blog_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='博客翻译表';

-- -----------------------------------------------------
-- 12. pages - 页面表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(128) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面表';

-- -----------------------------------------------------
-- 13. page_translations - 页面翻译表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `page_translations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id` INT UNSIGNED NOT NULL,
    `lang` CHAR(2) NOT NULL,
    `title` VARCHAR(256) NOT NULL,
    `content` TEXT DEFAULT NULL,
    `seo_title` VARCHAR(256) DEFAULT NULL,
    `seo_desc` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_page_trans_lang` (`page_id`, `lang`),
    CONSTRAINT `fk_page_trans_page` FOREIGN KEY (`page_id`) 
        REFERENCES `pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面翻译表';

-- -----------------------------------------------------
-- 14. home_sections - 首页板块配置表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `home_sections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_key` VARCHAR(64) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `config` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_home_section_key` (`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='首页板块配置表';

-- -----------------------------------------------------
-- 15. media - 媒体文件表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `media` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename` VARCHAR(256) NOT NULL,
    `original_name` VARCHAR(256) NOT NULL,
    `file_path` VARCHAR(512) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(128) NOT NULL,
    `width` INT UNSIGNED DEFAULT NULL,
    `height` INT UNSIGNED DEFAULT NULL,
    `alt_text` VARCHAR(256) DEFAULT NULL,
    `uploaded_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_media_filename` (`filename`),
    CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploaded_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='媒体文件表';

-- -----------------------------------------------------
-- 16. activity_logs - 操作日志表
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(64) NOT NULL,
    `entity_type` VARCHAR(64) DEFAULT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `old_value` JSON DEFAULT NULL,
    `new_value` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(512) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_user` (`user_id`),
    KEY `idx_logs_entity` (`entity_type`, `entity_id`),
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- -----------------------------------------------------
-- 初始化默认管理员账号
-- 用户名: admin
-- 密码: admin123（bcrypt 与注释一致；旧版误用的 92IX… 哈希实际对应明文 password）
-- -----------------------------------------------------
INSERT INTO `users` (`username`, `password_hash`, `email`, `role`) VALUES
('admin', '$2y$10$Wi.04El7tO5tUbe.XypJ3O72USWqByXaVqoyCdkx9Fn9KDCw1WjQy', 'admin@example.com', 'super_admin');

-- -----------------------------------------------------
-- 初始化默认系统设置
-- -----------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`, `type`, `group_name`, `is_public`, `sort_order`, `description`) VALUES
('site_name', 'Stitch Tech', 'string', 'general', 1, 1, '网站名称'),
('site_url', 'http://localhost:8888/public_html', 'string', 'general', 1, 2, '网站URL'),
('logo', '', 'string', 'general', 1, 3, '网站Logo'),
('default_lang', 'cn', 'string', 'general', 1, 5, '默认语言'),
('supported_langs', '["en", "cn", "es"]', 'json', 'general', 1, 6, '支持的语言'),
('per_page', '12', 'int', 'general', 0, 7, '每页数量'),
('smtp_host', '', 'string', 'smtp', 0, 10, 'SMTP服务器'),
('smtp_port', '587', 'int', 'smtp', 0, 11, 'SMTP端口'),
('smtp_user', '', 'string', 'smtp', 0, 12, 'SMTP用户名'),
('smtp_pass', '', 'string', 'smtp', 0, 13, 'SMTP密码'),
('smtp_from', '', 'string', 'smtp', 0, 15, '发件人邮箱'),
('inquiry_email', '', 'string', 'notification', 0, 20, '询盘通知邮箱'),
('admin_email', '', 'string', 'notification', 0, 21, '管理员邮箱'),
('upload_max_size', '2097152', 'int', 'advanced', 0, 40, '上传大小限制'),
('upload_allowed_types', '["jpg", "jpeg", "png", "webp", "pdf"]', 'json', 'advanced', 0, 41, '允许的上传类型');

-- -----------------------------------------------------
-- 初始化首页板块
-- -----------------------------------------------------
INSERT INTO `home_sections` (`section_key`, `is_enabled`, `sort_order`, `config`) VALUES
('banner', 1, 1, '{"title": "", "subtitle": "", "cta_text": "", "cta_link": "", "background_image": ""}'),
('products', 1, 2, '{"title": "", "subtitle": "", "items": []}'),
('factory', 1, 3, '{"title": "", "subtitle": "", "content": "", "images": []}'),
('cases', 1, 4, '{"title": "", "subtitle": "", "items": []}'),
('about', 1, 5, '{"title": "", "content": "", "image": "", "certifications": []}'),
('blog', 1, 6, '{"title": "", "subtitle": ""}'),
('contact', 1, 7, '{"title": "", "subtitle": "", "email": "", "phone": "", "whatsapp": "", "address": ""}');

-- -----------------------------------------------------
-- newsletter_subscribers - 邮件订阅（询盘自动订阅 / 手动订阅 / 更新通知）
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL COMMENT '订阅邮箱',
    `lang` VARCHAR(8) NOT NULL DEFAULT 'en' COMMENT '偏好语言',
    `notify_product` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '接收产品更新',
    `notify_blog` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '接收博客更新',
    `notify_general` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '接收通用/活动',
    `source` VARCHAR(32) NOT NULL DEFAULT 'manual' COMMENT '来源 manual|inquiry',
    `unsubscribe_token` CHAR(64) NOT NULL COMMENT '退订令牌',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否有效订阅',
    `tags` JSON NULL COMMENT 'JSON 字符串数组，询盘自动合并 product:slug 等标签',
    `interested_products` JSON NULL COMMENT 'JSON 字符串数组，询盘累计的产品 slug',
    `last_inquiry_product` VARCHAR(255) DEFAULT NULL COMMENT '最近一次询盘关联的产品 slug',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_newsletter_email` (`email`),
    KEY `idx_newsletter_active_product` (`is_active`, `notify_product`),
    KEY `idx_newsletter_active_blog` (`is_active`, `notify_blog`),
    KEY `idx_newsletter_token` (`unsubscribe_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮件订阅';

-- 已有 newsletter_subscribers 表时手工执行（列已存在则报错可忽略）:
-- ALTER TABLE `newsletter_subscribers` ADD COLUMN `tags` JSON NULL COMMENT 'JSON 字符串数组' AFTER `is_active`;
-- ALTER TABLE `newsletter_subscribers` ADD COLUMN `interested_products` JSON NULL COMMENT 'JSON slug 列表' AFTER `tags`;
-- ALTER TABLE `newsletter_subscribers` ADD COLUMN `last_inquiry_product` VARCHAR(255) DEFAULT NULL COMMENT '最近询盘产品 slug' AFTER `interested_products`;

-- -----------------------------------------------------
-- newsletter_jobs - 邮件发送队列（订阅通知等）
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `newsletter_jobs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subscriber_id` INT UNSIGNED NOT NULL COMMENT 'newsletter_subscribers.id',
    `subject` VARCHAR(512) NOT NULL COMMENT '邮件主题',
    `content_html` MEDIUMTEXT NOT NULL COMMENT 'HTML 正文',
    `content_text` TEXT NOT NULL COMMENT '纯文本摘要/正文',
    `type` VARCHAR(32) NOT NULL DEFAULT 'general' COMMENT '任务类型 product_update|blog_post|general',
    `status` ENUM('pending', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'pending' COMMENT 'pending|sending|sent|failed',
    `send_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '计划发送时间',
    `sent_at` DATETIME DEFAULT NULL COMMENT '实际发送完成时间',
    `error_message` TEXT DEFAULT NULL COMMENT '失败原因',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已失败次数（含将重试的本次）',
    `max_retry` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '最多允许失败重试次数（超过则永久 failed）',
    `next_retry_at` DATETIME DEFAULT NULL COMMENT '下次重试计划时间（与 send_at 一致便于抓取）',
    `last_retry_at` DATETIME DEFAULT NULL COMMENT '最近一次进入重试/失败处理的时间',
    `provider_message_id` VARCHAR(128) DEFAULT NULL COMMENT 'Brevo 返回的 messageId',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_newsletter_job_status_send` (`status`, `send_at`),
    KEY `idx_newsletter_job_subscriber` (`subscriber_id`),
    KEY `idx_newsletter_job_type` (`type`),
    CONSTRAINT `fk_newsletter_job_subscriber` FOREIGN KEY (`subscriber_id`) REFERENCES `newsletter_subscribers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮件发送队列';

-- 从旧版 newsletter_jobs（content 列、updated_at）迁移：建议备份后建新表或逐列 ALTER，与 app/core/NewsletterJobRepository.php 字段对齐。
-- 已有表增加重试字段（执行一次即可）：
-- ALTER TABLE `newsletter_jobs` ADD COLUMN `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已失败次数' AFTER `error_message`;
-- ALTER TABLE `newsletter_jobs` ADD COLUMN `max_retry` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '最多失败重试次数' AFTER `retry_count`;
-- ALTER TABLE `newsletter_jobs` ADD COLUMN `next_retry_at` DATETIME DEFAULT NULL COMMENT '下次重试时间' AFTER `max_retry`;
-- ALTER TABLE `newsletter_jobs` ADD COLUMN `last_retry_at` DATETIME DEFAULT NULL COMMENT '最近一次重试/失败处理时间' AFTER `next_retry_at`;
-- ALTER TABLE `newsletter_jobs` ADD COLUMN `provider_message_id` VARCHAR(128) DEFAULT NULL COMMENT 'Brevo messageId' AFTER `last_retry_at`;
-- 详见 database_migrations/20260209_inquiry_newsletter_jobs.sql

-- -----------------------------------------------------
-- newsletter_events - Webhook 事件（Brevo 等）
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `newsletter_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'newsletter_jobs.id',
    `provider_message_id` VARCHAR(255) DEFAULT NULL COMMENT 'ESP message-id',
    `subscriber_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'newsletter_subscribers.id',
    `event_type` VARCHAR(50) NOT NULL COMMENT '规范化事件类型',
    `payload` LONGTEXT NOT NULL COMMENT 'JSON',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_provider_message_id` (`provider_message_id`),
    KEY `idx_job_id` (`job_id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Newsletter Webhook 事件';

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 执行完成！
-- 验证命令：SHOW TABLES;
-- =====================================================
