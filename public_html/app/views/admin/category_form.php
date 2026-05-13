<?php $pageTitle = $isEdit ? '编辑分类' : '新增分类'; $activeMenu = 'categories'; ?>

<div class="panel">
    <div class="panel-header">
        <h2><?php echo $isEdit ? '编辑分类' : '新增分类'; ?> <span class="lang-badge"><?php echo strtoupper($editLang); ?></span></h2>
        <a href="/admin/categories?lang=<?php echo $editLang; ?>" class="btn btn-sm">&larr; 返回分类列表</a>
    </div>

    <form method="POST" action="<?php echo $isEdit ? '/admin/categories/edit/' . htmlspecialchars($category['slug'] ?? '', ENT_QUOTES, 'UTF-8') . '?lang=' . $editLang : '/admin/categories/create?lang=' . $editLang; ?>" class="admin-form">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-row">
            <div class="form-group form-group-2">
                <label for="name">分类名称 *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($category['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            </div>
            <div class="form-group form-group-1">
                <label for="slug">URL别名 <small>(留空自动生成)</small></label>
                <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($category['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="100" pattern="[a-z0-9\-]*">
            </div>
        </div>

        <div class="form-group">
            <label for="description">分类描述</label>
            <textarea id="description" name="description" rows="3" maxlength="500"><?php echo htmlspecialchars($category['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="form-group">
            <label>分类缩略图</label>
            <div class="upload-field" data-accept="image/jpeg,image/png,image/webp">
                <div class="upload-field-row">
                    <input type="text" id="image" name="image"
                           value="<?php echo htmlspecialchars($category['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           class="upload-url-input" placeholder="选择文件上传或手动输入URL">
                    <button type="button" class="upload-btn">选择文件</button>
                </div>
                <div class="upload-preview"></div>
            </div>
        </div>

        <div class="form-group">
            <label for="banner_image">分类 Banner 图 URL</label>
            <input type="text" id="banner_image" name="banner_image" value="<?php echo htmlspecialchars($category['banner_image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group">
            <label for="sort_order">排序 (数字越小越靠前)</label>
            <input type="number" id="sort_order" name="sort_order" value="<?php echo (int) ($category['sort_order'] ?? 0); ?>">
        </div>

        <div class="form-group">
            <label for="seo_title">分类 SEO 标题</label>
            <input type="text" id="seo_title" name="seo_title" value="<?php echo htmlspecialchars($category['seo_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="120">
        </div>
        <div class="form-group">
            <label for="seo_description">分类 SEO 描述</label>
            <textarea id="seo_description" name="seo_description" rows="2" maxlength="200"><?php echo htmlspecialchars($category['seo_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="form-group">
            <label for="featured_product_slugs">推荐产品 slug（空格/逗号）</label>
            <input type="text" id="featured_product_slugs" name="featured_product_slugs"
                   value="<?php echo htmlspecialchars(isset($category['featured_product_slugs']) && is_array($category['featured_product_slugs']) ? implode(' ', $category['featured_product_slugs']) : '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group">
            <label for="faqs_json">分类 FAQ (JSON)</label>
            <textarea id="faqs_json" name="faqs_json" rows="5" class="code-editor"><?php
            echo htmlspecialchars(json_encode($category['faqs'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
            ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $isEdit ? '更新分类' : '创建分类'; ?></button>
            <a href="/admin/categories?lang=<?php echo $editLang; ?>" class="btn">取消</a>
        </div>
    </form>
</div>
