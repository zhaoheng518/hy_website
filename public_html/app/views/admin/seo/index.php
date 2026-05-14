<?php $pageTitle = 'SEO 总控'; $activeMenu = 'seo'; 

// ── SEO Health Audit Summary ────────────────────────────────────────────
$auditReport = [];
$reportPath = DATA_PATH . '/seo_audit_report.json';
if (is_file($reportPath)) {
    $raw = @file_get_contents($reportPath);
    if ($raw !== false) {
        $auditReport = @json_decode($raw, true) ?: [];
    }
}

$summary    = $auditReport['summary']    ?? [];
$scannedAt  = $auditReport['scanned_at'] ?? '';
$totalIssues = (int) ($summary['total']      ?? 0);
$critical    = (int) ($summary['critical']   ?? 0);
$warning     = (int) ($summary['warning']    ?? 0);
$info        = (int) ($summary['info']       ?? 0);

// ── 404 Log Summary ─────────────────────────────────────────────────────
$fourOhFourStats = [
    'total'   => 0,
    'active'  => 0,
    'ignored' => 0,
];
$fourOhFourTopEntries = [];

try {
    $all404Entries = \App\Core\NotFoundLogger::readAll('hit_count', true);
    $fourOhFourStats['total'] = count($all404Entries);
    
    foreach ($all404Entries as $entry) {
        $isIgnored = (bool) ($entry['ignored'] ?? false);
        if ($isIgnored) {
            $fourOhFourStats['ignored']++;
        } else {
            $fourOhFourStats['active']++;
            if (count($fourOhFourTopEntries) < 10) {
                $fourOhFourTopEntries[] = $entry;
            }
        }
    }
} catch (\Throwable $e) {
    // Silently ignore - 404 logging may not be initialized yet
}

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>

<!-- ══ SEO Health Audit Summary Card ════════════════════════════════════ -->
<div class="panel mb-6">
    <div class="panel-header flex items-center justify-between">
        <h2>SEO 健康检查</h2>
        <form method="post" action="/admin/seo/rescan" class="inline">
            <input type="hidden" name="_csrf" value="<?php echo e($csrfToken); ?>">
            <button type="submit" class="btn btn-sm btn-outline-primary">
                重新扫描
            </button>
        </form>
    </div>
    <div class="panel-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Total issues -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-2xl font-bold text-gray-900"><?php echo $totalIssues; ?></div>
                <div class="text-sm text-gray-500">总问题数</div>
            </div>
            <!-- Critical -->
            <div class="bg-white rounded-lg shadow-sm border border-red-200 p-4">
                <div class="text-2xl font-bold text-red-700"><?php echo $critical; ?></div>
                <div class="text-sm text-red-500">严重</div>
            </div>
            <!-- Warning -->
            <div class="bg-white rounded-lg shadow-sm border border-yellow-200 p-4">
                <div class="text-2xl font-bold text-yellow-700"><?php echo $warning; ?></div>
                <div class="text-sm text-yellow-500">警告</div>
            </div>
            <!-- Info -->
            <div class="bg-white rounded-lg shadow-sm border border-blue-200 p-4">
                <div class="text-2xl font-bold text-blue-600"><?php echo $info; ?></div>
                <div class="text-sm text-blue-500">提示</div>
            </div>
        </div>
        <?php if ($scannedAt !== ''): ?>
        <div class="mt-3 text-sm text-gray-500">
            最近扫描时间：<?php echo e($scannedAt); ?>
            &nbsp;·&nbsp;
            <a href="/admin/seo/audit" class="text-primary-600 hover:underline">查看详细报告 →</a>
        </div>
        <?php else: ?>
        <div class="mt-3 text-sm text-gray-500">
            尚未进行扫描。点击上方「重新扫描」按钮开始检查。
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ Internal Links Management Card ════════════════════════════════ -->
<div class="panel mb-6">
    <div class="panel-header flex items-center justify-between">
        <h2>自动内链管理</h2>
        <a href="/admin/seo/internal-links" class="btn btn-sm btn-outline-primary">
            管理内链规则
        </a>
    </div>
    <div class="panel-body">
        <p class="text-sm text-gray-600">
            自动内链系统会在博客、案例、页面正文内容中自动插入站内链接，提升 SEO 内链结构。
        </p>
        <div class="mt-3 flex items-center gap-4 text-sm">
            <span class="text-gray-500">规则数量：</span>
            <span class="font-medium text-gray-800">
                <?php 
                $linksFile = DATA_PATH . '/internal_links.json';
                $linksCount = 0;
                if (file_exists($linksFile)) {
                    $raw = @file_get_contents($linksFile);
                    if ($raw !== false) {
                        $links = @json_decode($raw, true);
                        if (is_array($links)) {
                            $linksCount = count($links);
                        }
                    }
                }
                echo $linksCount;
                ?>
            </span>
            <span class="text-gray-400">|</span>
            <span class="text-gray-500">每篇最多插入 5 个链接</span>
        </div>
    </div>
</div>

<!-- ══ 404 Monitor Summary Card ════════════════════════════════════════ -->
<div class="panel mb-6">
    <div class="panel-header flex items-center justify-between">
        <h2>404 监控</h2>
        <a href="/admin/404monitor" class="btn btn-sm btn-outline-primary">
            查看完整 404 监控
        </a>
    </div>
    <div class="panel-body">
        <div class="grid grid-cols-3 gap-4 mb-4">
            <!-- Total -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-2xl font-bold text-gray-900"><?php echo $fourOhFourStats['total']; ?></div>
                <div class="text-sm text-gray-500">总 404 数</div>
            </div>
            <!-- Active -->
            <div class="bg-white rounded-lg shadow-sm border border-red-200 p-4">
                <div class="text-2xl font-bold text-red-700"><?php echo $fourOhFourStats['active']; ?></div>
                <div class="text-sm text-red-500">待处理</div>
            </div>
            <!-- Ignored -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-2xl font-bold text-gray-500"><?php echo $fourOhFourStats['ignored']; ?></div>
                <div class="text-sm text-gray-500">已忽略</div>
            </div>
        </div>

        <?php if (!empty($fourOhFourTopEntries)): ?>
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>访问次数</th>
                        <th>来源</th>
                        <th>最后访问</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fourOhFourTopEntries as $entry): ?>
                    <tr>
                        <td><code class="text-sm"><?php echo e($entry['url'] ?? ''); ?></code></td>
                        <td class="text-center"><?php echo (int) ($entry['hit_count'] ?? $entry['count'] ?? 0); ?></td>
                        <td>
                            <?php if (($entry['last_referrer'] ?? '') !== ''): ?>
                            <a href="<?php echo e($entry['last_referrer'] ?? ''); ?>" target="_blank" rel="noopener noreferrer" class="text-sm text-primary-600 hover:underline" title="<?php echo e($entry['last_referrer'] ?? ''); ?>">
                                <?php echo e(substr($entry['last_referrer'] ?? '', 0, 60)); ?>
                            </a>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm text-gray-500"><?php echo e($entry['last_seen'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-sm text-gray-500">
            暂无 404 记录。
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>SEO 总控</h2></div>

    <form method="post" action="/admin/seo" class="admin-form">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-section">
            <h3 class="form-section-title">品牌与默认 Meta</h3>
            <div class="form-group">
                <label>品牌名称 (site_name)</label>
                <input type="text" name="site_name" value="<?php echo htmlspecialchars(\App\Core\Config::get('site_name', ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="120">
            </div>
            <div class="form-group">
                <label>默认 Meta Title</label>
                <input type="text" name="default_meta_title" value="<?php echo htmlspecialchars(\App\Core\Config::get('default_meta_title', ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="90">
            </div>
            <div class="form-group">
                <label>默认 Meta Description</label>
                <textarea name="default_meta_description" rows="3" maxlength="200"><?php echo htmlspecialchars(\App\Core\Config::get('default_meta_description', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label>默认 OG 图片 URL</label>
                <input type="text" name="default_og_image" value="<?php echo htmlspecialchars(\App\Core\Config::get('default_og_image', ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="/uploads/og-default.jpg">
            </div>
            <div class="form-group">
                <label>Favicon URL</label>
                <input type="text" name="favicon" value="<?php echo htmlspecialchars(\App\Core\Config::get('favicon', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">索引与 Sitemap</h3>
            <div class="admin-check-group">
                <input type="hidden" name="robots_block_all" value="0">
                <label class="admin-check-row">
                    <input type="checkbox" name="robots_block_all" value="1" <?php echo \App\Core\Config::get('robots_block_all', false) ? 'checked' : ''; ?>>
                    <span>全站 robots 禁止抓取 (Disallow: /)</span>
                </label>
                <input type="hidden" name="sitemap_enabled" value="0">
                <label class="admin-check-row">
                    <input type="checkbox" name="sitemap_enabled" value="1" <?php echo \App\Core\Config::get('sitemap_enabled', true) ? 'checked' : ''; ?>>
                    <span>生成 sitemap index、products/blog/pages/image 子 sitemap，并刷新缓存。</span>
                </label>
            </div>
            <div class="form-group">
                <label>robots.txt 追加规则（纯文本）</label>
                <textarea name="robots_txt_extra" rows="5" maxlength="4000" placeholder="例如：Disallow: /tmp/"><?php echo htmlspecialchars(\App\Core\Config::get('robots_txt_extra', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>保存后追加到固定 robots 规则之后、Sitemap 链接之前；HTML/PHP 标签会被清除。</small>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">统计与广告代码</h3>
            <div class="form-group">
                <label>Google Analytics (测量 ID, 如 G-XXXX)</label>
                <input type="text" name="google_analytics_id" value="<?php echo htmlspecialchars(\App\Core\Config::get('google_analytics_id', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>GTM 容器 ID (如 GTM-XXXX)</label>
                <input type="text" name="gtm_container_id" value="<?php echo htmlspecialchars(\App\Core\Config::get('gtm_container_id', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>Google Ads / 其他 head 片段 (HTML)</label>
                <textarea name="google_ads_head" rows="4" class="code-editor"><?php echo htmlspecialchars(\App\Core\Config::get('google_ads_head', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label>WhatsApp 网页插件 / 聊天脚本 (HTML)</label>
                <textarea name="whatsapp_widget_script" rows="4" class="code-editor"><?php echo htmlspecialchars(\App\Core\Config::get('whatsapp_widget_script', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">结构化数据 (Organization JSON-LD)</h3>
            <div class="form-group">
                <textarea name="schema_organization_json" rows="8" class="code-editor" placeholder='{"@context":"https://schema.org","@type":"Organization",...}'><?php echo htmlspecialchars(\App\Core\Config::get('schema_organization_json', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>输出到前台时包裹为 &lt;script type="application/ld+json"&gt;</small>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">安全设置</h3>
            <div class="form-group">
                <label>Inquiry IP Hash Salt</label>
                <input type="text" name="inquiry_ip_salt" value="<?php echo htmlspecialchars(\App\Core\Config::get('inquiry_ip_salt', ''), ENT_QUOTES, 'UTF-8'); ?>">
                <small>用于对询盘访客 IP 进行哈希处理，防垃圾询盘和频率限制使用。上线后不要随意修改，否则历史 ip_hash 将无法与新记录对应。<strong>不要公开此值</strong>。</small>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">自定义 head / body 注入</h3>
            <div class="form-group">
                <label>&lt;head&gt;</label>
                <textarea name="head_scripts" rows="6" class="code-editor"><?php echo htmlspecialchars(\App\Core\Config::get('head_scripts', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label>&lt;body&gt; 底部</label>
                <textarea name="body_scripts" rows="6" class="code-editor"><?php echo htmlspecialchars(\App\Core\Config::get('body_scripts', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存全部</button>
            <button type="submit" name="regenerate_sitemap" value="1" class="btn">保存并重建 Sitemap</button>
        </div>
    </form>
</div>
