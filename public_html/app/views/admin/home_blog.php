<div class="panel-info-box">
    <p>博客区块自动调取最新发布的3篇文章。如需管理文章，请前往 <a href="/admin/languages?type=blog">多语言管理 → Blog</a> 或 <a href="/admin/products">产品管理</a>。</p>
</div>

<form method="POST" action="/admin/home/save?lang=<?php echo $editLang; ?>" class="admin-form">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="section" value="blog">

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
        <button type="submit" class="btn btn-primary">保存博客区块</button>
    </div>
</form>
