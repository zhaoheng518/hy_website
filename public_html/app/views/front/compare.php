<?php
$compareProducts = $compareProducts ?? [];
$lang = $lang ?? 'en';
$labels = [
    'en' => ['specs' => 'Specifications', 'empty' => 'Select products on product pages using “Add to compare”, then open this page.', 'back' => 'All products'],
    'cn' => ['specs' => '技术参数', 'empty' => '在产品页使用「加入对比」选择产品后回到此页查看。', 'back' => '全部产品'],
    'es' => ['specs' => 'Especificaciones', 'empty' => 'Añada productos desde las fichas con «Añadir a comparar».', 'back' => 'Todos los productos'],
];
$L = $labels[$lang] ?? $labels['en'];
?>

<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($h1 ?? 'Compare', ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="page-subtitle"><a href="<?php echo View::langUrl($lang, 'products'); ?>"><?php echo htmlspecialchars($L['back'], ENT_QUOTES, 'UTF-8'); ?></a></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($compareProducts)): ?>
        <p class="catalog-empty"><?php echo htmlspecialchars($L['empty'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
        <div class="compare-table-wrap">
            <table class="compare-table">
                <thead>
                    <tr>
                        <th scope="col"></th>
                        <?php foreach ($compareProducts as $cp): ?>
                        <th scope="col">
                            <a href="<?php echo View::langUrl($lang, 'product/' . ($cp['slug'] ?? '')); ?>"><?php echo htmlspecialchars($cp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row"><?php echo htmlspecialchars($L['specs'], ENT_QUOTES, 'UTF-8'); ?></th>
                        <?php foreach ($compareProducts as $cp): ?>
                        <td>
                            <?php if (!empty($cp['specs'])): ?>
                            <ul class="compare-spec-list">
                                <?php foreach ($cp['specs'] as $sp): ?>
                                <?php if (!empty($sp['label'])): ?>
                                <li><strong><?php echo htmlspecialchars($sp['label'], ENT_QUOTES, 'UTF-8'); ?>:</strong>
                                    <?php echo htmlspecialchars($sp['value'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            —
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th scope="row">SKU</th>
                        <?php foreach ($compareProducts as $cp): ?>
                        <td><?php echo htmlspecialchars($cp['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>
