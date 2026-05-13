-- Brevo Webhook 等事件落库（执行前请备份）
CREATE TABLE IF NOT EXISTS `newsletter_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'newsletter_jobs.id（可空）',
    `provider_message_id` VARCHAR(255) DEFAULT NULL COMMENT 'ESP message-id',
    `subscriber_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'newsletter_subscribers.id（可空）',
    `event_type` VARCHAR(50) NOT NULL COMMENT '规范化事件类型',
    `payload` LONGTEXT NOT NULL COMMENT '原始/补充 JSON',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_provider_message_id` (`provider_message_id`),
    KEY `idx_job_id` (`job_id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Newsletter Webhook 事件';
