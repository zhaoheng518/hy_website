<?php $pageTitle = '询盘管理'; $activeMenu = 'inquiries'; ?>
<div class="panel">
    <div class="panel-header">
        <h2>询盘管理</h2>
        <div class="panel-actions">
            <?php
            $exportQuery = [
                '_csrf' => \App\Core\Auth::generateCsrfToken(),
                'status' => $currentFilter ?? '',
                'q' => $q ?? '',
                'date_from' => $date_from ?? '',
                'date_to' => $date_to ?? '',
            ];
            ?>
            <a href="/admin/inquiry_export?<?php echo htmlspecialchars(http_build_query(array_filter($exportQuery, function ($v) { return $v !== ''; })), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm">Export to CSV</a>
        </div>
    </div>

    <form method="get" class="admin-form" style="margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort ?? 'desc', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="form-group" style="margin:0;">
            <label>关键词</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="姓名/邮箱/公司">
        </div>
        <div class="form-group" style="margin:0;">
            <label>开始日期</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>结束日期</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <button type="submit" class="btn btn-sm btn-primary">搜索</button>
    </form>

    <div class="inquiry-filter-tabs">
        <a href="/admin/inquiries" class="filter-tab<?php echo empty($currentFilter) ? ' active' : ''; ?>">全部 <span class="filter-count"><?php echo (int) ($counts['all'] ?? 0); ?></span></a>
        <a href="/admin/inquiries?status=new" class="filter-tab<?php echo ($currentFilter ?? '') === 'new' ? ' active' : ''; ?>">新建 <span class="filter-count"><?php echo (int) ($counts['new'] ?? 0); ?></span></a>
        <a href="/admin/inquiries?status=contacted" class="filter-tab<?php echo ($currentFilter ?? '') === 'contacted' ? ' active' : ''; ?>">已联系 <span class="filter-count"><?php echo (int) ($counts['contacted'] ?? 0); ?></span></a>
        <a href="/admin/inquiries?status=quoted" class="filter-tab<?php echo ($currentFilter ?? '') === 'quoted' ? ' active' : ''; ?>">已报价 <span class="filter-count"><?php echo (int) ($counts['quoted'] ?? 0); ?></span></a>
        <a href="/admin/inquiries?status=closed" class="filter-tab<?php echo ($currentFilter ?? '') === 'closed' ? ' active' : ''; ?>">已关闭 <span class="filter-count"><?php echo (int) ($counts['closed'] ?? 0); ?></span></a>
    </div>

    <?php if (empty($inquiries)): ?>
    <div class="panel-empty">暂无询盘</div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>姓名</th><th>公司</th><th>邮箱</th><th>国家</th><th>产品</th><th>来源</th><th>日期</th><th>状态</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $labels = ['new' => '新建', 'contacted' => '已联系', 'quoted' => '已报价', 'closed' => '已关闭', 'replied' => '已联系', 'spam' => '已关闭'];
            foreach ($inquiries as $inquiry):
                $raw = $inquiry['status'] ?? 'new';
                $norm = $raw === 'replied' ? 'contacted' : ($raw === 'spam' ? 'closed' : $raw);
                if (!in_array($norm, ['new', 'contacted', 'quoted', 'closed'], true)) {
                    $norm = 'new';
                }
            ?>
            <tr class="<?php echo $norm === 'new' ? 'row-unread' : ''; ?>">
                <td><strong><?php echo htmlspecialchars($inquiry['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td><?php echo htmlspecialchars($inquiry['company'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><a href="mailto:<?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></td>
                <td><?php echo htmlspecialchars($inquiry['country'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($inquiry['product_slug'] ?? $inquiry['product_source'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php if (!empty($inquiry['source_url'])): ?><a href="<?php echo htmlspecialchars($inquiry['source_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="source-url-link"><?php echo htmlspecialchars(mb_strimwidth($inquiry['source_url'], 0, 36, '...'), ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?>-<?php endif; ?></td>
                <td><?php echo htmlspecialchars($inquiry['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <form method="post" action="/admin/inquiries/updateStatus/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <select name="status" class="status-inline-select" onchange="this.form.submit()">
                            <?php foreach (['new', 'contacted', 'quoted', 'closed'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $norm === $st ? 'selected' : ''; ?>><?php echo $labels[$st]; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td class="action-cell">
                    <a href="/admin/inquiries/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm">查看</a>
                    <form method="POST" action="/admin/inquiries/delete/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="inline-form" onsubmit="return confirm('删除？');">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if (($totalPages ?? 1) > 1): ?>
    <div style="padding:12px;display:flex;justify-content:space-between;">
        <span>共 <?php echo (int) ($total ?? 0); ?> 条</span>
        <span>
            <?php if (($page ?? 1) > 1): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">上一页</a><?php endif; ?>
            <?php if (($page ?? 1) < ($totalPages ?? 1)): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">下一页</a><?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<style>
.inquiry-filter-tabs{display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap;border-bottom:2px solid var(--c-border);}
.filter-tab{padding:8px 12px;margin-bottom:-2px;font-size:13px;color:var(--c-text-light);text-decoration:none;border-bottom:2px solid transparent;}
.filter-tab.active{color:var(--c-primary);border-bottom-color:var(--c-primary);}
.filter-count{font-size:11px;background:var(--c-bg);padding:1px 6px;border-radius:8px;margin-left:4px;}
.source-url-link{color:var(--c-primary);font-size:12px;}
.status-inline-select{font-size:12px;padding:4px;border-radius:4px;}
</style>
