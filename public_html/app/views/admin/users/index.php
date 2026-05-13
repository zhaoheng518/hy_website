<?php $pageTitle = '用户与权限'; $activeMenu = 'users'; ?>
<div class="panel">
    <div class="panel-header"><h2>管理员账号</h2></div>
    <?php if (empty($users)): ?>
    <p>无法加载用户表或为空（请确认 MySQL users 表存在）。</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>ID</th><th>用户名</th><th>邮箱</th><th>角色</th><th>状态</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo (int) ($u['id'] ?? 0); ?></td>
                <td><?php echo htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($u['role'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo !empty($u['is_active']) ? '启用' : '禁用'; ?></td>
                <td>
                    <?php if (!empty($canManageUsers) && (int)($u['id'] ?? 0) !== (int)(\App\Core\Auth::user()['id'] ?? 0)): ?>
                    <form method="post" action="/admin/users/delete/<?php echo (int)($u['id'] ?? 0); ?>" onsubmit="return confirm('删除用户？');" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($canManageUsers)): ?>
    <h3 style="margin-top:24px;">新建用户</h3>
    <form method="post" action="/admin/users/create" class="admin-form">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="form-row">
            <div class="form-group"><label>用户名</label><input type="text" name="username" required></div>
            <div class="form-group"><label>邮箱</label><input type="email" name="email" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>密码 (≥8)</label><input type="password" name="password" required minlength="8"></div>
            <div class="form-group"><label>角色</label>
                <select name="role">
                    <option value="editor">editor</option>
                    <option value="admin">admin</option>
                    <option value="viewer">viewer（只读）</option>
                    <?php if (\App\Core\Auth::isSuperAdmin()): ?>
                    <option value="super_admin">super_admin</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">创建</button>
    </form>
    <?php endif; ?>

    <h3 style="margin-top:24px;">编辑账号权限 (JSON，键为用户 ID)</h3>
    <p class="text-sm">权限键（布尔 true / 字符串 "rw" 写读，"r" 仅读）：dashboard, home, products, categories, pages, blog, cases, inquiries, media, menu, seo, settings, languages, users。legacy：files 与 media 等价。super_admin 不受此表限制。</p>
    <?php if (!empty($canManageUsers)): ?>
    <form method="post" action="/admin/users" class="admin-form">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="save_permissions">
        <textarea name="permissions_json" rows="12" class="code-editor" style="width:100%;"><?php
        echo htmlspecialchars(json_encode($permMap ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
        ?></textarea>
        <button type="submit" class="btn btn-primary" style="margin-top:8px;">保存权限 JSON</button>
    </form>
    <?php else: ?>
    <p class="text-sm" style="margin-top:8px;color:var(--c-text-light);">您仅有查看权限；修改 JSON 需 users 写权限。</p>
    <textarea rows="12" class="code-editor" style="width:100%;margin-top:8px;" readonly><?php
        echo htmlspecialchars(json_encode($permMap ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
    ?></textarea>
    <?php endif; ?>

    <h3 style="margin-top:24px;">最近登录</h3>
    <table class="data-table">
        <thead><tr><th>时间</th><th>用户</th><th>IP</th></tr></thead>
        <tbody>
            <?php foreach (array_slice($loginLogs ?? [], 0, 30) as $log): ?>
            <tr>
                <td><?php echo htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($log['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($log['ip'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
