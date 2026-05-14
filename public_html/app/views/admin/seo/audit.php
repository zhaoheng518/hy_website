<?php
$pageTitle = 'SEO 健康检查';
$activeMenu = 'seo_audit';

// ── Helpers ───────────────────────────────────────────────────────────────────
$summary    = $auditResult['summary']    ?? [];
$issues     = $auditResult['issues']     ?? [];
$totalPages = $auditResult['total_pages'] ?? 0;
$scannedAt  = $auditResult['scanned_at'] ?? '';
$totalIssues    = (int)  ($summary['total']             ?? 0);
$crit           = (int)  ($summary['critical']          ?? 0);
$warn           = (int)  ($summary['warning']           ?? 0);
$info           = (int)  ($summary['info']              ?? 0);
$missingAlt     = (int)  ($summary['missing_alt']       ?? 0);
$brokenLinks    = (int)  ($summary['broken_links']      ?? 0);
$missingOg      = (int)  ($summary['missing_og_image']  ?? 0);
$slugIssues     = (int)  ($summary['slug_issues']       ?? 0);
$securityWarn   = (int)  ($summary['security_warnings'] ?? 0);
$robotsBlocked  = (bool) ($summary['robots_blocked']    ?? false);

$e = function (string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
};

// Type labels
$typeLabels = [
    'product' => '产品',
    'blog'    => '博客',
    'case'    => '案例',
    'page'    => '页面',
    'global'  => '全局',
];

// Pagination
$perPage     = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;
$totalRows   = count($issues);
$totalPagesP = (int) ceil($totalRows / $perPage);
$pagedIssues = array_slice($issues, $offset, $perPage);

// Build filter query string helper
function auditFilterUrl(string $lang, string $type, int $page = 1): string {
    $q = [];
    if ($lang !== '') $q['lang'] = $lang;
    if ($type !== '') $q['type'] = $type;
    if ($page > 1)    $q['page'] = $page;
    return '/admin/seo/audit' . (empty($q) ? '' : '?' . http_build_query($q));
}
?>

<div class="space-y-6">

    <!-- ══ robots_block_all critical banner ═══════════════════════════════════ -->
    <?php if ($robotsBlocked): ?>
    <div class="flex items-start gap-3 rounded-lg border border-red-400 bg-red-50 px-5 py-4">
        <svg class="w-6 h-6 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
        </svg>
        <div>
            <p class="font-bold text-red-700 text-sm">全站 Noindex 已开启 — 搜索引擎无法索引整站！</p>
            <p class="text-red-600 text-xs mt-1">site.json 中 <code class="font-mono bg-red-100 px-1 rounded">robots_block_all</code> 为 true。
               请立即前往 <a href="/admin/seo" class="underline font-medium">SEO 总控</a> 关闭，除非你有意屏蔽索引。</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ 404 Monitor companion link ════════════════════════════════════════ -->
    <div class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
        <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
        </svg>
        <p class="text-sm text-red-700 flex-1">
            404 错误会直接流失 SEO 权重。配合 404 监控，找到高频死链并一键创建 301 修复。
        </p>
        <a href="/admin/404monitor"
           class="shrink-0 rounded-md border border-red-300 bg-white text-red-700 px-3 py-1.5 text-xs font-medium hover:bg-red-100 transition-colors whitespace-nowrap">
            查看 404 监控 →
        </a>
    </div>

    <!-- ══ Summary cards ══════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Total issues -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $totalIssues; ?></div>
                <div class="text-sm text-gray-500">总问题数</div>
                <div class="text-xs text-gray-400 mt-0.5">扫描 <?php echo $totalPages; ?> 个页面</div>
            </div>
        </div>

        <!-- Critical -->
        <div class="bg-white rounded-lg shadow-sm border border-red-200 p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-red-700"><?php echo $crit; ?></div>
                <div class="text-sm text-red-500">严重</div>
                <div class="text-xs text-red-400 mt-0.5">需立即修复</div>
            </div>
        </div>

        <!-- Warning -->
        <div class="bg-white rounded-lg shadow-sm border border-yellow-200 p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-yellow-700"><?php echo $warn; ?></div>
                <div class="text-sm text-yellow-500">警告</div>
                <div class="text-xs text-yellow-400 mt-0.5">建议修复</div>
            </div>
        </div>

        <!-- Info -->
        <div class="bg-white rounded-lg shadow-sm border border-blue-200 p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-blue-600"><?php echo $info; ?></div>
                <div class="text-sm text-blue-500">提示</div>
                <div class="text-xs text-blue-400 mt-0.5">可选优化</div>
            </div>
        </div>
    </div>

    <!-- ══ Specialised counters row ══════════════════════════════════════════ -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">

        <!-- Missing ALT -->
        <div class="bg-white rounded-lg shadow-sm border border-orange-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="text-xl font-bold text-orange-700"><?php echo $missingAlt; ?></div>
                <div class="text-xs text-orange-500">图片 ALT 缺失</div>
            </div>
        </div>

        <!-- Broken Links -->
        <div class="bg-white rounded-lg shadow-sm border border-red-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
            </div>
            <div>
                <div class="text-xl font-bold text-red-700"><?php echo $brokenLinks; ?></div>
                <div class="text-xs text-red-500">死链 / 危险链接</div>
            </div>
        </div>

        <!-- Missing OG Image -->
        <div class="bg-white rounded-lg shadow-sm border border-purple-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
            </div>
            <div>
                <div class="text-xl font-bold text-purple-700"><?php echo $missingOg; ?></div>
                <div class="text-xs text-purple-500">缺失 OG 图片</div>
            </div>
        </div>

        <!-- Slug Issues -->
        <div class="bg-white rounded-lg shadow-sm border border-yellow-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </div>
            <div>
                <div class="text-xl font-bold text-yellow-700"><?php echo $slugIssues; ?></div>
                <div class="text-xs text-yellow-600">Slug 问题</div>
            </div>
        </div>

        <!-- Security Warnings -->
        <div class="bg-white rounded-lg shadow-sm border border-rose-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <div class="text-xl font-bold text-rose-700"><?php echo $securityWarn; ?></div>
                <div class="text-xs text-rose-500">安全警告</div>
            </div>
        </div>

    </div>

    <!-- ══ Top-issues breakdown ════════════════════════════════════════════════ -->
    <?php if (!empty($summary['by_check'])): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h3 class="text-base font-semibold text-gray-800 mb-3">问题分布</h3>
        <div class="flex flex-wrap gap-2">
            <?php
            arsort($summary['by_check']);
            foreach ($summary['by_check'] as $chk => $cnt):
            ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700">
                <?php echo $e((string) $chk); ?>
                <span class="font-semibold text-gray-900"><?php echo (int) $cnt; ?></span>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ Filter bar ══════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 flex flex-wrap gap-3 items-end">
        <form method="get" action="/admin/seo/audit" class="flex flex-wrap gap-3 items-end w-full">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">语言</label>
                <select name="lang" class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                    <option value="">全部语言</option>
                    <?php foreach ($supportedLangs as $lng): ?>
                    <option value="<?php echo $e((string) $lng); ?>" <?php echo $filterLang === $lng ? 'selected' : ''; ?>>
                        <?php echo strtoupper($e((string) $lng)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">页面类型</label>
                <select name="type" class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                    <option value="">全部类型</option>
                    <?php foreach ($validTypes as $vt): ?>
                    <option value="<?php echo $e($vt); ?>" <?php echo $filterType === $vt ? 'selected' : ''; ?>>
                        <?php echo $e($typeLabels[$vt] ?? $vt); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-md bg-primary-600 text-white px-4 py-2 text-sm font-medium hover:bg-primary-700">筛选</button>
            <?php if ($filterLang !== '' || $filterType !== ''): ?>
            <a href="/admin/seo/audit" class="rounded-md border border-gray-300 bg-white text-gray-600 px-4 py-2 text-sm hover:bg-gray-50">重置</a>
            <?php endif; ?>

            <div class="ms-auto text-xs text-gray-400 self-center">
                扫描时间：<?php echo $e($scannedAt); ?>
                &nbsp;·&nbsp;
                共 <?php echo $totalRows; ?> 条问题
                &nbsp;·&nbsp;
                <a href="/admin/seo/export?lang=<?php echo $e($filterLang); ?>&amp;type=<?php echo $e($filterType); ?>"
                   class="text-primary-600 hover:underline">导出 TXT</a>
            </div>
        </form>
    </div>

    <!-- ══ Issues table ════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">问题列表</h3>
            <span class="text-sm text-gray-500">
                第 <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?> 条（共 <?php echo $totalRows; ?> 条）
            </span>
        </div>

        <?php if (empty($issues)): ?>
        <div class="px-5 py-12 text-center">
            <svg class="w-12 h-12 text-green-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-lg font-semibold text-green-700">全部检查通过！</p>
            <p class="text-sm text-gray-500 mt-1">当前筛选条件下未发现 SEO 问题</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase w-24">风险等级</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase w-20">类型</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase w-14">语言</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase w-36">检查项</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase">问题详情</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase w-52">修复建议</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php foreach ($pagedIssues as $issue):
                    $sev   = (string) ($issue['severity'] ?? '');
                    $iType = (string) ($issue['type']     ?? '');
                    $iLang = (string) ($issue['lang']     ?? '');
                    $iUrl  = (string) ($issue['url']      ?? '');
                    $check = (string) ($issue['check']    ?? '');
                    $detail = (string) ($issue['detail']  ?? '');
                    $fix   = (string) ($issue['fix']      ?? '');

                    $sevClasses = [
                        'critical' => 'bg-red-100 text-red-700 border border-red-200',
                        'warning'  => 'bg-yellow-100 text-yellow-700 border border-yellow-200',
                        'info'     => 'bg-blue-100 text-blue-600 border border-blue-200',
                    ];
                    $sevLabels = [
                        'critical' => '严重',
                        'warning'  => '警告',
                        'info'     => '提示',
                    ];
                    $rowBg = [
                        'critical' => 'hover:bg-red-50',
                        'warning'  => 'hover:bg-yellow-50',
                        'info'     => 'hover:bg-blue-50',
                    ];
                ?>
                <tr class="<?php echo $rowBg[$sev] ?? 'hover:bg-gray-50'; ?> transition-colors">
                    <td class="px-4 py-3 align-top">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold <?php echo $sevClasses[$sev] ?? 'bg-gray-100 text-gray-600'; ?>">
                            <?php echo $e($sevLabels[$sev] ?? $sev); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 align-top text-gray-600">
                        <?php echo $e($typeLabels[$iType] ?? $iType); ?>
                    </td>
                    <td class="px-4 py-3 align-top">
                        <?php if ($iLang !== ''): ?>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-mono bg-gray-100 text-gray-700">
                            <?php echo $e(strtoupper($iLang)); ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 align-top font-medium text-gray-800">
                        <?php echo $e($check); ?>
                    </td>
                    <td class="px-4 py-3 align-top text-gray-600">
                        <div><?php echo $e($detail); ?></div>
                        <?php if ($iUrl !== '' && $iUrl !== '/admin/seo'): ?>
                        <div class="mt-1">
                            <a href="<?php echo $e($iUrl); ?>" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 text-xs text-primary-600 hover:underline font-mono">
                                <?php echo $e($iUrl); ?>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 align-top text-gray-500 text-xs">
                        <?php echo $e($fix); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination ── -->
        <?php if ($totalPagesP > 1): ?>
        <div class="px-5 py-4 border-t border-gray-200 flex items-center justify-between">
            <span class="text-sm text-gray-500">共 <?php echo $totalPagesP; ?> 页</span>
            <div class="flex gap-1">
                <?php if ($currentPage > 1): ?>
                <a href="<?php echo $e(auditFilterUrl($filterLang, $filterType, $currentPage - 1)); ?>"
                   class="px-3 py-1.5 rounded-md border border-gray-300 text-sm hover:bg-gray-50">上一页</a>
                <?php endif; ?>

                <?php
                $start = max(1, $currentPage - 2);
                $end   = min($totalPagesP, $currentPage + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                <a href="<?php echo $e(auditFilterUrl($filterLang, $filterType, $p)); ?>"
                   class="px-3 py-1.5 rounded-md border text-sm <?php echo $p === $currentPage ? 'bg-primary-600 border-primary-600 text-white' : 'border-gray-300 hover:bg-gray-50'; ?>">
                    <?php echo $p; ?>
                </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPagesP): ?>
                <a href="<?php echo $e(auditFilterUrl($filterLang, $filterType, $currentPage + 1)); ?>"
                   class="px-3 py-1.5 rounded-md border border-gray-300 text-sm hover:bg-gray-50">下一页</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ══ CLI instructions ════════════════════════════════════════════════════ -->
    <div class="bg-slate-800 rounded-lg p-5 text-slate-300 text-sm">
        <p class="font-semibold text-white mb-2">CLI 使用方式</p>
        <div class="font-mono text-xs space-y-1 text-slate-400">
            <p><span class="text-green-400">$</span> php app/commands/SeoAudit.php</p>
            <p><span class="text-green-400">$</span> php app/commands/SeoAudit.php --lang=en</p>
            <p><span class="text-green-400">$</span> php app/commands/SeoAudit.php --type=product</p>
            <p><span class="text-green-400">$</span> php app/commands/SeoAudit.php --limit=200</p>
            <p><span class="text-green-400">$</span> php app/commands/SeoAudit.php --lang=en --type=product --limit=100 --format=json</p>
        </div>
    </div>

</div>
