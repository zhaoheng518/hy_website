<?php
$post = $post ?? [];
$tagsStr = isset($post['tags']) && is_array($post['tags']) ? implode(', ', $post['tags']) : '';
$relStr = isset($post['related_products']) && is_array($post['related_products']) ? implode(' ', $post['related_products']) : '';
$faqsJson = json_encode($post['faqs'] ?? [], JSON_UNESCAPED_UNICODE);
?>
<form method="post" action="<?php echo $isEdit
    ? '/admin/blog/edit/' . rawurlencode($post['slug'] ?? '')
    : '/admin/blog/create'; ?>?lang=<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>"
      class="max-w-5xl space-y-6">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">标题 *</label>
            <input type="text" name="title" required maxlength="200" value="<?php echo htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-gray-400">(留空自动生成)</span></label>
            <input type="text" name="slug" value="<?php echo htmlspecialchars($post['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2" pattern="[a-z0-9\-]*">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">状态</label>
            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="draft" <?php echo (($post['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>草稿</option>
                <option value="published" <?php echo (($post['status'] ?? 'published') === 'published') ? 'selected' : ''; ?>>已发布</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">发布时间</label>
            <input type="datetime-local" name="published_at"
                   value="<?php echo htmlspecialchars(str_replace(' ', 'T', substr($post['published_at'] ?? ($post['date'] ?? date('Y-m-d')) . ' 12:00:00', 0, 16)), ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">摘要</label>
        <textarea name="desc" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2"><?php echo htmlspecialchars($post['desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">正文 (HTML)</label>
        <textarea name="content" rows="14" class="w-full border border-gray-300 rounded-lg px-3 py-2 font-mono text-sm"><?php echo htmlspecialchars($post['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">特色图 URL</label>
            <input type="text" name="image" value="<?php echo htmlspecialchars($post['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">分类</label>
            <select name="category_slug" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">—</option>
                <?php foreach ($categories ?? [] as $c): ?>
                <option value="<?php echo htmlspecialchars($c['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo (($post['category_slug'] ?? '') === ($c['slug'] ?? '')) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['name'] ?? $c['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tags (逗号分隔)</label>
            <input type="text" name="tags" value="<?php echo htmlspecialchars($tagsStr, ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">相关产品 slug（空格/逗号）</label>
            <input type="text" name="related_products" value="<?php echo htmlspecialchars($relStr, ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">SEO Title</label>
            <input type="text" name="seo_title" value="<?php echo htmlspecialchars($post['seo_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">SEO Description</label>
            <input type="text" name="seo_desc" value="<?php echo htmlspecialchars($post['seo_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">FAQ (JSON)</label>
        <textarea name="faqs_json" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 font-mono text-sm"><?php echo htmlspecialchars($faqsJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <p class="text-xs text-gray-500 mt-1">例: [{"question":"Q","answer":"A"}]</p>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">保存</button>
        <a href="/admin/blog?lang=<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">返回</a>
    </div>
</form>
