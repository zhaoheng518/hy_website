<?php $pageTitle = '新建博客'; $activeMenu = 'blog'; ?>
<div class="max-w-5xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-900">新建博客</h1>
        <div class="flex gap-2 text-sm">
            <?php foreach ($supportedLangs as $l): ?>
            <a href="/admin/blog/create?lang=<?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?>"
               class="px-2 py-1 rounded <?php echo $l === $editLang ? 'bg-primary-100 text-primary-800 font-medium' : 'text-gray-600 hover:bg-gray-100'; ?>"><?php echo strtoupper($l); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <p><a href="/admin/blog?lang=<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>" class="text-primary-600 text-sm hover:underline">← 返回列表</a></p>
    <?php require __DIR__ . '/_form.inc.php'; ?>
</div>
