<?php $pageTitle = '多语言管理'; $activeMenu = 'languages'; ?>

<div class="panel">
    <div class="panel-header">
        <h2>多语言内容管理</h2>
        <div class="panel-actions">
            <div class="lang-tabs">
                <?php foreach ($supportedLangs as $l): ?>
                <a href="/admin/languages?lang=<?php echo $l; ?>&type=<?php echo $dataType; ?>"
                   class="lang-tab<?php echo $editLang === $l ? ' active' : ''; ?>">
                    <?php echo strtoupper($l); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="type-tabs">
                <?php foreach ($validTypes as $t): ?>
                <?php
                    $typeNames = [
                        'home' => '首页',
                        'products' => '产品',
                        'cases' => '案例',
                        'blog' => '博客',
                        'contact' => '联系我们',
                        'seo' => 'SEO',
                    ];
                    $typeName = $typeNames[$t] ?? ucfirst($t);
                ?>
                <a href="/admin/languages?lang=<?php echo $editLang; ?>&type=<?php echo $t; ?>"
                   class="type-tab<?php echo $dataType === $t ? ' active' : ''; ?>">
                    <?php echo $typeName; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <form method="POST" action="/admin/languages/save" class="admin-form">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="data_type" value="<?php echo htmlspecialchars($dataType, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-group">
            <label for="json_data">
                JSON数据
                <span class="lang-badge"><?php echo strtoupper($editLang); ?></span> /
                <?php
                    $typeNames = [
                        'home' => '首页',
                        'products' => '产品',
                        'cases' => '案例',
                        'blog' => '博客',
                        'contact' => '联系我们',
                        'seo' => 'SEO',
                    ];
                    echo $typeNames[$dataType] ?? ucfirst($dataType);
                ?>
            </label>
            <textarea id="json_data" name="json_data" rows="20" class="code-editor"><?php echo htmlspecialchars(json_encode($langData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></textarea>
            <small>请谨慎编辑JSON数据，格式错误将无法保存。</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存修改</button>
        </div>
    </form>
</div>
