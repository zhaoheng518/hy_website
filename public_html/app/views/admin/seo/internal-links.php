<?php $pageTitle = '自动内链管理'; $activeMenu = 'seo'; ?>

<div class="max-w-6xl mx-auto px-4 py-6">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-2 mb-6">
        <?php foreach ($breadcrumbs as $crumb): ?>
            <?php if (isset($crumb['url'])): ?>
                <a href="<?php echo htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-600 hover:text-blue-700">
                    <?php echo htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <span class="text-gray-400">/</span>
            <?php else: ?>
                <span class="text-gray-600"><?php echo htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success !== ''): ?>
        <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">
            <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- Add New Rule Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">新增内链规则</h3>
        <form method="post" action="/admin/seo/internal-links" class="space-y-4">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="add">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">关键词 <span class="text-red-500">*</span></label>
                    <input type="text" name="keyword" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="如：PTFE wire">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">目标路径 <span class="text-red-500">*</span></label>
                    <input type="text" name="url" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="如：/en/product/ptfe-wire">
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="case_sensitive" id="case_sensitive" value="1" 
                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="case_sensitive" class="text-sm text-gray-700">区分大小写</label>
            </div>

            <button type="submit" 
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                添加规则
            </button>
        </form>
    </div>

    <!-- Rules List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 py-3 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">内链规则列表</h3>
            <p class="text-sm text-gray-500 mt-1">共 <?php echo count($links); ?> 条规则</p>
        </div>

        <?php if (empty($links)): ?>
            <div class="px-4 py-12 text-center text-gray-500">
                <p>暂无内链规则</p>
                <p class="text-sm mt-1">点击上方"添加规则"创建第一条内链</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">关键词</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">目标路径</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">区分大小写</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">状态</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-gray-600">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($links as $index => $link): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="text-sm text-gray-800">
                                        <?php echo htmlspecialchars($link['keyword'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>" 
                                       target="_blank" class="text-sm text-blue-600 hover:text-blue-700">
                                        <?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($link['case_sensitive']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">是</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">否</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($link['enabled']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">启用</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <!-- Toggle Status -->
                                    <form method="post" action="/admin/seo/internal-links" class="inline">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="_action" value="toggle">
                                        <input type="hidden" name="index" value="<?php echo (int) $index; ?>">
                                        <button type="submit" 
                                            class="px-3 py-1 text-xs font-medium rounded-lg transition-colors"
                                            style="<?php echo $link['enabled'] ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200'; ?>">
                                            <?php echo $link['enabled'] ? '禁用' : '启用'; ?>
                                        </button>
                                    </form>

                                    <!-- Delete -->
                                    <form method="post" action="/admin/seo/internal-links" class="inline"
                                          onsubmit="return confirm('确定要删除这条规则吗？');">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="index" value="<?php echo (int) $index; ?>">
                                        <button type="submit" 
                                            class="px-3 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-lg hover:bg-red-200 transition-colors">
                                            删除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Instructions -->
    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
        <h4 class="text-sm font-semibold text-gray-700 mb-2">使用说明</h4>
        <ul class="text-sm text-gray-600 space-y-1">
            <li>- 关键词：在正文内容中匹配的文本，匹配到后会自动替换为链接</li>
            <li>- 目标路径：必须以 / 开头的站内路径，如 /en/product/slug</li>
            <li>- 区分大小写：勾选后区分大小写匹配（如 "PTFE" 和 "ptfe" 视为不同）</li>
            <li>- 每篇正文最多插入 5 个自动内链，每个关键词/URL 最多使用 1 次</li>
            <li>- 链接会跳过已有 &lt;a&gt; 标签、标题标签及代码块</li>
        </ul>
    </div>
</div>