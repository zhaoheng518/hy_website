-- Module 10: Datasheet Download Tracking
-- Creates download_log table to record every PDF/file download event.
-- product_id is nullable: resolved from products.slug when DB is available.
-- Run once. Safe to re-run (IF NOT EXISTS guard).

CREATE TABLE IF NOT EXISTS `download_log` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `product_id`   INT UNSIGNED            DEFAULT NULL   COMMENT 'FK products.id — nullable for JSON-only setups',
    `product_slug` VARCHAR(128)            DEFAULT NULL   COMMENT 'product slug at time of download',
    `file_name`    VARCHAR(256)   NOT NULL DEFAULT ''     COMMENT 'human-readable label or basename',
    `file_url`     VARCHAR(512)   NOT NULL DEFAULT ''     COMMENT 'original file path/URL',
    `ip`           VARCHAR(45)    NOT NULL DEFAULT ''     COMMENT 'IPv4 or IPv6',
    `user_agent`   VARCHAR(255)   NOT NULL DEFAULT ''     COMMENT 'truncated UA string',
    `lang`         VARCHAR(10)    NOT NULL DEFAULT 'en'   COMMENT 'site language at request time',
    `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dl_product_slug` (`product_slug`),
    KEY `idx_dl_created_at`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Module 10 — one row per tracked file download';
