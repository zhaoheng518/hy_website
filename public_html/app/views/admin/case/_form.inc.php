<?php
$c = $caseItem ?? [];
$relStr = isset($c['related_products']) && is_array($c['related_products']) ? implode(' ', $c['related_products']) : '';
$galleryJson = json_encode($c['gallery'] ?? [], JSON_UNESCAPED_UNICODE);
?>
<form method="post" action="<?php echo $isEdit
    ? '/admin/case/edit/' . rawurlencode($c['slug'] ?? '')
    : '/admin/case/create'; ?>?lang=<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>" class="max-w-5xl space-y-4">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">标题 *</label>
            <input type="text" name="title" required class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($c['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
            <input type="text" name="slug" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($c['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" pattern="[a-z0-9\-]*">
        </div>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">状态</label>
            <select name="status" class="w-full border rounded-lg px-3 py-2">
                <option value="published" <?php echo (($c['status'] ?? 'published') === 'published') ? 'selected' : ''; ?>>已发布</option>
                <option value="draft" <?php echo (($c['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>草稿</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">发布时间</label>
            <input type="datetime-local" name="published_at" class="w-full border rounded-lg px-3 py-2"
                   value="<?php echo htmlspecialchars(str_replace(' ', 'T', substr($c['published_at'] ?? ($c['date'] ?? date('Y-m-d')) . ' 12:00:00', 0, 16)), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">摘要</label>
        <textarea name="desc" rows="3" class="w-full border rounded-lg px-3 py-2"><?php echo htmlspecialchars($c['desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">详情 HTML</label>
        <textarea name="content" rows="10" class="w-full border rounded-lg px-3 py-2 font-mono text-sm"><?php echo htmlspecialchars($c['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">封面图 URL</label>
            <input type="text" name="image" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($c['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">客户行业</label>
            <input type="text" name="client_industry" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($c['client_industry'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">使用产品（文本）</label>
        <input type="text" name="products_used" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($c['products_used'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">图集 JSON（URL 字符串数组或 {"url":""} ）</label>
        <textarea name="gallery_json" rows="4" class="w-full border rounded-lg px-3 py-2 font-mono text-sm"><?php echo htmlspecialchars($galleryJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">SEO Title</label>
            <input type="text" name="seo_title" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($c['seo_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">SEO Description</label>
            <input type="text" name="seo_desc" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($c['seo_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">相关产品 slug</label>
        <input type="text" name="related_products" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($relStr, ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="flex gap-3">
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg">保存</button>
        <a href="/admin/case?lang=<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>" class="px-4 py-2 border rounded-lg">返回</a>
    </div>
</form>
