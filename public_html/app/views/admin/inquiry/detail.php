<?php
$pageTitle = '询盘详情';
$activeMenu = 'inquiries';
$ds = $inquiry['display_status'] ?? 'new';
$labels = ['new' => '新建', 'contacted' => '已联系', 'quoted' => '已报价', 'closed' => '已关闭'];
?>
<div class="panel">
    <div class="panel-header">
        <h2>询盘：<?php echo htmlspecialchars($inquiry['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
        <a href="/admin/inquiries" class="btn btn-sm">&larr; 返回列表</a>
    </div>

    <div class="detail-status-bar">
        <span class="badge badge-new"><?php echo htmlspecialchars($labels[$ds] ?? $ds, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="status-bar-time"><?php echo htmlspecialchars($inquiry['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
        <form method="POST" action="/admin/inquiries/updateStatus/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="inline-form" style="margin-left:auto;">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <select name="status" class="status-action-select" onchange="this.form.submit();">
                <?php foreach (['new', 'contacted', 'quoted', 'closed'] as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $ds === $st ? 'selected' : ''; ?>><?php echo $labels[$st]; ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">姓名</span><span class="detail-value"><?php echo htmlspecialchars($inquiry['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="detail-item"><span class="detail-label">邮箱</span><span class="detail-value"><a href="mailto:<?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></span></div>
        <div class="detail-item"><span class="detail-label">公司</span><span class="detail-value"><?php echo htmlspecialchars($inquiry['company'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="detail-item"><span class="detail-label">电话</span><span class="detail-value"><?php echo htmlspecialchars($inquiry['phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="detail-item"><span class="detail-label">国家</span><span class="detail-value"><?php echo htmlspecialchars($inquiry['country'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="detail-item"><span class="detail-label">产品 slug</span><span class="detail-value"><?php echo htmlspecialchars($inquiry['product_slug'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="detail-item"><span class="detail-label">产品来源</span><span class="detail-value"><?php echo htmlspecialchars($inquiry['product_source'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="detail-item"><span class="detail-label">来源 URL</span><span class="detail-value"><?php if (!empty($inquiry['source_url'])): ?><a href="<?php echo htmlspecialchars($inquiry['source_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?php echo htmlspecialchars($inquiry['source_url'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?>-<?php endif; ?></span></div>
        <div class="detail-item"><span class="detail-label">IP</span><span class="detail-value"><?php echo htmlspecialchars($inquiry['ip'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php if (!empty($inquiry['lang'])): ?>
        <div class="detail-item"><span class="detail-label">语言</span><span class="detail-value"><?php echo htmlspecialchars(strtoupper($inquiry['lang']), ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php endif; ?>
    </div>

    <div class="detail-message">
        <h3>留言</h3>
        <div class="message-content"><?php echo nl2br(htmlspecialchars($inquiry['message'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
    </div>

    <div class="form-actions">
        <a href="mailto:<?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">邮件回复</a>
        <form method="POST" action="/admin/inquiries/delete/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="inline-form" onsubmit="return confirm('删除？');">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-danger">删除</button>
        </form>
    </div>
</div>
<style>
.detail-status-bar{display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--c-bg);border-radius:var(--radius);margin-bottom:16px;flex-wrap:wrap;}
.status-bar-time{color:var(--c-text-light);font-size:13px;}
.status-action-select{padding:6px 12px;border:1px solid var(--c-border);border-radius:var(--radius);}
.badge-new{background:var(--c-primary-light);color:var(--c-primary);padding:4px 12px;border-radius:12px;font-size:13px;font-weight:600;}
</style>
