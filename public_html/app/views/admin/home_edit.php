<?php
$pageTitle = '首页板块管理';
$activeMenu = 'home';

$sectionConfig = [
    'seo_home' => ['name' => '首页 SEO (TDK)', 'icon' => '&#128269;', 'desc' => '独立 Meta Title / Description / Keywords；留空则沿用 Banner 与各语言 seo.json'],
    'banner'   => ['name' => 'Hero Banner', 'icon' => '&#127968;', 'desc' => '首屏大图、标题、CTA按钮'],
    'products' => ['name' => '产品版块',     'icon' => '&#9733;',  'desc' => '选择首页展示的产品分类'],
    'factory'  => ['name' => '工厂实力',     'icon' => '&#9881;',  'desc' => '工厂介绍文字、图片集'],
    'cases'    => ['name' => '应用案例',     'icon' => '&#128737;', 'desc' => '应用场景图文内容'],
    'about'    => ['name' => '关于我们',     'icon' => '&#128100;', 'desc' => '公司简介、资质认证Logo'],
    'blog'     => ['name' => '博客资讯',     'icon' => '&#128240;', 'desc' => '区块标题设置'],
    'contact'  => ['name' => '联系我们',     'icon' => '&#9993;',  'desc' => '联系方式、地址、WhatsApp'],
];

$currentSection = $section ?? 'banner';
?>

<div class="panel">
    <div class="panel-header">
        <h2>首页板块管理</h2>
        <div class="lang-tabs" style="margin:0;">
            <?php foreach ($supportedLangs as $l): ?>
            <a href="/admin/home/<?php echo $currentSection; ?>?lang=<?php echo $l; ?>"
               class="lang-tab<?php echo $editLang === $l ? ' active' : ''; ?>">
                <?php echo strtoupper($l); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section-accordion">
        <?php foreach ($sectionConfig as $key => $cfg): ?>
        <div class="accordion-item<?php echo $currentSection === $key ? ' active' : ''; ?>">
            <div class="accordion-header" onclick="toggleAccordion(this);">
                <div class="accordion-title">
                    <span class="accordion-icon"><?php echo $cfg['icon']; ?></span>
                    <div>
                        <strong><?php echo $cfg['name']; ?></strong>
                        <small><?php echo $cfg['desc']; ?></small>
                    </div>
                </div>
                <div class="accordion-actions">
                    <a href="/admin/home/<?php echo $key; ?>?lang=<?php echo $editLang; ?>"
                       class="btn btn-sm btn-primary" onclick="event.stopPropagation();">编辑内容</a>
                    <span class="accordion-chevron">&#9660;</span>
                </div>
            </div>
            <div class="accordion-body">
                <?php if ($currentSection === $key): ?>
                <?php
                $templateFile = VIEW_PATH . '/admin/home_' . $key . '.php';
                if (file_exists($templateFile)):
                    require $templateFile;
                else:
                ?>
                    <div class="panel-empty">板块编辑页面开发中...</div>
                <?php endif; ?>
                <?php else: ?>
                <div class="accordion-preview">
                    <p class="accordion-preview-text">点击"编辑内容"按钮修改此板块</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.section-accordion { margin-top: 16px; }
.accordion-item { border: 1px solid var(--c-border); border-radius: var(--radius); margin-bottom: 8px; overflow: hidden; }
.accordion-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: var(--c-bg-alt); cursor: pointer; transition: background 0.15s; }
.accordion-header:hover { background: var(--c-primary-light); }
.accordion-title { display: flex; align-items: center; gap: 12px; }
.accordion-icon { font-size: 24px; }
.accordion-title strong { display: block; font-size: 15px; }
.accordion-title small { display: block; font-size: 12px; color: var(--c-text-light); margin-top: 2px; }
.accordion-actions { display: flex; align-items: center; gap: 8px; }
.accordion-chevron { font-size: 12px; transition: transform 0.2s; color: var(--c-text-light); }
.accordion-item.active .accordion-chevron { transform: rotate(180deg); }
.accordion-body { display: none; padding: 20px; border-top: 1px solid var(--c-border); }
.accordion-item.active .accordion-body { display: block; }
.accordion-preview { text-align: center; padding: 20px; color: var(--c-text-light); }
</style>

<script>
function toggleAccordion(header) {
    var item = header.closest('.accordion-item');
    item.classList.toggle('active');
}
</script>
