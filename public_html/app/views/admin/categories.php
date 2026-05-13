<?php $pageTitle = '产品分类管理'; $activeMenu = 'categories'; ?>

<div class="panel">
    <div class="panel-header">
        <h2>产品分类管理</h2>
        <div class="panel-actions">
            <a href="/admin/categories/create?lang=<?php echo $editLang; ?>" class="btn btn-primary btn-sm">+ 新增分类</a>
        </div>
    </div>

    <div class="lang-tabs" style="margin-bottom:16px;">
        <?php foreach ($supportedLangs as $l): ?>
        <a href="/admin/categories?lang=<?php echo $l; ?>" class="lang-tab<?php echo $editLang === $l ? ' active' : ''; ?>"><?php echo strtoupper($l); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($categories)): ?>
    <div class="panel-empty">暂无分类，请点击"新增分类"添加。</div>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>分类名称</th><th>别名 (Slug)</th><th>描述</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
            <td><code><?php echo htmlspecialchars($cat['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
            <td><?php echo htmlspecialchars(mb_strimwidth($cat['description'] ?? '', 0, 60, '...'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="action-cell">
                <a href="/admin/categories/edit/<?php echo htmlspecialchars($cat['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?lang=<?php echo $editLang; ?>" class="btn btn-sm">编辑</a>
                <form method="POST" action="/admin/categories/delete/<?php echo htmlspecialchars($cat['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?lang=<?php echo $editLang; ?>" class="inline-form" onsubmit="return confirm('确定删除此分类？');">
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
