<?php
$pageTitle = $pageTitle ?? '首页板块管理';
$activeMenu = $activeMenu ?? 'sections';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">首页板块管理</h1>
        <p class="text-sm text-gray-500 mt-1">拖拽排序，控制首页各板块显示状态。</p>
    </div>
</div>

<?php if (!empty($flashSuccess)): ?>
<div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700 text-sm">
    <?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
<?php if (!empty($flashError)): ?>
<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
    <?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<div class="bg-white border border-gray-200 rounded-xl shadow-sm">
    <form id="sections-form" method="post" action="/admin/sections/update" class="p-4 sm:p-6">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="sections" id="sections-data" value="">

        <div id="sections-list" class="space-y-3">
            <?php foreach (($sections ?? []) as $index => $section): ?>
            <?php
            $type = (string) ($section['type'] ?? '');
            $enabled = !empty($section['enabled']);
            $label = $typeLabels[$type] ?? ucfirst($type);
            ?>
            <div class="section-item flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3"
                 draggable="true"
                 data-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
                 data-enabled="<?php echo $enabled ? '1' : '0'; ?>"
                 data-order="<?php echo (int) ($section['order'] ?? $index); ?>">
                <button type="button" class="drag-handle cursor-move text-gray-400 hover:text-gray-600" title="拖拽排序">☰</button>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <a href="/admin/sections/edit/<?php echo rawurlencode($type); ?>" class="inline-flex items-center rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100">编辑</a>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox"
                           class="section-toggle sr-only"
                           data-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
                           <?php echo $enabled ? 'checked' : ''; ?>>
                    <span class="toggle-ui relative inline-flex h-6 w-11 rounded-full transition-colors <?php echo $enabled ? 'bg-primary-600' : 'bg-gray-300'; ?>">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white transition <?php echo $enabled ? 'translate-x-5' : 'translate-x-1'; ?> mt-0.5"></span>
                    </span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-5 flex justify-end">
            <button type="submit" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                保存板块设置
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    var list = document.getElementById('sections-list');
    var form = document.getElementById('sections-form');
    var dataInput = document.getElementById('sections-data');
    if (!list || !form || !dataInput) return;

    var dragging = null;

    function bindSwitchState(item) {
        var toggle = item.querySelector('.section-toggle');
        var ui = item.querySelector('.toggle-ui');
        var knob = ui ? ui.querySelector('span') : null;
        if (!toggle || !ui || !knob) return;

        function paint() {
            var on = toggle.checked;
            item.setAttribute('data-enabled', on ? '1' : '0');
            ui.classList.toggle('bg-primary-600', on);
            ui.classList.toggle('bg-gray-300', !on);
            knob.classList.toggle('translate-x-5', on);
            knob.classList.toggle('translate-x-1', !on);
        }

        toggle.addEventListener('change', paint);
        paint();
    }

    function updateOrder() {
        var items = list.querySelectorAll('.section-item');
        items.forEach(function (item, index) {
            item.setAttribute('data-order', String(index));
        });
    }

    Array.prototype.forEach.call(list.querySelectorAll('.section-item'), function (item) {
        bindSwitchState(item);
    });

    list.addEventListener('dragstart', function (e) {
        var item = e.target.closest('.section-item');
        if (!item) return;
        dragging = item;
        dragging.classList.add('opacity-60');
        e.dataTransfer.effectAllowed = 'move';
    });

    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragging) return;
        var target = e.target.closest('.section-item');
        if (!target || target === dragging) return;

        var rect = target.getBoundingClientRect();
        var before = e.clientY < (rect.top + rect.height / 2);
        if (before) {
            list.insertBefore(dragging, target);
        } else {
            list.insertBefore(dragging, target.nextSibling);
        }
    });

    list.addEventListener('dragend', function () {
        if (!dragging) return;
        dragging.classList.remove('opacity-60');
        dragging = null;
        updateOrder();
    });

    form.addEventListener('submit', function () {
        updateOrder();
        var payload = [];
        var items = list.querySelectorAll('.section-item');
        items.forEach(function (item, index) {
            payload.push({
                type: item.getAttribute('data-type') || '',
                enabled: item.getAttribute('data-enabled') === '1',
                order: index
            });
        });
        dataInput.value = JSON.stringify(payload);
    });
})();
</script>
