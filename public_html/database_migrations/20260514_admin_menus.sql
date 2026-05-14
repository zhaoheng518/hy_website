-- =====================================================
-- admin_menus — 后台 Sidebar 动态菜单（可多级）
-- 执行前请备份数据库。
-- 扩展字段说明（在需求字段之外，为兼容现有 Auth 与高亮）：
--   active_key   对应各页 $activeMenu，多个用英文逗号分隔
--   permission_key  对应 Auth::can($key,'read')，空字符串表示任意已登录管理员可见
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `admin_menus` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL=顶级',
    `title` VARCHAR(128) NOT NULL COMMENT '显示标题',
    `icon` VARCHAR(64) NOT NULL DEFAULT 'default' COMMENT '图标预设键，见 AdminMenuService::iconSvg',
    `route` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '链接，如 /admin/products；父级分组可用 #',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `is_collapsible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '有子级时是否可折叠',
    `active_key` VARCHAR(255) DEFAULT NULL COMMENT '高亮匹配 activeMenu，逗号分隔',
    `permission_key` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '权限键，空=不校验',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_parent_sort` (`parent_id`, `sort_order`, `id`),
    KEY `idx_visible` (`is_visible`),
    CONSTRAINT `fk_admin_menus_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `admin_menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台侧边栏菜单';

-- 清空后重跑本段可重复初始化（谨慎）
-- TRUNCATE TABLE admin_menus;

-- 顶级
INSERT INTO `admin_menus` (`id`, `parent_id`, `title`, `icon`, `route`, `sort_order`, `is_visible`, `is_collapsible`, `active_key`, `permission_key`) VALUES
(1, NULL, '仪表盘', 'dashboard', '/admin', 10, 1, 0, 'dashboard', 'dashboard');

-- 首页管理
INSERT INTO `admin_menus` (`id`, `parent_id`, `title`, `icon`, `route`, `sort_order`, `is_visible`, `is_collapsible`, `active_key`, `permission_key`) VALUES
(10, NULL, '首页管理', 'folder', '#', 20, 1, 1, '', 'home'),
(11, 10, '横幅设置', 'image', '/admin/home/banner', 10, 1, 0, 'home_banner', 'home'),
(12, 10, '产品展示', 'package', '/admin/home/products', 20, 1, 0, 'home_products', 'home'),
(13, 10, '工厂介绍', 'factory', '/admin/home/factory', 30, 1, 0, 'home_factory', 'home'),
(14, 10, '成功案例', 'star', '/admin/home/cases', 40, 1, 0, 'home_cases', 'home'),
(15, 10, '关于我们', 'info', '/admin/home/about', 50, 1, 0, 'home_about', 'home'),
(16, 10, '博客新闻', 'blog', '/admin/home/blog', 60, 1, 0, 'home_blog', 'home'),
(17, 10, '联系信息', 'mail', '/admin/home/contact', 70, 1, 0, 'home_contact', 'home');

-- 内容管理
INSERT INTO `admin_menus` (`id`, `parent_id`, `title`, `icon`, `route`, `sort_order`, `is_visible`, `is_collapsible`, `active_key`, `permission_key`) VALUES
(20, NULL, '内容管理', 'folder', '#', 30, 1, 1, '', ''),
(21, 20, '产品管理', 'package', '/admin/products', 10, 1, 0, 'products', 'products'),
(22, 20, '产品分类', 'tag', '/admin/categories', 20, 1, 0, 'categories', 'categories'),
(23, 20, '页面管理', 'document', '/admin/pages', 30, 1, 0, 'pages', 'pages'),
(24, 20, '页面 CMS', 'edit', '/admin/page', 40, 1, 0, 'page_cms', 'pages'),
(25, 20, '博客管理', 'blog', '/admin/blog', 50, 1, 0, 'blog,home_blog', 'blog'),
(26, 20, '案例管理', 'star', '/admin/case', 60, 1, 0, 'cases_admin,case,home_cases', 'cases'),
(27, 20, '询盘中心', 'chat', '/admin/inquiries', 70, 1, 0, 'inquiries', 'inquiries'),
(28, 20, '邮件订阅', 'mail', '/admin/newsletter', 80, 1, 0, 'newsletter', 'newsletter'),
(29, 20, '下载统计', 'chart', '/admin/downloads', 90, 1, 0, 'downloads', 'inquiries');

-- 系统
INSERT INTO `admin_menus` (`id`, `parent_id`, `title`, `icon`, `route`, `sort_order`, `is_visible`, `is_collapsible`, `active_key`, `permission_key`) VALUES
(30, NULL, '系统', 'cog', '#', 40, 1, 1, '', ''),
(31, 30, '媒体库', 'image', '/admin/media', 10, 1, 0, 'media', 'media'),
(32, 30, '文件管理', 'folder', '/admin/files', 20, 1, 0, 'files', 'media'),
(33, 30, '前台菜单', 'menu', '/admin/menu', 30, 1, 0, 'menu', 'menu'),
(34, 30, '侧边栏菜单', 'menu', '/admin/menu-settings', 35, 1, 0, 'menu_settings', 'settings'),
(35, 30, 'SEO 总控', 'search', '/admin/seo', 40, 1, 0, 'seo', 'seo'),
(36, 30, 'SEO 健康检查', 'clipboard', '/admin/seo/audit', 50, 1, 0, 'seo_audit', 'seo'),
(37, 30, '404 监控', 'ban', '/admin/404monitor', 60, 1, 0, '404monitor', 'settings'),
(38, 30, '301 重定向', 'link', '/admin/redirects', 70, 1, 0, 'redirects', 'redirects'),
(39, 30, '备份中心', 'archive', '/admin/backup', 80, 1, 0, 'backup', 'settings'),
(40, 30, '系统设置', 'cog', '/admin/settings', 90, 1, 0, 'settings', 'settings'),
(41, 30, '用户权限', 'users', '/admin/users', 100, 1, 0, 'users', 'users'),
(42, 30, '多语言数据', 'globe', '/admin/languages', 110, 1, 0, 'languages', 'languages'),
(43, 30, '首页板块', 'document', '/admin/sections', 115, 1, 0, 'sections', 'sections');

ALTER TABLE `admin_menus` AUTO_INCREMENT = 100;

SET FOREIGN_KEY_CHECKS = 1;
