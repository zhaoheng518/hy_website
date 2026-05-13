<form method="POST" action="/admin/home/save?lang=<?php echo $editLang; ?>" class="admin-form">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="section" value="banner">
    <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="form-group">
        <label for="title">H1 标题 *</label>
        <input type="text" id="title" name="title"
               value="<?php echo htmlspecialchars($sectionData['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
               required maxlength="200">
    </div>

    <div class="form-group">
        <label for="subtitle">副标题</label>
        <textarea id="subtitle" name="subtitle" rows="3" maxlength="300"><?php echo htmlspecialchars($sectionData['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="form-row">
        <div class="form-group form-group-2">
            <label for="cta_text">按钮文字</label>
            <input type="text" id="cta_text" name="cta_text"
                   value="<?php echo htmlspecialchars($sectionData['cta_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   maxlength="50">
        </div>
        <div class="form-group form-group-2">
            <label for="cta_link">按钮链接</label>
            <input type="text" id="cta_link" name="cta_link"
                   value="<?php echo htmlspecialchars($sectionData['cta_link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="/en/contact">
        </div>
    </div>

    <div class="form-group">
        <label>背景图片</label>
        <div class="upload-field" data-accept="image/jpeg,image/png,image/webp">
            <div class="upload-field-row">
                <input type="text" id="background_image" name="background_image"
                       value="<?php echo htmlspecialchars($sectionData['background_image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                       class="upload-url-input" placeholder="选择文件上传或手动输入URL">
                <button type="button" class="upload-btn">选择文件</button>
            </div>
            <div class="upload-preview"></div>
        </div>
    </div>

    <div class="form-group">
        <label for="background_video">背景视频URL <small>(留空则使用图片)</small></label>
        <input type="text" id="background_video" name="background_video"
               value="<?php echo htmlspecialchars($sectionData['background_video'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
               placeholder="/uploads/banner_xxx.mp4">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存Banner</button>
    </div>
</form>
