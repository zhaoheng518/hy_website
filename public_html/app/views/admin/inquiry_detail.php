<?php $pageTitle = '询盘详情'; $activeMenu = 'inquiries'; ?>

<?php
$status = $inquiry['status'] ?? 'new';
$statusLabels = ['new' => '未读', 'replied' => '已回复', 'spam' => '垃圾邮件'];
$statusClass = ['new' => 'badge-new', 'replied' => 'badge-replied', 'spam' => 'badge-spam'];
?>

<div class="panel">
    <div class="panel-header">
        <h2>询盘来自 <?php echo htmlspecialchars($inquiry['name'] ?? '未知', ENT_QUOTES, 'UTF-8'); ?></h2>
        <a href="/admin/inquiries" class="btn btn-sm">&larr; 返回询盘列表</a>
    </div>

    <div class="detail-status-bar">
        <div class="status-bar-left">
            <span class="badge <?php echo $statusClass[$status] ?? 'badge-new'; ?>" id="current-status-badge">
                <?php echo $statusLabels[$status] ?? '未读'; ?>
            </span>
            <span class="status-bar-time">
                <?php echo htmlspecialchars($inquiry['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
        <div class="status-bar-right">
            <form method="POST" action="/admin/inquiries/updateStatus/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                  class="inline-form" id="status-form">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <select name="status" class="status-action-select" onchange="this.form.submit();">
                    <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>标记为未读</option>
                    <option value="replied" <?php echo $status === 'replied' ? 'selected' : ''; ?>>标记为已回复</option>
                    <option value="spam" <?php echo $status === 'spam' ? 'selected' : ''; ?>>标记为垃圾邮件</option>
                </select>
            </form>
        </div>
    </div>

    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">姓名</span>
            <span class="detail-value"><?php echo htmlspecialchars($inquiry['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">邮箱</span>
            <span class="detail-value">
                <a href="mailto:<?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">公司</span>
            <span class="detail-value"><?php echo htmlspecialchars($inquiry['company'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">WhatsApp/电话</span>
            <span class="detail-value"><?php echo htmlspecialchars($inquiry['phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">产品来源</span>
            <span class="detail-value"><?php echo htmlspecialchars($inquiry['product_source'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">来源页面</span>
            <span class="detail-value">
                <?php if (!empty($inquiry['source_url'])): ?>
                <a href="<?php echo htmlspecialchars($inquiry['source_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                    <?php echo htmlspecialchars($inquiry['source_url'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <?php else: ?>
                -
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">提交时间</span>
            <span class="detail-value"><?php echo htmlspecialchars($inquiry['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">IP地址</span>
            <span class="detail-value"><?php echo htmlspecialchars($inquiry['ip'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php if (!empty($inquiry['lang'])): ?>
        <div class="detail-item">
            <span class="detail-label">提交语言</span>
            <span class="detail-value"><?php echo strtoupper(htmlspecialchars($inquiry['lang'], ENT_QUOTES, 'UTF-8')); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($inquiry['read_at'])): ?>
        <div class="detail-item">
            <span class="detail-label">查看时间</span>
            <span class="detail-value"><?php echo htmlspecialchars($inquiry['read_at'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $hasUtm = !empty($inquiry['utm_source'])
           || !empty($inquiry['utm_medium'])
           || !empty($inquiry['utm_campaign'])
           || !empty($inquiry['utm_term'])
           || !empty($inquiry['utm_content'])
           || !empty($inquiry['referrer'])
           || !empty($inquiry['landing_page']);
    ?>
    <?php if ($hasUtm): ?>
    <div class="detail-attribution">
        <h3 class="detail-attribution-title">广告归因 / UTM</h3>
        <div class="detail-grid">
            <?php if (!empty($inquiry['utm_source'])): ?>
            <div class="detail-item">
                <span class="detail-label">UTM Source</span>
                <span class="detail-value"><?php echo htmlspecialchars($inquiry['utm_source'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($inquiry['utm_medium'])): ?>
            <div class="detail-item">
                <span class="detail-label">UTM Medium</span>
                <span class="detail-value"><?php echo htmlspecialchars($inquiry['utm_medium'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($inquiry['utm_campaign'])): ?>
            <div class="detail-item">
                <span class="detail-label">UTM Campaign</span>
                <span class="detail-value"><?php echo htmlspecialchars($inquiry['utm_campaign'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($inquiry['utm_term'])): ?>
            <div class="detail-item">
                <span class="detail-label">UTM Term</span>
                <span class="detail-value"><?php echo htmlspecialchars($inquiry['utm_term'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($inquiry['utm_content'])): ?>
            <div class="detail-item">
                <span class="detail-label">UTM Content</span>
                <span class="detail-value"><?php echo htmlspecialchars($inquiry['utm_content'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($inquiry['referrer'])): ?>
            <div class="detail-item">
                <span class="detail-label">Referrer</span>
                <span class="detail-value">
                    <a href="<?php echo htmlspecialchars($inquiry['referrer'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo htmlspecialchars($inquiry['referrer'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($inquiry['landing_page'])): ?>
            <div class="detail-item">
                <span class="detail-label">落地页</span>
                <span class="detail-value">
                    <a href="<?php echo htmlspecialchars($inquiry['landing_page'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo htmlspecialchars($inquiry['landing_page'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="detail-message">
        <h3>留言内容</h3>
        <div class="message-content">
            <?php echo nl2br(htmlspecialchars($inquiry['message'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
        </div>
    </div>

    <div class="form-actions">
        <a href="mailto:<?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">&#9993; 邮件回复</a>
        <?php if (!empty($inquiry['phone'])): ?>
        <a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $inquiry['phone']), ENT_QUOTES, 'UTF-8'); ?>"
           class="btn" target="_blank" rel="noopener">
            &#128172; WhatsApp
        </a>
        <?php endif; ?>
        <form method="POST" action="/admin/inquiries/delete/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              class="inline-form"
              onsubmit="return confirm('确定要删除此询盘吗？');">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-danger">删除询盘</button>
        </form>
    </div>
</div>

<style>
.detail-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: var(--c-bg);
    border-radius: var(--radius);
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.status-bar-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.status-bar-time {
    color: var(--c-text-light);
    font-size: 13px;
}
.badge-new { background: var(--c-primary-light); color: var(--c-primary); padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; }
.badge-replied { background: var(--c-success-light); color: var(--c-success); padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; }
.badge-spam { background: var(--c-warning-light); color: var(--c-warning); padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; }
.status-action-select {
    padding: 6px 12px;
    border: 1px solid var(--c-border);
    border-radius: var(--radius);
    font-size: 13px;
    cursor: pointer;
    background: var(--c-white);
}
.detail-attribution {
    margin-bottom: 20px;
    padding: 16px;
    background: var(--c-bg);
    border-radius: var(--radius);
    border-left: 3px solid var(--c-primary);
}
.detail-attribution-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--c-text-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 12px;
}
.detail-attribution .detail-grid {
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}
</style>
