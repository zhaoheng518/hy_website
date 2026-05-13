-- 按需执行；若列/索引已存在请跳过对应语句。
-- newsletter_jobs：Brevo messageId、重试时间戳（与 NewsletterJobRepository / SendNewsletterJobs 对齐）
ALTER TABLE `newsletter_jobs`
    ADD COLUMN `last_retry_at` DATETIME DEFAULT NULL COMMENT '最近一次重试/失败处理时间' AFTER `next_retry_at`;

ALTER TABLE `newsletter_jobs`
    ADD COLUMN `provider_message_id` VARCHAR(128) DEFAULT NULL COMMENT 'Brevo messageId' AFTER `last_retry_at`;

-- inquiries：与 InquiryRepository / Contact 表单对齐（旧表无下列字段时执行）
ALTER TABLE `inquiries` ADD COLUMN `external_id` VARCHAR(64) DEFAULT NULL COMMENT '对外/兼容 ID' AFTER `id`;

ALTER TABLE `inquiries` ADD COLUMN `product_slug` VARCHAR(128) DEFAULT NULL COMMENT '询盘产品 slug' AFTER `product_id`;

ALTER TABLE `inquiries` ADD COLUMN `product_source` VARCHAR(256) DEFAULT NULL COMMENT '询盘来源产品文案' AFTER `product_slug`;

ALTER TABLE `inquiries` ADD COLUMN `source_url` VARCHAR(512) DEFAULT NULL COMMENT '提交页 URL' AFTER `product_name`;

ALTER TABLE `inquiries` ADD COLUMN `lang` VARCHAR(8) DEFAULT NULL COMMENT '语言代码' AFTER `source_url`;

ALTER TABLE `inquiries` ADD COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'new' COMMENT 'new|contacted|quoted|closed' AFTER `message`;

ALTER TABLE `inquiries` ADD UNIQUE KEY `idx_inquiries_external_id` (`external_id`);

ALTER TABLE `inquiries` ADD KEY `idx_inquiries_status` (`status`);
