<?php
$pageTitle = $pageTitle ?? '编辑首页板块';
$activeMenu = $activeMenu ?? 'sections';
$section = $section ?? [];
$detail = $sectionDetail ?? [];
$type = (string) ($section['type'] ?? '');
$enabled = !empty($section['enabled']);
$order = (int) ($section['order'] ?? 0);
$typeLabel = $typeLabels[$type] ?? ucfirst($type);
$image = trim((string) ($detail['image'] ?? ''));
?>

<div class="max-w-3xl">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">编辑首页板块：<?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="text-sm text-gray-500 mt-1">支持上传 Banner 图片，图片路径会保存为相对路径（`uploads/...`）。</p>
        </div>
        <a href="/admin/sections" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">返回列表</a>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        <?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <form method="post" action="/admin/sections/save/<?php echo rawurlencode($type); ?>" enctype="multipart/form-data" class="space-y-5">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken ?? \App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="bg-white border border-gray-200 rounded-xl p-5 sm:p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">板块类型</label>
                    <input type="text" readonly value="<?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">排序</label>
                    <input type="number" name="order" value="<?php echo $order; ?>" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800">
                </div>
            </div>

            <div class="flex items-center gap-3">
                <input id="enabled" type="checkbox" name="enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?> class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                <label for="enabled" class="text-sm font-medium text-gray-700">启用此板块</label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">标题</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars((string) ($detail['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">副标题</label>
                    <input type="text" name="subtitle" value="<?php echo htmlspecialchars((string) ($detail['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">描述</label>
                <textarea name="description" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php echo htmlspecialchars((string) ($detail['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">按钮文案</label>
                    <input type="text" name="button_text" value="<?php echo htmlspecialchars((string) ($detail['button_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">按钮链接</label>
                    <input type="text" name="button_link" value="<?php echo htmlspecialchars((string) ($detail['button_link'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="border-t border-gray-200 pt-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Banner 图片</label>
                <input type="file" name="banner_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-primary-700 hover:file:bg-primary-100">
                <p class="text-xs text-gray-500 mt-1">支持 JPG / PNG / WEBP。上传后写入 `uploads/...`。</p>
                <?php if ($image !== ''): ?>
                <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div class="text-xs text-gray-500 mb-2">当前图片：<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?></div>
                    <img src="/<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="banner" class="max-h-40 rounded border border-gray-200">
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-red-600">
                        <input type="checkbox" name="remove_image" value="1" class="h-4 w-4 rounded border-gray-300">
                        删除当前图片
                    </label>
                </div>
                <?php endif; ?>
            </div>

            <div class="pt-1 flex justify-end">
                <button type="submit" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    保存板块
                </button>
            </div>
        </div>
    </form>
</div>
