<?php $pageTitle = '媒体库'; $activeMenu = 'media'; ?>

<div class="panel">
    <div class="panel-header">
        <h2>媒体库</h2>
        <span class="panel-info">最大文件大小：<?php echo htmlspecialchars($maxSize, ENT_QUOTES, 'UTF-8'); ?> | 允许格式：JPG、PNG、WebP</span>
    </div>

    <div class="upload-area">
        <form method="POST" action="/admin/media/upload" enctype="multipart/form-data" class="upload-form">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="upload-dropzone" id="upload-dropzone">
                <p>点击选择或拖拽图片到此处</p>
                <input type="file" name="file" id="file-input" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
            </div>
            <label style="display:block;margin-top:10px;font-size:13px;">替换已有文件（可选，填 web 路径如 uploads/images/xxx.jpg 或仅文件名）</label>
            <input type="text" name="replace" placeholder="uploads/images/xxx.jpg" style="width:100%;max-width:420px;margin-top:4px;" autocomplete="off">
            <button type="submit" class="btn btn-primary" style="margin-top:10px;">上传</button>
        </form>
    </div>

    <?php if (empty($files)): ?>
        <div class="panel-empty">暂无媒体文件</div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($files as $file): ?>
            <div class="media-item">
                <div class="media-thumb">
                    <img src="<?php echo htmlspecialchars($file['url'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>"
                         loading="lazy">
                </div>
                <div class="media-info">
                    <span class="media-name" title="<?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span class="media-meta"><?php echo htmlspecialchars($file['dimensions'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($file['size'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="media-actions">
                    <button type="button" class="btn btn-sm" onclick="copyToClipboard('<?php echo htmlspecialchars($file['url'], ENT_QUOTES, 'UTF-8'); ?>')">复制URL</button>
                    <form method="POST" action="/admin/media/delete"
                          class="inline-form"
                          onsubmit="return confirm('确定要删除此文件吗？');">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            alert('URL已复制到剪贴板！');
        });
    } else {
        var input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        alert('URL已复制到剪贴板！');
    }
}

function openMediaPicker(targetId) {
    var url = prompt('输入图片URL或路径（如 /uploads/img_xxx.jpg）：');
    if (url !== null && url.trim() !== '') {
        document.getElementById(targetId).value = url.trim();
        var preview = document.getElementById(targetId + '-preview');
        if (preview) {
            preview.src = url.trim();
            preview.style.display = 'block';
        }
    }
}
</script>
