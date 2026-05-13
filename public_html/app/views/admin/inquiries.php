<?php $pageTitle = '询盘管理'; $activeMenu = 'inquiries'; ?>

<div class="panel">
    <div class="panel-header">
        <h2>询盘管理</h2>
        <div class="panel-actions">
            <a href="/admin/inquiries/export?_csrf=<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($currentFilter) ? '&status=' . htmlspecialchars($currentFilter, ENT_QUOTES, 'UTF-8') : ''; ?>"
               class="btn btn-sm" title="导出CSV">&#128229; 导出 CSV</a>
        </div>
    </div>

    <div class="inquiry-filter-tabs">
        <a href="/admin/inquiries"
           class="filter-tab<?php echo empty($currentFilter) ? ' active' : ''; ?>">
            全部 <span class="filter-count"><?php echo (int)($counts['all'] ?? 0); ?></span>
        </a>
        <a href="/admin/inquiries?status=new"
           class="filter-tab<?php echo $currentFilter === 'new' ? ' active' : ''; ?>">
            &#128308; 未读 <span class="filter-count"><?php echo (int)($counts['new'] ?? 0); ?></span>
        </a>
        <a href="/admin/inquiries?status=replied"
           class="filter-tab<?php echo $currentFilter === 'replied' ? ' active' : ''; ?>">
            &#128994; 已回复 <span class="filter-count"><?php echo (int)($counts['replied'] ?? 0); ?></span>
        </a>
        <a href="/admin/inquiries?status=spam"
           class="filter-tab<?php echo $currentFilter === 'spam' ? ' active' : ''; ?>">
            &#128311; 垃圾邮件 <span class="filter-count"><?php echo (int)($counts['spam'] ?? 0); ?></span>
        </a>
    </div>

    <?php if (empty($inquiries)): ?>
        <div class="panel-empty">暂无询盘</div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>公司</th>
                    <th>邮箱</th>
                    <th>产品来源</th>
                    <th>来源页面</th>
                    <th>日期</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inquiries as $inquiry): ?>
                <?php
                    $status = $inquiry['status'] ?? 'new';
                    $statusLabels = ['new' => '未读', 'replied' => '已回复', 'spam' => '垃圾邮件'];
                    $statusClass = ['new' => 'badge-new', 'replied' => 'badge-replied', 'spam' => 'badge-spam'];
                ?>
                <tr class="<?php echo $status === 'new' ? 'row-unread' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars($inquiry['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><?php echo htmlspecialchars($inquiry['company'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <a href="mailto:<?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($inquiry['product_source'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php if (!empty($inquiry['source_url'])): ?>
                        <a href="<?php echo htmlspecialchars($inquiry['source_url'], ENT_QUOTES, 'UTF-8'); ?>"
                           target="_blank" class="source-url-link" title="<?php echo htmlspecialchars($inquiry['source_url'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(mb_strimwidth($inquiry['source_url'], 0, 40, '...'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($inquiry['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <div class="status-dropdown" data-id="<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="badge <?php echo $statusClass[$status] ?? 'badge-new'; ?>">
                                <?php echo $statusLabels[$status] ?? '未读'; ?>
                            </span>
                            <select class="status-select" onchange="changeInquiryStatus(this)" data-id="<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>未读</option>
                                <option value="replied" <?php echo $status === 'replied' ? 'selected' : ''; ?>>已回复</option>
                                <option value="spam" <?php echo $status === 'spam' ? 'selected' : ''; ?>>垃圾邮件</option>
                            </select>
                        </div>
                    </td>
                    <td class="action-cell">
                        <a href="/admin/inquiries/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           class="btn btn-sm">查看</a>
                        <form method="POST" action="/admin/inquiries/delete/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                              class="inline-form"
                              onsubmit="return confirm('确定要删除此询盘吗？');">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<style>
.inquiry-filter-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--c-border);
    padding-bottom: 0;
}
.filter-tab {
    padding: 8px 16px;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    font-size: 14px;
    font-weight: 500;
    color: var(--c-text-light);
    text-decoration: none;
    transition: all 0.15s;
    white-space: nowrap;
}
.filter-tab:hover { color: var(--c-primary); text-decoration: none; }
.filter-tab.active { color: var(--c-primary); border-bottom-color: var(--c-primary); }
.filter-count {
    display: inline-block;
    background: var(--c-bg);
    color: var(--c-text-light);
    font-size: 11px;
    padding: 1px 6px;
    border-radius: 10px;
    margin-left: 4px;
    font-weight: 600;
}
.filter-tab.active .filter-count {
    background: rgba(37,99,235,0.1);
    color: var(--c-primary);
}
.badge-new { background: var(--c-primary-light); color: var(--c-primary); }
.badge-replied { background: var(--c-success-light); color: var(--c-success); }
.badge-spam { background: var(--c-warning-light); color: var(--c-warning); }
.status-dropdown { position: relative; }
.status-select {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    font-size: 12px;
}
.source-url-link {
    color: var(--c-primary);
    font-size: 12px;
    max-width: 160px;
    display: inline-block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: bottom;
}
.text-muted { color: var(--c-text-light); }
.table-responsive { overflow-x: auto; }
</style>

<script>
function changeInquiryStatus(selectEl) {
    var id = selectEl.getAttribute('data-id');
    var status = selectEl.value;
    var csrf = '<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, "UTF-8"); ?>';

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/inquiries/updateStatus/' + id;
    form.innerHTML = '<input type="hidden" name="_csrf" value="' + csrf + '">' +
        '<input type="hidden" name="status" value="' + status + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
