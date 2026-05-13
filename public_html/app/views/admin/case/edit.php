<?php $pageTitle = '编辑案例'; $activeMenu = 'cases_admin'; ?>
<div class="max-w-5xl mx-auto space-y-4">
    <h1 class="text-2xl font-bold text-gray-900">编辑案例</h1>
    <div class="flex gap-2 text-sm">
        <?php foreach ($supportedLangs as $l): ?>
        <a href="/admin/case/edit/<?php echo rawurlencode($caseItem['slug'] ?? ''); ?>?lang=<?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?>" class="px-2 py-1 rounded <?php echo $l === $editLang ? 'bg-primary-100 font-medium' : 'text-gray-600'; ?>"><?php echo strtoupper($l); ?></a>
        <?php endforeach; ?>
    </div>
    <?php require __DIR__ . '/_form.inc.php'; ?>
</div>
