<?php $pageTitle = '仪表盘'; $activeMenu = 'dashboard'; ?>
<div class="stats-grid">
    <a href="/admin/inquiries" class="stat-card block transition hover:shadow-lg hover:-translate-y-0.5">
        <div class="stat-icon">&#9993;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['total_inquiries']; ?></span>
            <span class="stat-label">询盘总数</span>
        </div>
    </a>
    <a href="/admin/inquiries?status=new" class="stat-card stat-card-accent block transition hover:shadow-lg hover:-translate-y-0.5">
        <div class="stat-icon">&#9733;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['unread_inquiries']; ?></span>
            <span class="stat-label">未读询盘</span>
        </div>
    </a>
    <a href="/admin/products?lang=<?php echo htmlspecialchars($defaultLang ?? 'en', ENT_QUOTES, 'UTF-8'); ?>" class="stat-card block transition hover:shadow-lg hover:-translate-y-0.5">
        <div class="stat-icon">&#128230;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['total_products']; ?></span>
            <span class="stat-label">产品总数(多语言)</span>
        </div>
    </a>
    <a href="/admin/case?lang=<?php echo htmlspecialchars($defaultLang ?? 'en', ENT_QUOTES, 'UTF-8'); ?>" class="stat-card block transition hover:shadow-lg hover:-translate-y-0.5">
        <div class="stat-icon">&#128196;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['total_cases']; ?></span>
            <span class="stat-label">案例数量</span>
        </div>
    </a>
    <a href="/admin/blog?lang=<?php echo htmlspecialchars($defaultLang ?? 'en', ENT_QUOTES, 'UTF-8'); ?>" class="stat-card block transition hover:shadow-lg hover:-translate-y-0.5">
        <div class="stat-icon">&#128240;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['total_posts']; ?></span>
            <span class="stat-label">博客文章</span>
        </div>
    </a>
    <a href="/admin/sections" class="stat-card block transition hover:shadow-lg hover:-translate-y-0.5">
        <div class="stat-icon">&#9881;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['active_sections']; ?></span>
            <span class="stat-label">启用版块</span>
        </div>
    </a>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>最新询盘</h2>
        <a href="/admin/inquiries" class="btn btn-sm">查看全部</a>
    </div>
    <?php if (empty($recentInquiries)): ?>
        <div class="panel-empty">暂无询盘</div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>公司</th>
                    <th>邮箱</th>
                    <th>日期</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentInquiries as $inquiry): ?>
                <tr>
                    <td><?php echo htmlspecialchars($inquiry['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($inquiry['company'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($inquiry['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php if (!empty($inquiry['read'])): ?>
                            <span class="badge badge-success">已读</span>
                        <?php else: ?>
                            <span class="badge badge-warning">未读</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/admin/inquiries/<?php echo htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm">查看</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="panel" style="margin-top:20px;">
    <div class="panel-header">
        <h2>最近发布博客 (<?php echo strtoupper($defaultLang ?? 'en'); ?>)</h2>
        <a href="/admin/blog?lang=<?php echo htmlspecialchars($defaultLang ?? 'en', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm">管理</a>
    </div>
    <?php if (empty($recentBlogs)): ?>
    <div class="panel-empty">暂无</div>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>标题</th><th>Slug</th><th>发布</th></tr></thead>
        <tbody>
            <?php foreach ($recentBlogs as $b): ?>
            <tr>
                <td><?php echo htmlspecialchars($b['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><code><?php echo htmlspecialchars($b['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
                <td><?php echo htmlspecialchars(substr($b['published_at'] ?? $b['date'] ?? '', 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="panel" style="margin-top:20px;">
    <div class="panel-header">
        <h2>热门 / 推荐产品</h2>
        <a href="/admin/products?lang=<?php echo htmlspecialchars($defaultLang ?? 'en', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm">产品管理</a>
    </div>
    <?php if (empty($hotProducts)): ?>
    <div class="panel-empty">暂无</div>
    <?php else: ?>
    <ul style="list-style:none;padding:0;margin:0;">
        <?php foreach ($hotProducts as $hp): ?>
        <li style="padding:8px 0;border-bottom:1px solid var(--c-border);">
            <?php echo htmlspecialchars($hp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($hp['is_hot'])): ?><span class="badge badge-warning">HOT</span><?php endif; ?>
            <?php if (!empty($hp['is_featured'])): ?><span class="badge badge-success">推荐</span><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
