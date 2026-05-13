<?php
$pageTitle = $pageTitle ?? '一键备份中心';
$activeMenu = $activeMenu ?? 'backup';
?>

<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">一键备份中心</h1>
        <p class="mt-2 text-sm text-gray-500">打包 `app/data` 下全部 JSON 文件与 `uploads` 目录文件，生成完整 ZIP 备份下载。</p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <div class="space-y-3 text-sm text-gray-600 mb-6">
            <p>包含内容：</p>
            <ul class="list-disc list-inside space-y-1">
                <li>`app/data/` 中所有 `.json`</li>
                <li>`uploads/` 目录下全部文件</li>
                <li>可选：MySQL 表结构导出 `database_schema.sql`</li>
            </ul>
        </div>

        <?php if (!$zipReady): ?>
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
            当前服务器未启用 ZipArchive 扩展，暂时无法生成备份包。
        </div>
        <?php else: ?>
        <form method="post" action="/admin/backup/download">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="inline-flex items-center rounded-lg bg-primary-600 px-5 py-3 text-white font-semibold hover:bg-primary-700 shadow-sm">
                Download Full Backup
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
