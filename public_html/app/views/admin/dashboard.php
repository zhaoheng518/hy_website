<?php $pageTitle = '仪表盘'; $activeMenu = 'dashboard'; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">&#9993;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['total_inquiries']; ?></span>
            <span class="stat-label">询盘总数</span>
        </div>
    </div>
    <div class="stat-card stat-card-accent">
        <div class="stat-icon">&#9733;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['unread_inquiries']; ?></span>
            <span class="stat-label">未读询盘</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128230;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['total_products']; ?></span>
            <span class="stat-label">产品数量</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128196;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['total_cases']; ?></span>
            <span class="stat-label">案例数量</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128240;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['total_posts']; ?></span>
            <span class="stat-label">博客文章</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#9881;</div>
        <div class="stat-info">
            <span class="stat-value"><?php echo (int) $stats['active_sections']; ?></span>
            <span class="stat-label">启用版块</span>
        </div>
    </div>
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
