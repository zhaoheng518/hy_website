<?php $pageTitle = $isEdit ? '编辑页面' : '新增页面'; $activeMenu = 'pages'; ?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="panel">
    <div class="panel-header">
        <h2><?php echo $isEdit ? '编辑页面' : '新增页面'; ?> <span class="lang-badge"><?php echo strtoupper($editLang); ?></span></h2>
        <a href="/admin/pages?lang=<?php echo $editLang; ?>" class="btn btn-sm">&larr; 返回页面列表</a>
    </div>

    <form method="POST" action="<?php echo $isEdit ? '/admin/pages/edit/' . htmlspecialchars($page['slug'] ?? '', ENT_QUOTES, 'UTF-8') . '?lang=' . $editLang : '/admin/pages/create?lang=' . $editLang; ?>" class="admin-form" onsubmit="return preparePageSubmit();">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="content" id="content-hidden" value="">
        <input type="hidden" name="faqs_json" id="faqs-json" value="">
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-row">
            <div class="form-group form-group-2">
                <label for="title">页面标题 *</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($page['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="200">
            </div>
            <div class="form-group form-group-1">
                <label for="slug">路由别名 <small>(留空自动生成)</small></label>
                <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($page['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="100" pattern="[a-z0-9\-]*">
            </div>
        </div>

        <div class="form-group">
            <label for="subtitle">副标题</label>
            <input type="text" id="subtitle" name="subtitle" value="<?php echo htmlspecialchars($page['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="300">
        </div>

        <div class="form-group">
            <label>顶部大图 (Featured Image)</label>
            <div class="upload-field" data-accept="image/jpeg,image/png,image/webp">
                <div class="upload-field-row">
                    <input type="text" id="featured_image" name="featured_image"
                           value="<?php echo htmlspecialchars($page['featured_image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           class="upload-url-input" placeholder="选择文件上传或手动输入URL">
                    <button type="button" class="upload-btn">选择文件</button>
                </div>
                <div class="upload-preview"></div>
            </div>
        </div>

        <div class="form-group">
            <label>页面内容</label>
            <div id="page-content-editor"><?php echo $page['content'] ?? ''; ?></div>
        </div>

        <div class="form-section" style="margin-top:24px;">
            <h3 class="form-section-title">页面 FAQ (JSON)</h3>
            <div class="form-group">
                <textarea id="faqs-editor" rows="5" class="code-editor" placeholder='[{"question":"...","answer":"..."}]'><?php
                $faqEnc = json_encode($page['faqs'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                echo htmlspecialchars($faqEnc === '[]' ? '' : $faqEnc, ENT_QUOTES, 'UTF-8');
                ?></textarea>
            </div>
        </div>

        <div class="form-section" style="margin-top:24px;">
            <h3 class="form-section-title">SEO 设置</h3>
            <div class="form-group">
                <label for="seo_title">SEO 标题</label>
                <input type="text" id="seo_title" name="seo_title" value="<?php echo htmlspecialchars($page['seo_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="70">
                <small>留空则使用页面标题</small>
            </div>
            <div class="form-group">
                <label for="seo_description">SEO 描述</label>
                <textarea id="seo_description" name="seo_description" rows="3" maxlength="160"><?php echo htmlspecialchars($page['seo_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $isEdit ? '更新页面' : '创建页面'; ?></button>
            <a href="/admin/pages?lang=<?php echo $editLang; ?>" class="btn">取消</a>
        </div>
    </form>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var quill = new Quill('#page-content-editor', {
    theme: 'snow',
    placeholder: '输入页面内容...',
    modules: {
        toolbar: [
            [{ 'header': [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'align': [] }],
            ['link', 'blockquote'],
            [{ 'color': [] }, { 'background': [] }],
            ['clean']
        ]
    }
});

function preparePageSubmit() {
    document.getElementById('content-hidden').value = quill.root.innerHTML;
    var fq = document.getElementById('faqs-editor');
    document.getElementById('faqs-json').value = fq ? fq.value.trim() : '[]';
    return true;
}
</script>
