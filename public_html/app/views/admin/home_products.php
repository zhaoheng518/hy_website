<form method="POST" action="/admin/home/save?lang=<?php echo $editLang; ?>" class="admin-form">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="section" value="products">
    <input type="hidden" name="selected_categories" id="selected-categories" value="<?php echo htmlspecialchars(json_encode($sectionData['selected_categories'] ?? []), ENT_QUOTES, 'UTF-8'); ?>">

    <div class="form-row">
        <div class="form-group form-group-2">
            <label for="title">区块标题</label>
            <input type="text" id="title" name="title"
                   value="<?php echo htmlspecialchars($sectionData['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group form-group-2">
            <label for="subtitle">区块副标题</label>
            <input type="text" id="subtitle" name="subtitle"
                   value="<?php echo htmlspecialchars($sectionData['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="form-group">
        <label>首页展示分类 <small>(勾选要在首页产品版块中展示的分类)</small></label>
        <div class="category-multi-select" id="category-multi-select">
            <?php
            $allCategories = \App\Core\JsonStore::langData($editLang, 'categories')->read();
            $selectedSlugs = $sectionData['selected_categories'] ?? [];
            if (!empty($allCategories)):
                foreach ($allCategories as $cat):
                    $slug = $cat['slug'] ?? '';
                    $isSelected = in_array($slug, $selectedSlugs);
            ?>
            <label class="category-check-item<?php echo $isSelected ? ' checked' : ''; ?>">
                <input type="checkbox" class="cat-check" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>"
                       <?php echo $isSelected ? 'checked' : ''; ?>>
                <?php if (!empty($cat['image'])): ?>
                <img src="<?php echo htmlspecialchars($cat['image'], ENT_QUOTES, 'UTF-8'); ?>" class="cat-thumb" alt="">
                <?php endif; ?>
                <span class="cat-name"><?php echo htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
            <?php endforeach; else: ?>
            <div class="panel-empty">暂无分类，请先前往 <a href="/admin/categories?lang=<?php echo $editLang; ?>">产品分类</a> 创建。</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存产品版块</button>
    </div>
</form>

<style>
.category-multi-select { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; max-height: 320px; overflow-y: auto; padding: 12px; border: 1px solid var(--c-border); border-radius: var(--radius); background: var(--c-white); }
.category-check-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid var(--c-border); border-radius: var(--radius); cursor: pointer; transition: all 0.15s; }
.category-check-item:hover { border-color: var(--c-primary); background: var(--c-primary-light); }
.category-check-item.checked { border-color: var(--c-primary); background: var(--c-primary-light); }
.category-check-item input { display: none; }
.cat-thumb { width: 28px; height: 28px; border-radius: 4px; object-fit: cover; }
.cat-name { font-size: 13px; font-weight: 500; }
</style>

<script>
document.querySelectorAll('.cat-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        this.closest('.category-check-item').classList.toggle('checked', this.checked);
    });
});

var form = document.querySelector('.admin-form');
if (form) {
    form.addEventListener('submit', function() {
        var selected = [];
        document.querySelectorAll('.cat-check:checked').forEach(function(cb) {
            selected.push(cb.value);
        });
        document.getElementById('selected-categories').value = JSON.stringify(selected);
    });
}
</script>
