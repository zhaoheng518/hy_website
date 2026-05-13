<?php $pageTitle = '页面管理'; $activeMenu = 'pages'; ?>

<div class="panel">
    <div class="panel-header">
        <h2>页面管理 (About / Factory / Contact / 自定义)</h2>
        <div class="panel-actions">
            <a href="/admin/pages/create?lang=<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-sm">+ 新增页面</a>
        </div>
    </div>

    <div class="lang-tabs" style="margin-bottom:16px;">
        <?php foreach ($supportedLangs as $l): ?>
        <a href="/admin/pages?lang=<?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?>" class="lang-tab<?php echo $editLang === $l ? ' active' : ''; ?>"><?php echo strtoupper($l); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($pages)): ?>
    <div class="panel-empty">暂无页面</div>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>标题</th><th>Slug</th><th>副标题</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($pages as $p): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($p['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
            <td><code><?php echo htmlspecialchars($p['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
            <td><?php echo htmlspecialchars(mb_strimwidth($p['subtitle'] ?? '', 0, 50, '...'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="action-cell">
                <a href="/admin/pages/edit/<?php echo htmlspecialchars($p['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?lang=<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm">编辑</a>
                <form method="POST" action="/admin/pages/delete/<?php echo htmlspecialchars($p['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?lang=<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>" class="inline-form" onsubmit="return confirm('确定删除？');">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
