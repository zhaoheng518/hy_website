<?php $pageTitle = '产品管理'; $activeMenu = 'products'; ?>

<div class="panel">
    <div class="panel-header">
        <h2>产品管理</h2>
        <div class="panel-actions">
            <div class="lang-tabs">
                <?php foreach ($supportedLangs as $l): ?>
                <a href="/admin/products?lang=<?php echo $l; ?>"
                   class="lang-tab<?php echo $editLang === $l ? ' active' : ''; ?>">
                    <?php echo strtoupper($l); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <a href="/admin/products/create?lang=<?php echo $editLang; ?>" class="btn btn-primary">+ 添加产品</a>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="panel-empty">暂无产品，点击"添加产品"创建第一个产品。</div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>图片</th>
                    <th>名称</th>
                    <th>别名</th>
                    <th>SEO标题</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" class="thumb-img">
                        <?php else: ?>
                            <div class="thumb-placeholder">无图片</div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><code><?php echo htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
                    <td><?php echo htmlspecialchars($product['seo_title'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="action-cell">
                        <a href="/admin/products/edit/<?php echo htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?lang=<?php echo $editLang; ?>"
                           class="btn btn-sm">编辑</a>
                        <form method="POST" action="/admin/products/delete/<?php echo htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?lang=<?php echo $editLang; ?>"
                              class="inline-form"
                              onsubmit="return confirm('确定要删除此产品吗？');">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
