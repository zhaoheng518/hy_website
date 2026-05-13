<form method="POST" action="/admin/home/save?lang=<?php echo $editLang; ?>" class="admin-form">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="section" value="seo_home">

    <p class="form-hint" style="margin-bottom:16px;color:var(--c-text-light);font-size:13px;">
        此处为首页 <code>/<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>/</code> 的独立 TDK。
        留空时：标题与描述仍可由 Banner、各语言 <code>data/<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>/seo.json</code> 的 <code>home</code> 段及全站默认 Meta 兜底。
    </p>

    <div class="form-group">
        <label for="meta_title">Meta Title（建议 ≤60 字符）</label>
        <input type="text" id="meta_title" name="meta_title" maxlength="120"
               value="<?php echo htmlspecialchars($sectionData['meta_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
               placeholder="留空则使用 Banner 标题或 seo.json">
    </div>

    <div class="form-group">
        <label for="meta_description">Meta Description（建议 ≤160 字符）</label>
        <textarea id="meta_description" name="meta_description" rows="3" maxlength="320"
                  placeholder="留空则使用副标题摘要或 seo.json / 全站默认"><?php echo htmlspecialchars($sectionData['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="form-group">
        <label for="meta_keywords">Meta Keywords（可选，逗号分隔）</label>
        <input type="text" id="meta_keywords" name="meta_keywords" maxlength="300"
               value="<?php echo htmlspecialchars($sectionData['meta_keywords'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
               placeholder="例如: cable harness, defense manufacturing">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存</button>
    </div>
</form>
