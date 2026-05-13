-- 扩展 users.role，加入 super_admin（与 Auth 权限模型一致）
-- 执行后建议将首个管理员改为 super_admin 以免细粒度权限为空时无法进入后台：
-- UPDATE users SET role = 'super_admin' WHERE id = 1 LIMIT 1;

ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('super_admin', 'admin', 'editor', 'viewer')
    NOT NULL DEFAULT 'admin' COMMENT '角色权限';
