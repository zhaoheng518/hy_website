<?php $pageTitle = '301 重定向'; $activeMenu = 'redirects'; ?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">新增 301 规则</h2>
        <form method="post" action="/admin/redirects" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="add">
            <div class="md:col-span-2">
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">旧路径（From）</label>
                <input type="text" id="from" name="from" placeholder="/old-product" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div class="md:col-span-2">
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">新路径（To）</label>
                <input type="text" id="to" name="to" placeholder="/en/products/new-product 或 https://example.com/..." required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div class="md:col-span-1">
                <button type="submit" class="w-full rounded-lg bg-primary-600 text-white px-3 py-2 text-sm font-medium hover:bg-primary-700">新增</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">已有规则</h3>
        </div>
        <?php if (empty($redirects)): ?>
            <div class="px-5 py-8 text-sm text-gray-500">暂无 301 规则</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase">旧路径</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600 uppercase">新路径</th>
                            <th class="px-4 py-3 text-end text-xs font-semibold text-gray-600 uppercase">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach ($redirects as $row): ?>
                        <tr>
                            <td class="px-4 py-3 align-top">
                                <input type="text" form="form-update-<?php echo md5($row['from'] ?? ''); ?>" name="from" value="<?php echo htmlspecialchars($row['from'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                            </td>
                            <td class="px-4 py-3 align-top">
                                <input type="text" form="form-update-<?php echo md5($row['from'] ?? ''); ?>" name="to" value="<?php echo htmlspecialchars($row['to'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                            </td>
                            <td class="px-4 py-3 align-top text-end">
                                <div class="inline-flex items-center gap-2">
                                    <form id="form-update-<?php echo md5($row['from'] ?? ''); ?>" method="post" action="/admin/redirects" class="inline">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="old_from" value="<?php echo htmlspecialchars($row['from'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="rounded-md bg-primary-600 text-white px-3 py-1.5 text-sm hover:bg-primary-700">保存</button>
                                    </form>
                                    <form method="post" action="/admin/redirects" class="inline" onsubmit="return confirm('确认删除该重定向？');">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="from" value="<?php echo htmlspecialchars($row['from'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="rounded-md bg-red-600 text-white px-3 py-1.5 text-sm hover:bg-red-700">删除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
