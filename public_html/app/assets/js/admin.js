document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 300);
        }, 5000);
    });

    var sidebar = document.querySelector('.admin-sidebar');
    if (sidebar) {
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target)) {
                var toggle = document.querySelector('.sidebar-toggle');
                if (toggle && !toggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    document.querySelectorAll('.upload-field').forEach(function(field) {
        initUploadField(field);
    });
});

function initUploadField(container) {
    var input = container.querySelector('.upload-url-input');
    var btn = container.querySelector('.upload-btn');
    var preview = container.querySelector('.upload-preview');
    var fileInput = container.querySelector('.upload-file-input');
    var accept = container.getAttribute('data-accept') || 'image/jpeg,image/png,image/webp';

    if (!input || !btn) return;

    if (!fileInput) {
        fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = accept;
        fileInput.style.display = 'none';
        fileInput.className = 'upload-file-input';
        container.appendChild(fileInput);
    }

    updatePreview();

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        if (!this.files || !this.files.length) return;
        var file = this.files[0];

        if (file.size > 2097152) {
            alert('文件超过 2MB 限制');
            return;
        }

        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            alert('仅支持 JPG/PNG/WebP 格式');
            return;
        }

        var csrfToken = document.getElementById('csrf-token');
        var token = csrfToken ? csrfToken.value : '';

        var formData = new FormData();
        formData.append('file', file);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/upload', true);
        xhr.setRequestHeader('X-CSRF-TOKEN', token);

        btn.textContent = '上传中...';
        btn.disabled = true;

        xhr.onload = function() {
            btn.textContent = '选择文件';
            btn.disabled = false;

            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success && resp.url) {
                        input.value = resp.url;
                        updatePreview();
                        var event = new Event('change', { bubbles: true });
                        input.dispatchEvent(event);
                    } else {
                        alert('上传失败: ' + (resp.error || '未知错误'));
                    }
                } catch (e) {
                    alert('上传响应解析失败');
                }
            } else {
                alert('上传失败 (HTTP ' + xhr.status + ')');
            }
        };

        xhr.onerror = function() {
            btn.textContent = '选择文件';
            btn.disabled = false;
            alert('上传网络错误');
        };

        xhr.send(formData);
        this.value = '';
    });

    input.addEventListener('change', updatePreview);
    input.addEventListener('input', updatePreview);

    function updatePreview() {
        if (!preview) return;
        var url = input.value.trim();
        if (url) {
            preview.innerHTML = '<img src="' + url.replace(/"/g, '') + '" alt="preview" style="max-width:100%;max-height:80px;border-radius:4px;margin-top:4px;">';
            preview.style.display = 'block';
        } else {
            preview.innerHTML = '';
            preview.style.display = 'none';
        }
    }
}
