<form method="POST" action="/admin/home/save?lang=<?php echo $editLang; ?>" class="admin-form"
      onsubmit="return prepareFactorySubmit();">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="section" value="factory">
    <input type="hidden" name="images_json" id="images-json" value="">
    <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="panel-info-box" style="margin-bottom:16px;">
        <p>此版块仅控制首页"工厂实力"区块的展示内容。如需编辑完整的工厂页面，请前往 <a href="/admin/pages/edit/factory?lang=<?php echo $editLang; ?>">页面管理 → Factory</a>。</p>
    </div>

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
        <label for="content">简介文字</label>
        <textarea id="content" name="content" rows="4" maxlength="500"><?php echo htmlspecialchars($sectionData['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>首页展示的简短介绍，建议不超过 200 字</small>
    </div>

    <div class="form-group">
        <label>展示图片 <small>(首页轮播/网格展示)</small></label>
        <div class="image-list" id="factory-images">
            <?php foreach ($sectionData['images'] ?? [] as $i => $img): ?>
            <div class="image-list-item">
                <div class="upload-field" data-accept="image/jpeg,image/png,image/webp">
                    <div class="upload-field-row">
                        <input type="text" class="factory-img-url upload-url-input" value="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="button" class="upload-btn">选择文件</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.image-list-item').remove();">移除</button>
                    </div>
                    <div class="upload-preview"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm" onclick="addFactoryImage();">+ 添加图片</button>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存工厂区块</button>
    </div>
</form>

<script>
function addFactoryImage() {
    var list = document.getElementById('factory-images');
    var html = '<div class="image-list-item"><div class="upload-field" data-accept="image/jpeg,image/png,image/webp">' +
        '<div class="upload-field-row">' +
        '<input type="text" class="factory-img-url upload-url-input" value="">' +
        '<button type="button" class="upload-btn">选择文件</button>' +
        '<button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'.image-list-item\').remove();">移除</button>' +
        '</div><div class="upload-preview"></div></div></div>';
    list.insertAdjacentHTML('beforeend', html);
    var newItem = list.lastElementChild;
    initUploadField(newItem.querySelector('.upload-field'));
}

function prepareFactorySubmit() {
    var images = [];
    document.querySelectorAll('.factory-img-url').forEach(function(el) {
        if (el.value.trim()) images.push(el.value.trim());
    });
    document.getElementById('images-json').value = JSON.stringify(images);
    return true;
}
</script>
