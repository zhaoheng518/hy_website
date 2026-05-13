<?php $pageTitle = '系统设置'; $activeMenu = 'settings'; ?>

<div class="panel">
    <div class="panel-header">
        <h2>系统设置</h2>
    </div>

    <div class="settings-tabs">
        <button type="button" class="tab-btn active" onclick="switchTab('general')">基本设置</button>
        <button type="button" class="tab-btn" onclick="switchTab('smtp')">邮件设置</button>
        <button type="button" class="tab-btn" onclick="switchTab('password')">修改密码</button>
        <button type="button" class="tab-btn" id="tab-btn-auto-reply">自动回复</button>
    </div>

    <div id="tab-general" class="tab-content active">
        <form method="POST" action="/admin/settings" class="admin-form">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tab" value="general">

            <div class="form-group">
                <label for="site_name">网站名称</label>
                <input type="text" id="site_name" name="site_name"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('site_name', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="site_url">网站URL</label>
                <input type="url" id="site_url" name="site_url"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('site_url', ''), ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="https://example.com">
            </div>
            <div class="form-group">
                <label for="logo">Logo URL</label>
                <input type="text" id="logo" name="logo"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('logo', ''), ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="/uploads/logo.png">
            </div>
            <div class="form-group">
                <label for="inquiry_email">询盘通知邮箱</label>
                <input type="email" id="inquiry_email" name="inquiry_email"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('inquiry_email', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="admin_email">管理员邮箱</label>
                <input type="email" id="admin_email" name="admin_email"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('admin_email', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="per_page">每页显示数量</label>
                <input type="number" id="per_page" name="per_page" min="1" max="100"
                       value="<?php echo (int) \App\Core\Config::get('per_page', 12); ?>">
            </div>

            <div class="form-group">
                <label>电话</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars(\App\Core\Config::get('phone', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>WhatsApp</label>
                <input type="text" name="whatsapp" value="<?php echo htmlspecialchars(\App\Core\Config::get('whatsapp', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>地址</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars(\App\Core\Config::get('address', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>公司全称</label>
                <input type="text" name="company_legal_name" value="<?php echo htmlspecialchars(\App\Core\Config::get('company_legal_name', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>公司简介</label>
                <textarea name="company_intro" rows="3"><?php echo htmlspecialchars(\App\Core\Config::get('company_intro', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label>Footer HTML</label>
                <textarea name="footer_html" rows="4"><?php echo htmlspecialchars(\App\Core\Config::get('footer_html', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label>LinkedIn</label>
                <input type="url" name="social_linkedin" value="<?php echo htmlspecialchars(\App\Core\Config::get('social_linkedin', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>YouTube</label>
                <input type="url" name="social_youtube" value="<?php echo htmlspecialchars(\App\Core\Config::get('social_youtube', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>Facebook</label>
                <input type="url" name="social_facebook" value="<?php echo htmlspecialchars(\App\Core\Config::get('social_facebook', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="head_scripts">全局 Head 追踪代码</label>
                <textarea id="head_scripts" name="head_scripts" rows="6" placeholder="<script>/* Head tracking code */</script>"><?php echo htmlspecialchars((string) \App\Core\Config::get('head_scripts', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>将注入到前台页面的 &lt;/head&gt; 前。</small>
            </div>
            <div class="form-group">
                <label for="body_scripts">全局 Body 底部代码</label>
                <textarea id="body_scripts" name="body_scripts" rows="6" placeholder="<script>/* Body bottom script */</script>"><?php echo htmlspecialchars((string) \App\Core\Config::get('body_scripts', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>将注入到前台页面的 &lt;/body&gt; 前。</small>
            </div>
            <div class="form-group">
                <label>默认语言</label>
                <select name="default_lang">
                    <?php foreach (\App\Core\Config::get('supported_langs', ['en', 'cn', 'es']) as $l): ?>
                    <option value="<?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $l === \App\Core\Config::get('default_lang', 'en') ? 'selected' : ''; ?>><?php echo strtoupper($l); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="hidden" name="multilang_enabled" value="0">
                <label><input type="checkbox" name="multilang_enabled" value="1" <?php echo \App\Core\Config::get('multilang_enabled', true) ? 'checked' : ''; ?>> 启用多语言前台</label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存基本设置</button>
            </div>
        </form>
    </div>

    <div id="tab-smtp" class="tab-content">
        <form method="POST" action="/admin/settings" class="admin-form">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tab" value="smtp">

            <div class="form-group">
                <label for="smtp_host">SMTP服务器</label>
                <input type="text" id="smtp_host" name="smtp_host"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('smtp_host', ''), ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="smtp.hostinger.com">
            </div>
            <div class="form-group">
                <label for="smtp_port">SMTP端口</label>
                <input type="number" id="smtp_port" name="smtp_port"
                       value="<?php echo (int) \App\Core\Config::get('smtp_port', 587); ?>"
                       placeholder="587">
            </div>
            <div class="form-group">
                <label for="smtp_user">SMTP用户名</label>
                <input type="text" id="smtp_user" name="smtp_user"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('smtp_user', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="smtp_pass">SMTP密码</label>
                <input type="password" id="smtp_pass" name="smtp_pass"
                       value="********" autocomplete="new-password">
                <small>保持星号不变则不修改密码。</small>
            </div>
            <div class="form-group">
                <label for="smtp_from">发件邮箱</label>
                <input type="email" id="smtp_from" name="smtp_from"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('smtp_from', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="smtp_from_name">发件人名称</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name"
                       value="<?php echo htmlspecialchars(\App\Core\Config::get('smtp_from_name', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存邮件设置</button>
            </div>
        </form>
    </div>

    <div id="tab-password" class="tab-content">
        <form method="POST" action="/admin/settings" class="admin-form">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tab" value="password">

            <div class="form-group">
                <label for="current_password">当前密码</label>
                <input type="password" id="current_password" name="current_password" required
                       autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="new_password">新密码</label>
                <input type="password" id="new_password" name="new_password" required
                       autocomplete="new-password" minlength="8">
                <small>最少8个字符。</small>
            </div>
            <div class="form-group">
                <label for="confirm_password">确认新密码</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       autocomplete="new-password" minlength="8">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">修改密码</button>
            </div>
        </form>
    </div>

    <!-- ── Module 9: Auto-Reply Email Settings ─────────────────────────────── -->
    <div id="tab-auto_reply" class="tab-content">
        <form method="POST" action="/admin/settings" class="admin-form">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tab" value="auto_reply">

            <div class="form-group">
                <input type="hidden" name="auto_reply_enabled" value="0">
                <label>
                    <input type="checkbox" name="auto_reply_enabled" value="1"
                        <?php echo \App\Core\Config::get('auto_reply_enabled', false) ? 'checked' : ''; ?>>
                    启用询盘自动回复邮件
                </label>
                <small>开启后，客户提交询盘时将自动收到一封确认邮件。需先配置 SMTP。</small>
            </div>

            <p style="margin:20px 0 8px;font-weight:600;border-bottom:1px solid #eee;padding-bottom:8px;">
                支持变量：<code>{customer_name}</code> <code>{product_name}</code>
                <code>{inquiry_id}</code> <code>{site_name}</code> <code>{site_url}</code>
            </p>
            <small style="color:#888;">留空则使用系统内置模板。</small>

            <?php
            $arLangs = [
                'en' => 'English',
                'cn' => '中文',
                'es' => 'Español',
            ];
            foreach ($arLangs as $arLang => $arLabel):
            ?>
            <fieldset style="border:1px solid #e0e0e0;border-radius:4px;padding:16px 20px;margin-top:20px;">
                <legend style="padding:0 8px;font-weight:600;"><?php echo $arLabel; ?></legend>

                <div class="form-group">
                    <label for="auto_reply_subject_<?php echo $arLang; ?>">邮件主题</label>
                    <input type="text"
                           id="auto_reply_subject_<?php echo $arLang; ?>"
                           name="auto_reply_subject_<?php echo $arLang; ?>"
                           value="<?php echo htmlspecialchars(
                               (string) \App\Core\Config::get('auto_reply_subject_' . $arLang, ''),
                               ENT_QUOTES, 'UTF-8'
                           ); ?>"
                           placeholder="留空使用默认主题">
                </div>

                <div class="form-group">
                    <label for="auto_reply_body_<?php echo $arLang; ?>">邮件正文（HTML）</label>
                    <textarea id="auto_reply_body_<?php echo $arLang; ?>"
                              name="auto_reply_body_<?php echo $arLang; ?>"
                              rows="8"
                              placeholder="留空使用内置模板文件"><?php echo htmlspecialchars(
                        (string) \App\Core\Config::get('auto_reply_body_' . $arLang, ''),
                        ENT_QUOTES, 'UTF-8'
                    ); ?></textarea>
                    <small>支持 HTML。变量用花括号包裹，如 <code>{customer_name}</code>。</small>
                </div>
            </fieldset>
            <?php endforeach; ?>

            <div class="form-actions" style="margin-top:24px;">
                <button type="submit" class="btn btn-primary">保存自动回复设置</button>
            </div>
        </form>
    </div>
    <!-- ── /Module 9 ─────────────────────────────────────────────────────────── -->

</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    document.getElementById('tab-' + tabId).classList.add('active');
    event.target.classList.add('active');
}

// Module 9 — auto-reply tab button wired via addEventListener (no onclick attribute)
(function () {
    var btn = document.getElementById('tab-btn-auto-reply');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tab-content').forEach(function (el) {
            el.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(function (el) {
            el.classList.remove('active');
        });
        document.getElementById('tab-auto_reply').classList.add('active');
        btn.classList.add('active');
    });
}());
</script>
