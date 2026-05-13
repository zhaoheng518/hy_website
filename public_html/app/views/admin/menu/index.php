<?php $pageTitle = '菜单'; $activeMenu = 'menu'; ?>
<div class="panel">
    <div class="panel-header"><h2>主导航 / Footer</h2></div>
    <p class="text-sm text-gray-600 mb-4">编辑 JSON：<code>header</code> / <code>footer</code> 数组，每项含 label, url, sort, enabled (true/false)。前台需在 layout 中读取 Config 或后续接入。</p>
    <form method="post" action="/admin/menu" class="admin-form">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <textarea name="menus_json" rows="22" class="code-editor" style="width:100%;font-family:monospace;"><?php
        echo htmlspecialchars(json_encode($menus ?? ['header' => [], 'footer' => []], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
        ?></textarea>
        <div class="form-actions" style="margin-top:12px;">
            <button type="submit" class="btn btn-primary">保存</button>
        </div>
    </form>
</div>
