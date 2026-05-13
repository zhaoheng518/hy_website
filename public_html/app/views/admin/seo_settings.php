<?php $pageTitle = 'SEO与代码注入'; $activeMenu = 'seo'; ?>

<div class="panel">
    <div class="panel-header">
        <h2>SEO与代码注入</h2>
    </div>

    <div class="settings-tabs">
        <button type="button" class="tab-btn active" onclick="switchSeoTab('basic')">基础SEO</button>
        <button type="button" class="tab-btn" onclick="switchSeoTab('sitemap')">Sitemap</button>
        <button type="button" class="tab-btn" onclick="switchSeoTab('injection')">代码注入</button>
    </div>

    <div id="tab-basic" class="tab-content active">
        <form method="POST" action="/admin/seo" class="admin-form">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tab" value="seo">

            <div class="form-group">
                <label for="default_meta_title">默认 Meta Title</label>
                <input type="text" id="default_meta_title" name="default_meta_title"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('default_meta_title', ''), ENT_QUOTES, 'UTF-8'); ?>"
                       maxlength="70" placeholder="如: Stitch Tech - Professional Military Cable Manufacturer">
                <small>建议50-60个字符。当页面未单独设置SEO标题时，将使用此默认值 + 页面名称。</small>
            </div>
            <div class="form-group">
                <label for="default_meta_description">默认 Meta Description</label>
                <textarea id="default_meta_description" name="default_meta_description" rows="3" maxlength="160"
                          placeholder="如: Stitch Tech is a leading manufacturer of military-grade cables..."><?php echo htmlspecialchars(\App\Core\Config::get('default_meta_description', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>建议120-160个字符。当页面未单独设置描述时使用。</small>
            </div>
            <div class="form-group">
                <label for="favicon">Favicon URL</label>
                <div class="input-with-preview">
                    <input type="text" id="favicon" name="favicon"
                           value="<?php echo htmlspecialchars(\App\Core\Config::get('favicon', ''), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="/uploads/favicon.ico">
                    <button type="button" class="btn btn-sm" onclick="openMediaFor('favicon')">浏览</button>
                </div>
                <small>推荐 32x32 或 16x16 的 .ico 或 .png 文件。</small>
                <?php $favVal = \App\Core\Config::get('favicon', ''); ?>
                <?php if (!empty($favVal)): ?>
                <div style="margin-top:8px;">
                    <img src="<?php echo htmlspecialchars($favVal, ENT_QUOTES, 'UTF-8'); ?>" alt="Favicon Preview" style="width:32px;height:32px;border:1px solid var(--c-border);border-radius:4px;">
                </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存SEO设置</button>
            </div>
        </form>
    </div>

    <div id="tab-sitemap" class="tab-content">
        <div class="admin-form">
            <div class="sitemap-info">
                <h3>自动 Sitemap</h3>
                <p>系统会在保存SEO设置时自动生成 <code>sitemap.xml</code>，包含所有语言版本的首页、产品页、案例页和博客页。</p>
                <p>Google Search Console 提交地址：<code>/sitemap.xml</code></p>

                <?php
                $sitemapPath = ROOT_PATH . '/sitemap.xml';
                $sitemapExists = file_exists($sitemapPath);
                $sitemapTime = $sitemapExists ? date('Y-m-d H:i:s', filemtime($sitemapPath)) : '';
                ?>

                <div class="sitemap-status">
                    <span class="badge <?php echo $sitemapExists ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $sitemapExists ? '已生成' : '未生成'; ?>
                    </span>
                    <?php if ($sitemapExists): ?>
                    <span class="sitemap-time">最后更新: <?php echo $sitemapTime; ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($sitemapExists): ?>
                <div class="sitemap-actions">
                    <a href="/sitemap.xml" target="_blank" class="btn btn-sm">&#128279; 查看 Sitemap</a>
                </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="/admin/seo" class="sitemap-regen-form">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="tab" value="seo">
                <input type="hidden" name="regenerate_sitemap" value="1">
                <button type="submit" class="btn btn-primary">&#128260; 立即重新生成 Sitemap</button>
            </form>
        </div>
    </div>

    <div id="tab-injection" class="tab-content">
        <form method="POST" action="/admin/seo" class="admin-form">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tab" value="seo">

            <div class="form-group">
                <label for="head_scripts">&lt;head&gt; 代码注入</label>
                <textarea id="head_scripts" name="head_scripts" rows="8" class="code-editor"
                          placeholder="粘贴 Google Analytics (GA4), Facebook Pixel 等追踪代码..."><?php echo htmlspecialchars(\App\Core\Config::get('head_scripts', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>此代码将插入到每个页面的 &lt;head&gt; 标签内。支持 &lt;script&gt;, &lt;link&gt;, &lt;meta&gt; 等标签。</small>
            </div>
            <div class="form-group">
                <label for="body_scripts">&lt;body&gt; 代码注入</label>
                <textarea id="body_scripts" name="body_scripts" rows="8" class="code-editor"
                          placeholder="粘贴需要在 &lt;/body&gt; 前执行的脚本代码..."><?php echo htmlspecialchars(\App\Core\Config::get('body_scripts', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>此代码将插入到每个页面的 &lt;/body&gt; 标签前。适合放置 noscript 标签或延迟加载的脚本。</small>
            </div>

            <div class="injection-warning">
                <strong>&#9888; 注意：</strong>注入的代码会直接输出到页面，请确保代码来源可信。错误的代码可能导致网站无法正常加载。
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存代码注入</button>
            </div>
        </form>
    </div>
</div>

<style>
.sitemap-info h3 { font-size: 16px; margin-bottom: 8px; }
.sitemap-info p { color: var(--c-text-light); font-size: 14px; margin-bottom: 8px; line-height: 1.6; }
.sitemap-info code { background: var(--c-bg); padding: 2px 6px; border-radius: 4px; font-size: 13px; color: var(--c-primary); }
.sitemap-status { display: flex; align-items: center; gap: 12px; margin: 16px 0; }
.badge-success { background: var(--c-success-light); color: var(--c-success); padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; }
.badge-warning { background: var(--c-warning-light); color: var(--c-warning); padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; }
.sitemap-time { color: var(--c-text-light); font-size: 13px; }
.sitemap-actions { margin: 12px 0; }
.sitemap-regen-form { margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--c-border); }
.injection-warning { background: var(--c-warning-light); border: 1px solid #FDE68A; border-radius: var(--radius); padding: 12px 16px; margin-bottom: 16px; font-size: 13px; color: #92400E; line-height: 1.5; }
</style>

<script>
function switchSeoTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    document.getElementById('tab-' + tabId).classList.add('active');
    event.target.classList.add('active');
}

function openMediaFor(inputId) {
    var url = prompt('输入文件URL（如 /uploads/favicon.ico）：');
    if (url !== null && url.trim() !== '') {
        document.getElementById(inputId).value = url.trim();
    }
}
</script>
