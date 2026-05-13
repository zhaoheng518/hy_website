<form method="POST" action="/admin/home/save?lang=<?php echo $editLang; ?>" class="admin-form">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="section" value="cases">

    <div class="panel-info-box" style="margin-bottom:16px;">
        <p>此版块仅控制首页"应用案例"区块的标题和副标题。如需管理完整的案例内容，请前往 <a href="/admin/pages/edit/cases?lang=<?php echo $editLang; ?>">页面管理 → Case Studies</a>。</p>
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

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存案例区块</button>
    </div>
</form>
