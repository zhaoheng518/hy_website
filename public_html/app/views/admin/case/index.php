<?php $pageTitle = '案例管理'; $activeMenu = 'cases_admin'; $langQs = 'lang=' . htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h1 class="text-2xl font-bold text-gray-900">案例</h1>
    <a href="/admin/case/create?<?php echo $langQs; ?>" class="inline-flex px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">新建案例</a>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
        <div>
            <label class="block text-xs text-gray-500 mb-1">搜索</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">状态</label>
            <select name="status" class="border border-gray-300 rounded-lg px-2 py-2 text-sm">
                <option value="">全部</option>
                <option value="published" <?php echo ($status ?? '') === 'published' ? 'selected' : ''; ?>>已发布</option>
                <option value="draft" <?php echo ($status ?? '') === 'draft' ? 'selected' : ''; ?>>草稿</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">排序</label>
            <select name="sort" class="border border-gray-300 rounded-lg px-2 py-2 text-sm">
                <option value="desc" <?php echo ($sort ?? 'desc') === 'desc' ? 'selected' : ''; ?>>新→旧</option>
                <option value="asc" <?php echo ($sort ?? '') === 'asc' ? 'selected' : ''; ?>>旧→新</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">筛选</button>
    </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-start font-medium text-gray-600">标题</th>
                <th class="px-4 py-3 text-start">Slug</th>
                <th class="px-4 py-3 text-start">行业</th>
                <th class="px-4 py-3 text-start">状态</th>
                <th class="px-4 py-3 text-end">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($cases)): ?>
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">暂无案例</td></tr>
            <?php else: foreach ($cases as $c): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($c['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($c['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($c['client_industry'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="px-4 py-3">
                    <?php $st = $c['status'] ?? 'published'; ?>
                    <span class="text-xs px-2 py-0.5 rounded <?php echo $st === 'published' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'; ?>"><?php echo $st === 'published' ? '已发布' : '草稿'; ?></span>
                </td>
                <td class="px-4 py-3 text-end space-x-2">
                    <a href="/admin/case/edit/<?php echo rawurlencode($c['slug'] ?? ''); ?>?<?php echo $langQs; ?>" class="text-primary-600 hover:underline">编辑</a>
                    <form method="post" action="/admin/case/delete/<?php echo rawurlencode($c['slug'] ?? ''); ?>?<?php echo $langQs; ?>" class="inline" onsubmit="return confirm('删除？');">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="text-red-600 hover:underline">删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php if (($totalPages ?? 1) > 1): ?>
    <div class="px-4 py-3 border-t text-sm text-gray-600 flex justify-between">
        <span>共 <?php echo (int) ($total ?? 0); ?> 条</span>
        <div class="space-x-2">
            <?php if (($page ?? 1) > 1): ?><a class="text-primary-600" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">上一页</a><?php endif; ?>
            <?php if (($page ?? 1) < ($totalPages ?? 1)): ?><a class="text-primary-600" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">下一页</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
