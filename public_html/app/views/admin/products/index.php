<?php
/**
 * 产品列表页 - 使用 Tailwind CSS 现代化 Data Table
 */

$langLabels = [
    'en' => 'English',
    'cn' => '中文',
    'es' => 'Español',
];
?>

<!-- 页面标题区域 -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">产品列表</h2>
            <p class="text-sm text-gray-500 mt-1">共 <?php echo $totalCount; ?> 个产品</p>
        </div>
        <a href="/admin/products/create?lang=<?php echo $editLang; ?>"
           class="inline-flex items-center px-4 py-2.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors shadow-sm">
            <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            添加产品
        </a>
    </div>
</div>

<!-- 搜索和语言切换区域 -->
<div class="bg-white rounded-lg shadow-sm p-4 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <!-- 搜索框 -->
        <form method="GET" action="/admin/products" class="flex-1 max-w-md">
            <input type="hidden" name="lang" value="<?php echo View::e($editLang); ?>">
            <div class="relative">
                <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text"
                       name="search"
                       value="<?php echo View::e($search); ?>"
                       placeholder="搜索产品名称或 Slug..."
                       class="block w-full ps-10 pe-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-primary-500 focus:border-primary-500 focus:outline-none transition-colors">
            </div>
        </form>

        <!-- 语言切换 -->
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">编辑语言：</span>
            <div class="flex rounded-lg overflow-hidden border border-gray-300">
                <?php foreach ($supportedLangs as $l): ?>
                    <a href="/admin/products?lang=<?php echo $l; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                       class="px-4 py-2 text-sm font-medium transition-colors <?php echo $editLang === $l ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                        <?php echo $langLabels[$l] ?? strtoupper($l); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- 数据表格卡片 -->
<div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <?php if (empty($products)): ?>
        <!-- 空状态 -->
        <div class="py-16 px-6 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">暂无产品</h3>
            <p class="mt-2 text-sm text-gray-500">点击"添加产品"创建您的第一个产品。</p>
            <a href="/admin/products/create?lang=<?php echo $editLang; ?>"
               class="mt-4 inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                添加产品
            </a>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            ID / 图片
                        </th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            产品名称
                        </th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            分类
                        </th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            状态
                        </th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            排序
                        </th>
                        <th scope="col" class="px-6 py-3 text-end text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            操作
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $mainImage = '';
                        if (!empty($product['images']) && is_array($product['images'])) {
                            foreach ($product['images'] as $img) {
                                if (!empty($img['is_main']) && !empty($img['url'])) {
                                    $mainImage = $img['url'];
                                    break;
                                }
                            }
                            if (empty($mainImage) && !empty($product['images'][0]['url'])) {
                                $mainImage = $product['images'][0]['url'];
                            }
                        }
                        $catKey = (string) ($product['category_id'] ?? '');
                        $categoryName = $catKey !== '' ? ($categoryMap[$catKey] ?? '-') : '-';
                        $pubStatus = \App\Core\ProductPublishState::normalize($product['status'] ?? \App\Core\ProductPublishState::STATUS_PUBLISHED);
                        $statusBadge = $pubStatus === \App\Core\ProductPublishState::STATUS_DRAFT
                            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">草稿</span>'
                            : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">已发布</span>';
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <!-- ID / 图片 -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php if (!empty($mainImage)): ?>
                                        <div class="flex-shrink-0 h-10 w-10 rounded-lg overflow-hidden bg-gray-100">
                                            <img src="<?php echo View::e($mainImage); ?>"
                                                 alt=""
                                                 class="h-full w-full object-cover"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="h-full w-full items-center justify-center bg-gray-100 text-gray-400" style="display:none;">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                    <span class="ms-3 text-sm font-medium text-gray-500">#<?php echo View::e((string) ($product['id'] ?? '')); ?></span>
                                </div>
                            </td>

                            <!-- 产品名称 -->
                            <td class="px-6 py-4">
                                <div class="max-w-xs">
                                    <a href="/admin/products/edit/<?php echo View::e((string) ($product['slug'] ?? '')); ?>?lang=<?php echo $editLang; ?>"
                                       class="text-sm font-semibold text-gray-900 hover:text-primary-600 transition-colors">
                                        <?php echo View::e($product['name'] ?? '未命名产品'); ?>
                                    </a>
                                    <p class="text-xs text-gray-500 mt-0.5 truncate">
                                        <code class="bg-gray-100 px-1.5 py-0.5 rounded"><?php echo View::e((string) ($product['slug'] ?? '')); ?></code>
                                    </p>
                                </div>
                            </td>

                            <!-- 分类 -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-700"><?php echo View::e($categoryName); ?></span>
                            </td>

                            <!-- 状态 -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo $statusBadge; ?>
                            </td>

                            <!-- 排序 -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-500"><?php echo View::e((string) ($product['sort_order'] ?? '0')); ?></span>
                            </td>

                            <!-- 操作 -->
                            <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="/admin/products/edit/<?php echo View::e((string) ($product['slug'] ?? '')); ?>?lang=<?php echo $editLang; ?>"
                                       class="p-2 text-gray-500 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                                       title="编辑">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <form method="POST"
                                          action="/admin/products/delete/<?php echo View::e((string) ($product['slug'] ?? '')); ?>?lang=<?php echo $editLang; ?>"
                                          class="inline"
                                          onsubmit="return confirm('确定要删除此产品吗？此操作无法撤销。');">
                                        <input type="hidden" name="_csrf" value="<?php echo View::e(\App\Core\Auth::generateCsrfToken()); ?>">
                                        <button type="submit"
                                                class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="删除">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
            <div class="bg-white px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        显示 <?php echo (($page - 1) * $perPage) + 1; ?> - <?php echo min($page * $perPage, $totalCount); ?> 条，共 <?php echo $totalCount; ?> 条
                    </div>
                    <div class="flex items-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="/admin/products?page=<?php echo $page - 1; ?>&lang=<?php echo $editLang; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                               class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                上一页
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <a href="/admin/products?page=<?php echo $i; ?>&lang=<?php echo $editLang; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                               class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'bg-primary-600 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-lg transition-colors">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="/admin/products?page=<?php echo $page + 1; ?>&lang=<?php echo $editLang; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                               class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                下一页
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
