<?php $pageTitle = 'Newsletter 事件'; $activeMenu = 'newsletter'; ?>
<div class="panel">
    <div class="panel-header" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;justify-content:space-between;">
        <h2>Newsletter Webhook 事件</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="/admin/newsletter" class="btn btn-sm">订阅者</a>
            <a href="/admin/newsletter/jobs" class="btn btn-sm">任务队列</a>
        </div>
    </div>

    <?php if (!empty($success)): ?>
    <div class="form-message success" style="margin:12px 16px;"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="form-message error" style="margin:12px 16px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div style="margin:12px 16px;font-size:13px;color:#64748b;">
        最近 30 天统计：
        Delivered <strong><?php echo (int) ($statDelivered ?? 0); ?></strong>
        · Opened <strong><?php echo (int) ($statOpened ?? 0); ?></strong>
        · Clicked <strong><?php echo (int) ($statClicked ?? 0); ?></strong>
        · Bounced <strong><?php echo (int) ($statBounced ?? 0); ?></strong>
        · Unsubscribed <strong><?php echo (int) ($statUnsubscribed ?? 0); ?></strong>
    </div>

    <form method="get" action="/admin/newsletter/events" class="admin-form" style="margin:0 16px 12px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>事件类型</label>
            <select name="event_type" onchange="this.form.submit()">
                <option value="" <?php echo ($eventTypeFilter ?? '') === '' ? 'selected' : ''; ?>>全部</option>
                <option value="delivered" <?php echo ($eventTypeFilter ?? '') === 'delivered' ? 'selected' : ''; ?>>delivered</option>
                <option value="opened" <?php echo ($eventTypeFilter ?? '') === 'opened' ? 'selected' : ''; ?>>opened</option>
                <option value="clicked" <?php echo ($eventTypeFilter ?? '') === 'clicked' ? 'selected' : ''; ?>>clicked</option>
                <option value="bounced" <?php echo ($eventTypeFilter ?? '') === 'bounced' ? 'selected' : ''; ?>>bounced</option>
                <option value="spam" <?php echo ($eventTypeFilter ?? '') === 'spam' ? 'selected' : ''; ?>>spam</option>
                <option value="invalid" <?php echo ($eventTypeFilter ?? '') === 'invalid' ? 'selected' : ''; ?>>invalid</option>
                <option value="unsubscribed" <?php echo ($eventTypeFilter ?? '') === 'unsubscribed' ? 'selected' : ''; ?>>unsubscribed</option>
                <option value="unknown" <?php echo ($eventTypeFilter ?? '') === 'unknown' ? 'selected' : ''; ?>>unknown</option>
            </select>
        </div>
    </form>

    <div class="table-responsive" style="margin:0 16px 16px;">
    <table class="data-table">
        <thead>
            <tr>
                <th>时间</th>
                <th>事件类型</th>
                <th>邮箱</th>
                <th>message_id</th>
                <th>job_id</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($eventRows)): ?>
            <tr><td colspan="5" class="panel-empty">暂无事件</td></tr>
            <?php else: ?>
            <?php foreach ($eventRows as $er): ?>
            <?php
                $pl = json_decode((string) ($er['payload'] ?? '{}'), true);
                $em = is_array($pl) ? (string) ($pl['email'] ?? '') : '';
            ?>
            <tr>
                <td style="font-size:12px;"><?php echo htmlspecialchars((string) ($er['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($er['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $em !== '' ? htmlspecialchars($em, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                <td style="font-size:11px;max-width:180px;word-break:break-all;"><?php
                    $mid = (string) ($er['provider_message_id'] ?? '');
                    echo $mid !== '' ? htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') : '—';
                ?></td>
                <td><?php
                    $jid = (int) ($er['job_id'] ?? 0);
                    echo $jid > 0 ? (string) $jid : '—';
                ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php
    $__ep = (int) ($eventPage ?? 1);
    $__etp = (int) ($eventTotalPages ?? 1);
    $qbase = array_filter([
        'event_type' => (string) ($eventTypeFilter ?? ''),
    ], static fn ($v) => $v !== '');
    ?>
    <?php if ($__etp > 1): ?>
    <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
        <span>共 <?php echo (int) ($eventTotal ?? 0); ?> 条</span>
        <span>
            <?php if ($__ep > 1): ?>
            <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($qbase, ['event_page' => $__ep - 1])), ENT_QUOTES, 'UTF-8'); ?>">上一页</a>
            <?php endif; ?>
            &nbsp;第 <?php echo $__ep; ?> / <?php echo $__etp; ?> 页&nbsp;
            <?php if ($__ep < $__etp): ?>
            <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($qbase, ['event_page' => $__ep + 1])), ENT_QUOTES, 'UTF-8'); ?>">下一页</a>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
</div>
