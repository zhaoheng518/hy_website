-- B2B 特种电缆产品扩展字段（与 app/data/*/products.json 键名对齐）
-- 在已有库上执行一次；新建库可直接使用根目录 database.sql

ALTER TABLE `products`
    ADD COLUMN `product_model` VARCHAR(160) DEFAULT NULL COMMENT '型号（B2B）' AFTER `category_id`,
    ADD COLUMN `product_series` VARCHAR(160) DEFAULT NULL COMMENT '系列（B2B）' AFTER `product_model`,
    ADD COLUMN `moq` VARCHAR(128) DEFAULT NULL COMMENT '最小起订量' AFTER `product_series`,
    ADD COLUMN `lead_time` VARCHAR(160) DEFAULT NULL COMMENT '交货期' AFTER `moq`,
    ADD COLUMN `datasheet_files` JSON DEFAULT NULL COMMENT '附加资料' AFTER `lead_time`,
    ADD COLUMN `customizable_options` JSON DEFAULT NULL COMMENT '可定制项' AFTER `datasheet_files`,
    ADD COLUMN `download_center` JSON DEFAULT NULL COMMENT '下载中心' AFTER `customizable_options`,
    ADD COLUMN `related_products` JSON DEFAULT NULL COMMENT '关联 slug' AFTER `download_center`;

ALTER TABLE `product_translations`
    ADD COLUMN `short_description` TEXT DEFAULT NULL COMMENT 'B2B 摘要' AFTER `short_desc`,
    ADD COLUMN `product_structure` MEDIUMTEXT DEFAULT NULL COMMENT '产品结构' AFTER `content`,
    ADD COLUMN `technical_specs` MEDIUMTEXT DEFAULT NULL COMMENT '技术规格' AFTER `product_structure`,
    ADD COLUMN `electrical_characteristics` MEDIUMTEXT DEFAULT NULL COMMENT '电气性能' AFTER `technical_specs`,
    ADD COLUMN `mechanical_characteristics` MEDIUMTEXT DEFAULT NULL COMMENT '机械性能' AFTER `electrical_characteristics`,
    ADD COLUMN `environmental_characteristics` MEDIUMTEXT DEFAULT NULL COMMENT '环境性能' AFTER `mechanical_characteristics`,
    ADD COLUMN `applications` MEDIUMTEXT DEFAULT NULL COMMENT '应用领域' AFTER `environmental_characteristics`,
    ADD COLUMN `compliance_standards` MEDIUMTEXT DEFAULT NULL COMMENT '符合标准' AFTER `applications`,
    ADD COLUMN `tdk_tags` TEXT DEFAULT NULL COMMENT '附加 meta JSON' AFTER `compliance_standards`,
    ADD COLUMN `seo_keywords` VARCHAR(512) DEFAULT NULL COMMENT 'SEO 关键词' AFTER `seo_desc`,
    ADD COLUMN `canonical_url` VARCHAR(512) DEFAULT NULL COMMENT '规范链接' AFTER `seo_keywords`,
    ADD COLUMN `faq_json` MEDIUMTEXT DEFAULT NULL COMMENT 'FAQ JSON' AFTER `canonical_url`;
