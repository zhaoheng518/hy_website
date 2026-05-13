<form method="POST" action="/admin/home/save?lang=<?php echo $editLang; ?>" class="admin-form"
      onsubmit="return prepareAboutSubmit();">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="section" value="about">
    <input type="hidden" name="certifications_json" id="certs-json" value="">
    <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="panel-info-box" style="margin-bottom:16px;">
        <p>此版块仅控制首页"关于我们"区块的展示内容。如需编辑完整的关于页面，请前往 <a href="/admin/pages/edit/about?lang=<?php echo $editLang; ?>">页面管理 → About Us</a>。</p>
    </div>

    <div class="form-group">
        <label for="title">区块标题</label>
        <input type="text" id="title" name="title"
               value="<?php echo htmlspecialchars($sectionData['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="form-group">
        <label for="content">简介文字</label>
        <textarea id="content" name="content" rows="4" maxlength="500"><?php echo htmlspecialchars($sectionData['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>首页展示的简短介绍</small>
    </div>

    <div class="form-group">
        <label>公司图片</label>
        <div class="upload-field" data-accept="image/jpeg,image/png,image/webp">
            <div class="upload-field-row">
                <input type="text" id="about_image" name="about_image"
                       value="<?php echo htmlspecialchars($sectionData['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                       class="upload-url-input" placeholder="选择文件上传或手动输入URL">
                <button type="button" class="upload-btn">选择文件</button>
            </div>
            <div class="upload-preview"></div>
        </div>
    </div>

    <div class="form-group">
        <label>资质认证 <small>(首页展示)</small></label>
        <div class="sortable-list" id="certs-list">
            <?php foreach ($sectionData['certifications'] ?? [] as $i => $cert): ?>
            <div class="sortable-item">
                <div class="sortable-header">
                    <strong class="sortable-label"><?php echo htmlspecialchars($cert['name'] ?? '新认证', ENT_QUOTES, 'UTF-8'); ?></strong>
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.sortable-item').remove();">删除</button>
                </div>
                <div class="sortable-body">
                    <input type="hidden" class="cert-id" value="<?php echo htmlspecialchars($cert['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-row">
                        <div class="form-group form-group-2">
                            <label>认证名称</label>
                            <input type="text" class="cert-name" value="<?php echo htmlspecialchars($cert['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                        </div>
                        <div class="form-group form-group-2">
                            <label>Logo图片</label>
                            <div class="upload-field" data-accept="image/jpeg,image/png,image/webp">
                                <div class="upload-field-row">
                                    <input type="text" class="cert-image upload-url-input" value="<?php echo htmlspecialchars($cert['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="button" class="upload-btn">选择文件</button>
                                </div>
                                <div class="upload-preview"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm" onclick="addCertItem();" style="margin-top:10px;">+ 添加认证</button>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存关于区块</button>
    </div>
</form>

<script>
function addCertItem() {
    var list = document.getElementById('certs-list');
    var html = '<div class="sortable-item"><div class="sortable-header">' +
        '<strong class="sortable-label">新认证</strong>' +
        '<button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'.sortable-item\').remove();">删除</button></div>' +
        '<div class="sortable-body"><input type="hidden" class="cert-id" value="cert' + Date.now() + '">' +
        '<div class="form-row"><div class="form-group form-group-2"><label>认证名称</label>' +
        '<input type="text" class="cert-name" value="" maxlength="100"></div>' +
        '<div class="form-group form-group-2"><label>Logo图片</label><div class="upload-field" data-accept="image/jpeg,image/png,image/webp">' +
        '<div class="upload-field-row"><input type="text" class="cert-image upload-url-input" value="">' +
        '<button type="button" class="upload-btn">选择文件</button></div>' +
        '<div class="upload-preview"></div></div></div></div></div></div>';
    list.insertAdjacentHTML('beforeend', html);
    var newItem = list.lastElementChild;
    initUploadField(newItem.querySelector('.upload-field'));
}

function prepareAboutSubmit() {
    var certs = [];
    document.querySelectorAll('#certs-list .sortable-item').forEach(function(el) {
        certs.push({
            id: el.querySelector('.cert-id').value,
            name: el.querySelector('.cert-name').value,
            image: el.querySelector('.cert-image').value
        });
    });
    document.getElementById('certs-json').value = JSON.stringify(certs);
    return true;
}
</script>
