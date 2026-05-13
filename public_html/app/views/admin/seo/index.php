<?php $pageTitle = 'SEO 总控'; $activeMenu = 'seo'; ?>
<div class="panel">
    <div class="panel-header"><h2>SEO 总控</h2></div>

    <form method="post" action="/admin/seo" class="admin-form">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-section">
            <h3 class="form-section-title">品牌与默认 Meta</h3>
            <div class="form-group">
                <label>品牌名称 (site_name)</label>
                <input type="text" name="site_name" value="<?php echo htmlspecialchars(\App\Core\Config::get('site_name', ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="120">
            </div>
            <div class="form-group">
                <label>默认 Meta Title</label>
                <input type="text" name="default_meta_title" value="<?php echo htmlspecialchars(\App\Core\Config::get('default_meta_title', ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="90">
            </div>
            <div class="form-group">
                <label>默认 Meta Description</label>
                <textarea name="default_meta_description" rows="3" maxlength="200"><?php echo htmlspecialchars(\App\Core\Config::get('default_meta_description', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label>默认 OG 图片 URL</label>
                <input type="text" name="default_og_image" value="<?php echo htmlspecialchars(\App\Core\Config::get('default_og_image', ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="/uploads/og-default.jpg">
            </div>
            <div class="form-group">
                <label>Favicon URL</label>
                <input type="text" name="favicon" value="<?php echo htmlspecialchars(\App\Core\Config::get('favicon', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">索引与 Sitemap</h3>
            <div class="form-group">
                <input type="hidden" name="robots_block_all" value="0">
                <label><input type="checkbox" name="robots_block_all" value="1" <?php echo \App\Core\Config::get('robots_block_all', false) ? 'checked' : ''; ?>> 全站 robots 禁止抓取 (Disallow: /)</label>
            </div>
            <div class="form-group">
                <input type="hidden" name="sitemap_enabled" value="0">
                <label><input type="checkbox" name="sitemap_enabled" value="1" <?php echo \App\Core\Config::get('sitemap_enabled', true) ? 'checked' : ''; ?>> 启用自动生成 sitemap.xml / image-sitemap.xml</label>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">统计与广告代码</h3>
            <div class="form-group">
                <label>Google Analytics (测量 ID, 如 G-XXXX)</label>
                <input type="text" name="google_analytics_id" value="<?php echo htmlspecialchars(\App\Core\Config::get('google_analytics_id', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>GTM 容器 ID (如 GTM-XXXX)</label>
                <input type="text" name="gtm_container_id" value="<?php echo htmlspecialchars(\App\Core\Config::get('gtm_container_id', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>Google Ads / 其他 head 片段 (HTML)</label>
                <textarea name="google_ads_head" rows="4" class="code-editor"><?php echo htmlspecialchars(\App\Core\Config::get('google_ads_head', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label>WhatsApp 网页插件 / 聊天脚本 (HTML)</label>
                <textarea name="whatsapp_widget_script" rows="4" class="code-editor"><?php echo htmlspecialchars(\App\Core\Config::get('whatsapp_widget_script', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">结构化数据 (Organization JSON-LD)</h3>
            <div class="form-group">
                <textarea name="schema_organization_json" rows="8" class="code-editor" placeholder='{"@context":"https://schema.org","@type":"Organization",...}'><?php echo htmlspecialchars(\App\Core\Config::get('schema_organization_json', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>输出到前台时包裹为 &lt;script type="application/ld+json"&gt;</small>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">自定义 head / body 注入</h3>
            <div class="form-group">
                <label>&lt;head&gt;</label>
                <textarea name="head_scripts" rows="6" class="code-editor"><?php echo htmlspecialchars(\App\Core\Config::get('head_scripts', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label>&lt;body&gt; 底部</label>
                <textarea name="body_scripts" rows="6" class="code-editor"><?php echo htmlspecialchars(\App\Core\Config::get('body_scripts', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存全部</button>
            <button type="submit" name="regenerate_sitemap" value="1" class="btn">保存并重建 Sitemap</button>
        </div>
    </form>
</div>
