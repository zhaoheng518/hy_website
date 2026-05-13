<?php $pageTitle = 'Newsletter 队列'; $activeMenu = 'newsletter'; ?>
<div class="panel">
    <div class="panel-header" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;justify-content:space-between;">
        <h2>Newsletter 队列</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="/admin/newsletter" class="btn btn-sm">← 订阅者列表</a>
            <a href="/admin/newsletter/events" class="btn btn-sm">Webhook 事件</a>
        </div>
    </div>

    <?php if (!empty($success)): ?>
    <div class="form-message success" style="margin:12px 16px;"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="form-message error" style="margin:12px 16px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div style="margin:12px 16px;font-size:13px;color:#64748b;">
        订阅：共 <?php echo (int) ($statTotalSubscribers ?? 0); ?>，活跃 <?php echo (int) ($statActiveSubscribers ?? 0); ?>。
        队列：累计 <?php echo (int) ($statSendTotal ?? 0); ?>，成功 <?php echo (int) ($statSendSuccess ?? 0); ?>，失败 <?php echo (int) ($statSendFailed ?? 0); ?>，待处理 <?php echo (int) ($statSendPending ?? 0); ?>。
    </div>

    <div style="margin:0 16px 16px;padding:16px;border:1px solid var(--c-border,#e2e8f0);border-radius:8px;background:var(--c-bg,#fafafa);">
        <h3 style="margin:0 0 12px;font-size:15px;">手动群发（notify_general=1 的活跃订阅者）</h3>
        <form method="post" action="/admin/newsletter/broadcast" class="admin-form">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="broadcast_subject">主题</label>
                <input type="text" id="broadcast_subject" name="broadcast_subject" required maxlength="512" style="width:100%;max-width:560px;">
            </div>
            <div class="form-group">
                <label for="broadcast_html">HTML 正文</label>
                <textarea id="broadcast_html" name="broadcast_html" rows="8" required style="width:100%;max-width:900px;font-family:monospace;font-size:13px;"></textarea>
            </div>
            <div class="form-group">
                <label for="broadcast_text">纯文本（可选，留空则从 HTML 去标签生成）</label>
                <textarea id="broadcast_text" name="broadcast_text" rows="4" style="width:100%;max-width:900px;font-family:monospace;font-size:13px;"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('确认为所有「一般订阅」活跃订阅者入队？');">入队群发</button>
        </form>
    </div>

    <form method="get" action="/admin/newsletter/jobs" class="admin-form" style="margin:0 16px 12px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>任务状态</label>
            <select name="job_status" onchange="this.form.submit()">
                <option value="" <?php echo ($jobStatusFilter ?? '') === '' ? 'selected' : ''; ?>>全部</option>
                <option value="pending" <?php echo ($jobStatusFilter ?? '') === 'pending' ? 'selected' : ''; ?>>pending</option>
                <option value="sending" <?php echo ($jobStatusFilter ?? '') === 'sending' ? 'selected' : ''; ?>>sending</option>
                <option value="sent" <?php echo ($jobStatusFilter ?? '') === 'sent' ? 'selected' : ''; ?>>sent</option>
                <option value="failed" <?php echo ($jobStatusFilter ?? '') === 'failed' ? 'selected' : ''; ?>>failed</option>
            </select>
        </div>
    </form>

    <div class="table-responsive" style="margin:0 16px 16px;">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>订阅者</th>
                <th>类型</th>
                <th>状态</th>
                <th>计划发送</th>
                <th>retry</th>
                <th>messageId</th>
                <th>错误摘要</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($jobRows)): ?>
            <tr><td colspan="8" class="panel-empty">暂无任务</td></tr>
            <?php else: ?>
            <?php foreach ($jobRows as $jr): ?>
            <tr>
                <td><?php echo (int) ($jr['id'] ?? 0); ?></td>
                <td><?php echo (int) ($jr['subscriber_id'] ?? 0); ?></td>
                <td><?php echo htmlspecialchars((string) ($jr['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($jr['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="font-size:12px;"><?php echo htmlspecialchars((string) ($jr['send_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) ($jr['retry_count'] ?? 0); ?> / <?php echo (int) ($jr['max_retry'] ?? 0); ?></td>
                <td style="font-size:11px;max-width:140px;word-break:break-all;"><?php
                    $mid = (string) ($jr['provider_message_id'] ?? '');
                    echo $mid !== '' ? htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') : '—';
                ?></td>
                <td style="font-size:11px;max-width:220px;"><?php
                    $err = (string) ($jr['error_message'] ?? '');
                    if ($err === '') {
                        echo '—';
                    } else {
                        $short = function_exists('mb_strimwidth') ? mb_strimwidth($err, 0, 120, '…', 'UTF-8') : substr($err, 0, 117) . '...';
                        echo htmlspecialchars($short, ENT_QUOTES, 'UTF-8');
                    }
                ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php
    $__jp = (int) ($jobPage ?? 1);
    $__jtp = (int) ($jobTotalPages ?? 1);
    ?>
    <?php if ($__jtp > 1): ?>
    <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
        <span>共 <?php echo (int) ($jobTotal ?? 0); ?> 条</span>
        <span>
            <?php if ($__jp > 1): ?>
            <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $__jp - 1])), ENT_QUOTES, 'UTF-8'); ?>">上一页</a>
            <?php endif; ?>
            &nbsp;第 <?php echo $__jp; ?> / <?php echo $__jtp; ?> 页&nbsp;
            <?php if ($__jp < $__jtp): ?>
            <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $__jp + 1])), ENT_QUOTES, 'UTF-8'); ?>">下一页</a>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
</div>
