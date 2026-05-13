-- Migration: 20260511_add_utm_fields.sql
-- Purpose:   Add UTM attribution + referrer + landing_page fields to inquiries table
-- Safety:    All columns DEFAULT NULL — existing rows are unaffected.
-- Run once:  If a column already exists MySQL will return error 1060; skip that statement.
-- -----------------------------------------------------------------------

ALTER TABLE `inquiries`
    ADD COLUMN `utm_source`   VARCHAR(256) DEFAULT NULL COMMENT 'UTM Source (e.g. google, facebook)'   AFTER `source_url`,
    ADD COLUMN `utm_medium`   VARCHAR(256) DEFAULT NULL COMMENT 'UTM Medium (e.g. cpc, email)'         AFTER `utm_source`,
    ADD COLUMN `utm_campaign` VARCHAR(256) DEFAULT NULL COMMENT 'UTM Campaign name/id'                 AFTER `utm_medium`,
    ADD COLUMN `utm_term`     VARCHAR(256) DEFAULT NULL COMMENT 'UTM Term (paid keyword)'              AFTER `utm_campaign`,
    ADD COLUMN `utm_content`  VARCHAR(256) DEFAULT NULL COMMENT 'UTM Content (ad variant)'             AFTER `utm_term`,
    ADD COLUMN `referrer`     VARCHAR(512) DEFAULT NULL COMMENT 'JS document.referrer; fallback: HTTP_REFERER' AFTER `utm_content`,
    ADD COLUMN `landing_page` VARCHAR(512) DEFAULT NULL COMMENT 'First page URL in session (JS sessionStorage)' AFTER `referrer`;

-- Index on utm_source for campaign-level reporting queries
ALTER TABLE `inquiries`
    ADD KEY `idx_inquiries_utm_source` (`utm_source`(64));
