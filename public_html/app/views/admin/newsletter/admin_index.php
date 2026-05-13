<?php $pageTitle = '邮件订阅'; $activeMenu = 'newsletter'; ?>
<div class="panel">
    <div class="panel-header" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;justify-content:space-between;">
        <h2>邮件订阅</h2>
        <?php if (!empty($newsletterAdvanced)): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="/admin/newsletter/jobs" class="btn btn-sm">任务队列 / 群发</a>
            <a href="/admin/newsletter/events" class="btn btn-sm">Webhook 事件</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($success)): ?>
    <div class="form-message success" style="margin:12px 16px;"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="form-message error" style="margin:12px 16px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (empty($newsletterAdvanced)): ?>
    <p style="margin:8px 16px 12px;font-size:13px;color:#64748b;line-height:1.5;">
        当前为轻量模式：仅管理订阅者。队列、群发与 Webhook 事件库默认关闭；投递与打开等请在 <strong>Brevo</strong> 控制台查看。需要时在 <code>site.json</code> 设置 <code>&quot;newsletter_advanced&quot;: true</code> 后刷新本页。
    </p>
    <?php endif; ?>

    <div style="margin:0 16px 8px;font-size:13px;font-weight:600;color:var(--c-text,#334155);">订阅概览</div>
    <div class="newsletter-stats-row" style="display:flex;flex-wrap:wrap;gap:12px;margin:0 16px 16px;">
        <div class="newsletter-stat-box" style="flex:1;min-width:110px;padding:14px 16px;border:1px solid var(--c-border,#ddd);border-radius:8px;background:var(--c-bg,#fafafa);">
            <div style="font-size:22px;font-weight:700;color:var(--c-primary,#2563eb);"><?php echo (int) ($statTotalSubscribers ?? 0); ?></div>
            <div style="font-size:12px;color:var(--c-text-light,#666);margin-top:4px;">总订阅数</div>
        </div>
        <div class="newsletter-stat-box" style="flex:1;min-width:110px;padding:14px 16px;border:1px solid var(--c-border,#ddd);border-radius:8px;background:var(--c-bg,#fafafa);">
            <div style="font-size:22px;font-weight:700;color:#16a34a;"><?php echo (int) ($statActiveSubscribers ?? 0); ?></div>
            <div style="font-size:12px;color:var(--c-text-light,#666);margin-top:4px;">启用订阅</div>
        </div>
        <div class="newsletter-stat-box" style="flex:1;min-width:110px;padding:14px 16px;border:1px solid var(--c-border,#ddd);border-radius:8px;background:var(--c-bg,#fafafa);">
            <div style="font-size:22px;font-weight:700;color:#64748b;"><?php echo (int) ($statUnsubscribed ?? 0); ?></div>
            <div style="font-size:12px;color:var(--c-text-light,#666);margin-top:4px;">退订/停用</div>
        </div>
        <div class="newsletter-stat-box" style="flex:1;min-width:110px;padding:14px 16px;border:1px solid var(--c-border,#ddd);border-radius:8px;background:var(--c-bg,#fafafa);">
            <div style="font-size:22px;font-weight:700;color:#a855f7;"><?php echo number_format((float) ($statUnsubscribeRate ?? 0), 2, '.', ''); ?>%</div>
            <div style="font-size:12px;color:var(--c-text-light,#666);margin-top:4px;">退订率</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">停用人数 / 总订阅</div>
        </div>
        <div class="newsletter-stat-box" style="flex:1;min-width:110px;padding:14px 16px;border:1px solid var(--c-border,#ddd);border-radius:8px;background:var(--c-bg,#fafafa);opacity:0.92;">
            <div style="font-size:22px;font-weight:700;color:#94a3b8;">—</div>
            <div style="font-size:12px;color:var(--c-text-light,#666);margin-top:4px;">打开率 <span style="font-size:10px;color:#cbd5e1;">(预留)</span></div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">需追踪像素或 ESP 回传</div>
        </div>
    </div>

    <?php if (!empty($newsletterAdvanced)): ?>
    <div style="margin:0 16px 8px;font-size:13px;font-weight:600;color:var(--c-text,#334155);">发送队列</div>
    <div class="newsletter-stats-row" style="display:flex;flex-wrap:wrap;gap:12px;margin:0 16px 20px;">
        <div class="newsletter-stat-box" style="flex:1;min-width:110px;padding:14px 16px;border:1px solid var(--c-border,#ddd);border-radius:8px;background:var(--c-bg,#fafafa);">
            <div style="font-size:22px;font-weight:700;color:#1e40af;"><?php echo (int) ($statSendTotal ?? 0); ?></div>
            <div style="font-size:12px;color:var(--c-text-light,#666);margin-top:4px;">总发送数</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">累计邮件任务数</div>
        </div>
        <div class="newsletter-stat-box" style="flex:1;min-width:110px;padding:14px 16px;border:1px solid var(--c-border,#ddd);border-radius:8px;background:var(--c-bg,#fafafa);">
            <div style="font-size:22px;font-weight:700;color:#0d9488;"><?php echo (int) ($statSendSuccess ?? 0); ?></div>
            <div style="font-size:12px;color:var(--c-text-light,#666);margin-top:4px;">发送成功</div>
        </div>
        <div class="newsletter-stat-box" style="flex:1;min-width:110px;padding:14px 16px;border:1px solid var(--c-border,#ddd);border-radius:8px;background:var(--c-bg,#fafafa);">
            <div style="font-size:22px;font-weight:700;color:#dc2626;"><?php echo (int) ($statSendFailed ?? 0); ?></div>
            <div style="font-size:12px;color:var(--c-text-light,#666);margin-top:4px;">发送失败</div>
        </div>
        <div class="newsletter-stat-box" style="flex:1;min-width:110px;padding:14px 16px;border:1px solid var(--c-border,#ddd);border-radius:8px;background:var(--c-bg,#fafafa);" title="含排队(pending)与发送中(sending)">
            <div style="font-size:22px;font-weight:700;color:#ca8a04;"><?php echo (int) ($statSendPending ?? 0); ?></div>
            <div style="font-size:12px;color:var(--c-text-light,#666);margin-top:4px;">待发送</div>
            <?php if (((int) ($statSendPendingOnly ?? 0)) > 0 || ((int) ($statSendSending ?? 0)) > 0): ?>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">排队 <?php echo (int) ($statSendPendingOnly ?? 0); ?> · 发送中 <?php echo (int) ($statSendSending ?? 0); ?></div>
            <?php else: ?>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">队列为空</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <form method="get" class="admin-form" style="margin:0 16px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>邮箱</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="搜索邮箱">
        </div>
        <div class="form-group" style="margin:0;">
            <label>状态</label>
            <select name="status">
                <option value="" <?php echo ($status ?? '') === '' ? 'selected' : ''; ?>>全部</option>
                <option value="active" <?php echo ($status ?? '') === 'active' ? 'selected' : ''; ?>>活跃</option>
                <option value="inactive" <?php echo ($status ?? '') === 'inactive' ? 'selected' : ''; ?>>已停用</option>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">筛选</button>
    </form>

    <?php if (empty($rows)): ?>
    <div class="panel-empty">暂无订阅者</div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>邮箱</th>
                <th>语言</th>
                <th>产品</th>
                <th>博客</th>
                <th>一般</th>
                <th>来源</th>
                <th>最近询盘产品</th>
                <th>标签</th>
                <th>状态</th>
                <th>创建</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                <td><a href="mailto:<?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                <td><?php echo htmlspecialchars((string) ($row['lang'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo !empty($row['notify_product']) ? '是' : '—'; ?></td>
                <td><?php echo !empty($row['notify_blog']) ? '是' : '—'; ?></td>
                <td><?php echo !empty($row['notify_general']) ? '是' : '—'; ?></td>
                <td><?php echo htmlspecialchars((string) ($row['source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="max-width:140px;font-size:12px;"><?php
                    $lip = (string) ($row['last_inquiry_product'] ?? '');
                    echo $lip !== '' ? htmlspecialchars($lip, ENT_QUOTES, 'UTF-8') : '—';
                ?></td>
                <td style="max-width:220px;font-size:11px;word-break:break-all;"><?php
                    $tagsRaw = $row['tags'] ?? '';
                    $tagsArr = is_string($tagsRaw) ? json_decode($tagsRaw, true) : (is_array($tagsRaw) ? $tagsRaw : []);
                    $tagsStr = is_array($tagsArr) ? implode(', ', array_filter(array_map('strval', $tagsArr))) : '';
                    if ($tagsStr === '') {
                        echo '—';
                    } else {
                        $short = function_exists('mb_strimwidth')
                            ? mb_strimwidth($tagsStr, 0, 80, '…', 'UTF-8')
                            : (strlen($tagsStr) > 80 ? substr($tagsStr, 0, 77) . '...' : $tagsStr);
                        echo htmlspecialchars($short, ENT_QUOTES, 'UTF-8');
                    }
                ?></td>
                <td><?php echo !empty($row['is_active']) ? '<span style="color:green;">活跃</span>' : '<span style="color:#999;">停用</span>'; ?></td>
                <td><?php echo htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="action-cell">
                    <form method="post" action="/admin/newsletter/toggle" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                        <input type="hidden" name="active" value="<?php echo !empty($row['is_active']) ? '0' : '1'; ?>">
                        <button type="submit" class="btn btn-sm"><?php echo !empty($row['is_active']) ? '停用' : '启用'; ?></button>
                    </form>
                    <form method="post" action="/admin/newsletter/delete" class="inline-form" onsubmit="return confirm('确定删除该订阅？');">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
    $__p = (int) ($page ?? 1);
    $__tp = (int) ($totalPages ?? 1);
    ?>
    <?php if ($__tp > 1): ?>
    <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
        <span>共 <?php echo (int) ($total ?? 0); ?> 条</span>
        <span>
            <?php if ($__p > 1): ?>
            <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $__p - 1])), ENT_QUOTES, 'UTF-8'); ?>">上一页</a>
            <?php endif; ?>
            &nbsp;第 <?php echo $__p; ?> / <?php echo $__tp; ?> 页&nbsp;
            <?php if ($__p < $__tp): ?>
            <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $__p + 1])), ENT_QUOTES, 'UTF-8'); ?>">下一页</a>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
