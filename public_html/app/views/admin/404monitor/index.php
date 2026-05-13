<?php
$pageTitle = '404 监控';
$activeMenu = '404monitor';

$e = function (string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
};

$entries     = $entries     ?? [];
$totalEntries = $totalEntries ?? 0;
$totalHits   = $totalHits   ?? 0;
$currentPage = $currentPage ?? 1;
$totalPages  = $totalPages  ?? 1;
$q           = $q           ?? '';
$csrfToken   = $csrfToken   ?? '';
?>

<div class="space-y-6">

    <!-- ══ Summary cards ══════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $totalEntries; ?></div>
                <div class="text-sm text-gray-500">独立 404 URL</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo number_format($totalHits); ?></div>
                <div class="text-sm text-gray-500">总命中次数</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900">500</div>
                <div class="text-sm text-gray-500">日志上限（条）</div>
            </div>
        </div>
    </div>

    <!-- ══ Filter + clear ══════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 flex flex-wrap gap-3 items-center">
        <form method="get" action="/admin/404monitor" class="flex gap-2 items-center flex-1 min-w-0">
            <input type="text" name="q" value="<?php echo $e($q); ?>" placeholder="搜索 URL…"
                   class="flex-1 min-w-0 rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
            <button type="submit" class="rounded-md bg-primary-600 text-white px-4 py-2 text-sm hover:bg-primary-700 shrink-0">搜索</button>
            <?php if ($q !== ''): ?>
            <a href="/admin/404monitor" class="rounded-md border border-gray-300 bg-white text-gray-600 px-4 py-2 text-sm hover:bg-gray-50 shrink-0">重置</a>
            <?php endif; ?>
        </form>

        <?php if ($totalEntries > 0): ?>
        <form method="post" action="/admin/404monitor/clear" id="form-clear-all">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrfToken); ?>">
            <button type="button" id="btn-clear-all"
                    class="rounded-md border border-red-300 text-red-600 px-4 py-2 text-sm hover:bg-red-50">
                清空全部日志
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- ══ Table ════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">404 记录（按频次降序）</h3>
            <span class="text-sm text-gray-500">
                第 <?php echo (($currentPage - 1) * 50) + 1; ?>–<?php echo min($currentPage * 50, $totalEntries); ?> 条（共 <?php echo $totalEntries; ?> 条）
            </span>
        </div>

        <?php if (empty($entries)): ?>
        <div class="px-5 py-12 text-center">
            <svg class="w-12 h-12 text-green-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-lg font-semibold text-green-700">暂无 404 记录</p>
            <p class="text-sm text-gray-500 mt-1"><?php echo $q !== '' ? '无匹配结果，尝试清空搜索条件' : '太棒了！目前没有检测到 404 错误'; ?></p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase">404 路径</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase w-16">次数</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase w-32">首次</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase w-32">最近</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase w-40">来源 Referrer</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase w-24">IP</th>
                        <th class="px-4 py-3 text-end text-xs font-semibold text-gray-600 uppercase w-36">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="monitor-table-body">

                <?php foreach ($entries as $idx => $entry):
                    $entryUrl  = (string) ($entry['url']           ?? '');
                    $count     = (int)   ($entry['count']          ?? 0);
                    $firstSeen = (string) ($entry['first_seen']    ?? '');
                    $lastSeen  = (string) ($entry['last_seen']     ?? '');
                    $referer   = (string) ($entry['last_referrer'] ?? '');
                    $ip        = (string) ($entry['last_ip']       ?? '');
                    $ua        = (string) ($entry['last_ua']       ?? '');
                    $rowId     = 'row-' . md5($entryUrl);
                    $formId    = 'form-redirect-' . md5($entryUrl);
                    $panelId   = 'panel-redirect-' . md5($entryUrl);

                    $countClass = $count >= 10 ? 'text-red-700 font-bold' : ($count >= 3 ? 'text-orange-600 font-semibold' : 'text-gray-600');
                ?>
                <tr class="hover:bg-gray-50 transition-colors" id="<?php echo $e($rowId); ?>">
                    <td class="px-4 py-3 align-top">
                        <div class="font-mono text-xs text-gray-800 break-all"><?php echo $e($entryUrl); ?></div>
                        <?php if ($ua !== ''): ?>
                        <div class="text-xs text-gray-400 mt-0.5 truncate max-w-xs" title="<?php echo $e($ua); ?>">UA: <?php echo $e(mb_substr($ua, 0, 60)); ?>…</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 align-top text-center">
                        <span class="inline-flex items-center justify-center w-10 h-7 rounded-full text-xs <?php echo $countClass; ?> bg-gray-100">
                            <?php echo $count; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 align-top text-xs text-gray-500 whitespace-nowrap"><?php echo $e($firstSeen); ?></td>
                    <td class="px-4 py-3 align-top text-xs text-gray-500 whitespace-nowrap"><?php echo $e($lastSeen); ?></td>
                    <td class="px-4 py-3 align-top">
                        <?php if ($referer !== ''): ?>
                        <a href="<?php echo $e($referer); ?>" target="_blank" rel="noopener noreferrer"
                           class="text-xs text-primary-600 hover:underline break-all" title="<?php echo $e($referer); ?>">
                            <?php echo $e(mb_substr($referer, 0, 40)); ?>…
                        </a>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 align-top text-xs text-gray-600 font-mono whitespace-nowrap"><?php echo $e($ip ?: '—'); ?></td>
                    <td class="px-4 py-3 align-top text-end">
                        <div class="inline-flex flex-col gap-1.5 items-end">
                            <button type="button"
                                    class="btn-toggle-redirect text-xs rounded-md bg-primary-600 text-white px-2.5 py-1 hover:bg-primary-700 whitespace-nowrap"
                                    data-target="<?php echo $e($panelId); ?>">
                                添加 301
                            </button>
                            <form method="post" action="/admin/404monitor/dismiss" class="form-dismiss inline">
                                <input type="hidden" name="_csrf" value="<?php echo $e($csrfToken); ?>">
                                <input type="hidden" name="url"  value="<?php echo $e($entryUrl); ?>">
                                <button type="button" class="btn-dismiss text-xs rounded-md border border-gray-300 text-gray-500 px-2.5 py-1 hover:bg-gray-100 whitespace-nowrap">
                                    忽略
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>

                <!-- Inline 301 redirect form (hidden by default) -->
                <tr id="<?php echo $e($panelId); ?>" class="redirect-panel hidden bg-blue-50">
                    <td colspan="7" class="px-4 py-3">
                        <form method="post" action="/admin/404monitor/addRedirect" id="<?php echo $e($formId); ?>"
                              class="flex flex-wrap gap-2 items-end">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrfToken); ?>">
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs font-medium text-gray-600 mb-1">来源路径（From）</label>
                                <input type="text" name="from"
                                       value="<?php echo $e($entryUrl); ?>"
                                       class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm font-mono bg-white focus:ring-primary-500 focus:border-primary-500">
                            </div>
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs font-medium text-gray-600 mb-1">目标路径（To）</label>
                                <input type="text" name="to" required placeholder="/en/products/new-slug"
                                       class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                        class="rounded-md bg-green-600 text-white px-4 py-1.5 text-sm font-medium hover:bg-green-700">
                                    保存 301
                                </button>
                                <button type="button"
                                        class="btn-cancel-redirect rounded-md border border-gray-300 text-gray-600 px-4 py-1.5 text-sm hover:bg-gray-100"
                                        data-target="<?php echo $e($panelId); ?>">
                                    取消
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <!-- ── Pagination ── -->
        <?php if ($totalPages > 1): ?>
        <div class="px-5 py-4 border-t border-gray-200 flex items-center justify-between">
            <span class="text-sm text-gray-500">共 <?php echo $totalPages; ?> 页</span>
            <div class="flex gap-1">
                <?php if ($currentPage > 1): ?>
                <a href="/admin/404monitor?page=<?php echo $currentPage - 1; ?>&q=<?php echo urlencode($q); ?>"
                   class="px-3 py-1.5 rounded-md border border-gray-300 text-sm hover:bg-gray-50">上一页</a>
                <?php endif; ?>
                <?php
                $pStart = max(1, $currentPage - 2);
                $pEnd   = min($totalPages, $currentPage + 2);
                for ($p = $pStart; $p <= $pEnd; $p++): ?>
                <a href="/admin/404monitor?page=<?php echo $p; ?>&q=<?php echo urlencode($q); ?>"
                   class="px-3 py-1.5 rounded-md border text-sm <?php echo $p === $currentPage ? 'bg-primary-600 border-primary-600 text-white' : 'border-gray-300 hover:bg-gray-50'; ?>">
                    <?php echo $p; ?>
                </a>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                <a href="/admin/404monitor?page=<?php echo $currentPage + 1; ?>&q=<?php echo urlencode($q); ?>"
                   class="px-3 py-1.5 rounded-md border border-gray-300 text-sm hover:bg-gray-50">下一页</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<script>
(function () {
    'use strict';

    // ── Toggle inline 301 redirect panel ─────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-toggle-redirect');
        if (btn) {
            var targetId = btn.getAttribute('data-target');
            var panel = document.getElementById(targetId);
            if (panel) {
                panel.classList.toggle('hidden');
                if (!panel.classList.contains('hidden')) {
                    var input = panel.querySelector('input[name="to"]');
                    if (input) { input.focus(); }
                }
            }
            return;
        }

        var cancelBtn = e.target.closest('.btn-cancel-redirect');
        if (cancelBtn) {
            var targetId2 = cancelBtn.getAttribute('data-target');
            var panel2 = document.getElementById(targetId2);
            if (panel2) { panel2.classList.add('hidden'); }
            return;
        }
    });

    // ── Dismiss confirm ───────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-dismiss');
        if (!btn) { return; }
        if (!confirm('确认忽略该 404 记录？')) { return; }
        var form = btn.closest('.form-dismiss');
        if (form) { form.submit(); }
    });

    // ── Clear all confirm ─────────────────────────────────────────────────────
    var btnClear = document.getElementById('btn-clear-all');
    if (btnClear) {
        btnClear.addEventListener('click', function () {
            if (confirm('确认清空全部 404 日志？此操作不可恢复。')) {
                document.getElementById('form-clear-all').submit();
            }
        });
    }
})();
</script>
