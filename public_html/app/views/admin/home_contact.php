<form method="POST" action="/admin/home/save?lang=<?php echo $editLang; ?>" class="admin-form">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="section" value="contact">

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
        <label for="email">联系邮箱</label>
        <input type="email" id="email" name="email"
               value="<?php echo htmlspecialchars($sectionData['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="form-row">
        <div class="form-group form-group-2">
            <label for="phone">电话</label>
            <input type="text" id="phone" name="phone"
                   value="<?php echo htmlspecialchars($sectionData['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group form-group-2">
            <label for="whatsapp">WhatsApp</label>
            <input type="text" id="whatsapp" name="whatsapp"
                   value="<?php echo htmlspecialchars($sectionData['whatsapp'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="form-group">
        <label for="address">公司地址</label>
        <textarea id="address" name="address" rows="2" maxlength="300"><?php echo htmlspecialchars($sectionData['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存联系信息</button>
    </div>
</form>
