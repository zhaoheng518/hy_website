<?php $pageTitle = '文件管理'; $activeMenu = 'files'; ?>
<div class="panel">
    <div class="panel-header"><h2>文件管理</h2></div>
    <div class="lang-tabs" style="margin-bottom:16px;">
        <?php foreach ($buckets as $b): ?>
        <a href="/admin/files?bucket=<?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?>" class="lang-tab<?php echo ($bucket ?? '') === $b ? ' active' : ''; ?>"><?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" class="admin-form" style="margin-bottom:16px;display:flex;gap:8px;">
        <input type="hidden" name="bucket" value="<?php echo htmlspecialchars($bucket ?? 'images', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="搜索文件名">
        <button type="submit" class="btn btn-sm">搜索</button>
    </form>
    <form method="post" action="/admin/files/upload" enctype="multipart/form-data" class="admin-form" style="margin-bottom:20px;">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="bucket" value="<?php echo htmlspecialchars($bucket ?? 'images', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="file" name="file" required>
        <input type="text" name="replace_name" placeholder="替换：当前目录下已有文件名（可选）" style="min-width:220px;margin-left:8px;" autocomplete="off">
        <button type="submit" class="btn btn-primary btn-sm">上传</button>
    </form>
    <?php if (empty($files)): ?><div class="panel-empty">无文件</div><?php else: ?>
    <table class="data-table">
        <thead><tr><th>文件</th><th>URL</th><th>大小</th><th>时间</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($files as $f): ?>
            <tr>
                <td><?php echo htmlspecialchars($f['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><a href="<?php echo htmlspecialchars($f['url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" target="_blank">打开</a></td>
                <td><?php echo (int) ($f['size'] ?? 0); ?> B</td>
                <td><?php echo htmlspecialchars($f['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <form method="post" action="/admin/files/delete" onsubmit="return confirm('删除？');">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="bucket" value="<?php echo htmlspecialchars($bucket ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="name" value="<?php echo htmlspecialchars($f['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
