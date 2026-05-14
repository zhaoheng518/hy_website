<?php $pageTitle = '下载统计'; $activeMenu = 'downloads'; ?>

<div class="panel">
    <div class="panel-header">
        <h2>Datasheet 下载统计</h2>
        <span style="color:#888;font-size:14px;">共 <?php echo (int) $total; ?> 次下载记录</span>
    </div>

    <?php if ($total === 0): ?>
    <div style="padding:40px;text-align:center;color:#999;">
        <p>暂无下载记录，确认文件已上传并已有用户通过追踪链接下载文件。</p>
    </div>
    <?php else: ?>

    <!-- ── By Product ─────────────────────────────────────────────────────── -->
    <?php if (!empty($byProduct)): ?>
    <div style="padding:20px 24px 0;">
        <h3 style="margin:0 0 12px;font-size:15px;font-weight:600;">按产品统计（Top 30）</h3>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;">
            <?php foreach ($byProduct as $row): ?>
            <?php
            $slug  = htmlspecialchars((string) ($row['product_slug'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $count = (int) ($row['total'] ?? 0);
            ?>
            <div style="background:#f0f4ff;border-radius:6px;padding:10px 16px;min-width:160px;">
                <div style="font-size:13px;color:#555;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;" title="<?php echo $slug; ?>"><?php echo $slug; ?></div>
                <div style="font-size:22px;font-weight:700;color:#1a56db;line-height:1.3;"><?php echo $count; ?></div>
                <div style="font-size:11px;color:#888;">次下载</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Recent Downloads ───────────────────────────────────────────────── -->
    <div style="padding:0 24px 24px;">
        <h3 style="margin:0 0 12px;font-size:15px;font-weight:600;">最近下载记录</h3>
        <div style="overflow-x:auto;">
            <table class="admin-table" style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f8f9fa;text-align:left;">
                        <th style="padding:10px 12px;border-bottom:2px solid #e0e0e0;">产品 Slug</th>
                        <th style="padding:10px 12px;border-bottom:2px solid #e0e0e0;">文件名</th>
                        <th style="padding:10px 12px;border-bottom:2px solid #e0e0e0;">语言</th>
                        <th style="padding:10px 12px;border-bottom:2px solid #e0e0e0;">IP（脱敏）</th>
                        <th style="padding:10px 12px;border-bottom:2px solid #e0e0e0;">时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                    <?php
                    $slug     = htmlspecialchars((string) ($row['product_slug'] ?? '—'), ENT_QUOTES, 'UTF-8');
                    $fname    = htmlspecialchars((string) ($row['file_name']    ?? '—'), ENT_QUOTES, 'UTF-8');
                    $lang     = htmlspecialchars(strtoupper((string) ($row['lang'] ?? '')), ENT_QUOTES, 'UTF-8');
                    $ip       = (string) ($row['ip'] ?? '');
                    // Mask last octet for IPv4, last group for IPv6
                    $ipMasked = preg_replace('/(\d+)$/', '***', $ip) ?: '***';
                    $time     = htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr style="border-bottom:1px solid #f0f0f0;">
                        <td style="padding:9px 12px;"><?php echo $slug; ?></td>
                        <td style="padding:9px 12px;"><?php echo $fname; ?></td>
                        <td style="padding:9px 12px;"><?php echo $lang; ?></td>
                        <td style="padding:9px 12px;font-family:monospace;color:#888;"><?php echo htmlspecialchars($ipMasked, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding:9px 12px;white-space:nowrap;color:#666;"><?php echo $time; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent)): ?>
                    <tr><td colspan="5" style="padding:20px;text-align:center;color:#999;">暂无记录</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
        <div style="margin-top:16px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="/admin/downloads?page=<?php echo $p; ?>"
               style="padding:5px 10px;border-radius:4px;border:1px solid <?php echo $p === $page ? '#1a56db' : '#ddd'; ?>;background:<?php echo $p === $page ? '#1a56db' : '#fff'; ?>;color:<?php echo $p === $page ? '#fff' : '#333'; ?>;text-decoration:none;font-size:13px;">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>
