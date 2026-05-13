-- ---------------------------------------------------------------------------
-- Legacy upgrade: add `category_id` only if an old `products` table omits it.
-- Before running:  SHOW COLUMNS FROM `products` LIKE 'category_id';
-- If this returns a row, you do NOT need this migration.
-- ---------------------------------------------------------------------------

ALTER TABLE `products`
    ADD COLUMN `category_id` INT UNSIGNED DEFAULT NULL COMMENT '所属分类ID' AFTER `slug`,
    ADD KEY `idx_products_category` (`category_id`);

-- Optional: add FK after `categories` table exists and uses INT UNSIGNED `id`.
-- ALTER TABLE `products`
--     ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
--     REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
