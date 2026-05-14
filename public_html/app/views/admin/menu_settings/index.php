<?php
/** @var list<array<string,mixed>> $rows */
/** @var list<array{id:int,label:string}> $parentOptions */
/** @var bool $tableMissing */
$icons = ['default','dashboard','image','package','tag','document','edit','blog','star','factory','info','mail','chat','menu','search','clipboard','ban','link','archive','cog','folder','globe','users','chart'];
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<script>window.__ADMIN_MENU_ROWS__ = <?php echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;</script>

<?php if (!empty($tableMissing)): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm mb-6">
        尚未创建数据表。请在 MySQL 中执行：
        <code class="bg-white px-1 rounded">database_migrations/20260514_admin_menus.sql</code>
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">侧边栏菜单</h2>
            <p class="text-sm text-gray-500 mt-1">拖拽排序请修改「排序」数字后点击「保存排序」；显示开关即时生效。</p>
        </div>
        <button type="button" id="btn-save-order" class="inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700">保存排序</button>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3 font-medium">ID</th>
                    <th class="text-left px-4 py-3 font-medium">标题</th>
                    <th class="text-left px-4 py-3 font-medium">图标</th>
                    <th class="text-left px-4 py-3 font-medium">Route</th>
                    <th class="text-left px-4 py-3 font-medium">父级</th>
                    <th class="text-left px-4 py-3 font-medium">排序</th>
                    <th class="text-left px-4 py-3 font-medium">显示</th>
                    <th class="text-left px-4 py-3 font-medium">可折叠</th>
                    <th class="text-left px-4 py-3 font-medium">active_key</th>
                    <th class="text-left px-4 py-3 font-medium">权限键</th>
                    <th class="text-right px-4 py-3 font-medium">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($rows as $r): ?>
                <?php
                $rid = (int) ($r['id'] ?? 0);
                $depth = 0;
                $pid = $r['parent_id'] ?? null;
                while ($pid !== null && $pid !== '') {
                    $depth++;
                    $found = false;
                    foreach ($rows as $x) {
                        if ((int)($x['id'] ?? 0) === (int)$pid) {
                            $pid = $x['parent_id'] ?? null;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found || $depth > 32) break;
                }
                $pad = str_repeat('&nbsp;&nbsp;', $depth);
                ?>
                <tr class="hover:bg-gray-50" data-menu-id="<?php echo $rid; ?>">
                    <td class="px-4 py-2 font-mono text-gray-600"><?php echo $rid; ?></td>
                    <td class="px-4 py-2"><?php echo $pad ? '<span class="text-gray-400">' . $pad . '</span>' : ''; ?><?php echo htmlspecialchars((string)($r['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="px-4 py-2 text-gray-600"><?php echo htmlspecialchars((string)($r['icon'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="px-4 py-2 text-gray-700 max-w-xs truncate" title="<?php echo htmlspecialchars((string)($r['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)($r['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="px-4 py-2 text-gray-600"><?php echo $r['parent_id'] === null || $r['parent_id'] === '' ? '—' : (int)$r['parent_id']; ?></td>
                    <td class="px-4 py-2">
                        <input type="number" class="js-sort w-20 border border-gray-300 rounded px-2 py-1" data-id="<?php echo $rid; ?>" value="<?php echo (int)($r['sort_order'] ?? 0); ?>">
                    </td>
                    <td class="px-4 py-2">
                        <input type="checkbox" class="js-vis h-4 w-4 rounded border-gray-300 text-primary-600" data-id="<?php echo $rid; ?>" <?php echo !empty($r['is_visible']) ? 'checked' : ''; ?> title="显示/隐藏">
                    </td>
                    <td class="px-4 py-2 text-center"><?php echo !empty($r['is_collapsible']) ? '是' : '否'; ?></td>
                    <td class="px-4 py-2 text-gray-600 max-w-[140px] truncate"><?php echo htmlspecialchars((string)($r['active_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="px-4 py-2 text-gray-600"><?php echo htmlspecialchars((string)($r['permission_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <button type="button" class="text-primary-600 hover:underline js-edit" data-id="<?php echo $rid; ?>">编辑</button>
                        <form method="post" action="/admin/menu-settings/deleteItem" class="inline" onsubmit="return confirm('确定删除？无子菜单才可删除。');">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id" value="<?php echo $rid; ?>">
                            <button type="submit" class="text-red-600 hover:underline">删除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 编辑/新增 抽屉 -->
<div id="menu-edit-modal" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-6 bg-black/40" role="dialog" aria-modal="true">
    <div class="bg-white rounded-t-2xl sm:rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 id="menu-edit-title" class="text-lg font-semibold">编辑菜单</h3>
            <button type="button" class="text-gray-500 hover:text-gray-800 text-2xl leading-none js-close-modal">&times;</button>
        </div>
        <form method="post" action="/admin/menu-settings/saveItem" class="px-6 py-4 space-y-4">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" id="f-id" value="0">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">标题</label>
                <input name="title" id="f-title" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">图标键</label>
                <select name="icon" id="f-icon" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <?php foreach ($icons as $ic): ?>
                        <option value="<?php echo htmlspecialchars($ic, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ic, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Route（父级分组填 #）</label>
                <input name="route" id="f-route" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="/admin/... 或 #">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">父级</label>
                <select name="parent_id" id="f-parent" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <?php foreach ($parentOptions as $opt): ?>
                        <option value="<?php echo (int)$opt['id']; ?>"><?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">排序</label>
                <input type="number" name="sort_order" id="f-sort" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="flex items-center gap-4">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_visible" id="f-visible" value="1" class="rounded border-gray-300"> 显示
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_collapsible" id="f-collapsible" value="1" class="rounded border-gray-300"> 可折叠（有子级时）
                </label>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">active_key（逗号分隔）</label>
                <input name="active_key" id="f-active" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="如 products">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">permission_key（空=任意管理员）</label>
                <input name="permission_key" id="f-perm" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="如 products">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 js-close-modal">取消</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white font-medium">保存</button>
            </div>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-md font-semibold text-gray-900 mb-4">新增菜单</h3>
    <form method="post" action="/admin/menu-settings/saveItem" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="0">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">标题</label>
            <input name="title" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">图标键</label>
            <select name="icon" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <?php foreach ($icons as $ic): ?>
                    <option value="<?php echo htmlspecialchars($ic, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ic, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Route</label>
            <input name="route" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="/admin/... 或 #">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">父级</label>
            <select name="parent_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <?php foreach ($parentOptions as $opt): ?>
                    <option value="<?php echo (int)$opt['id']; ?>"><?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">排序</label>
            <input type="number" name="sort_order" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div class="flex items-center gap-4 md:col-span-2">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_visible" value="1" checked class="rounded border-gray-300"> 显示</label>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_collapsible" value="1" checked class="rounded border-gray-300"> 可折叠</label>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">active_key</label>
            <input name="active_key" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">permission_key</label>
            <input name="permission_key" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white font-medium">新增保存</button>
        </div>
    </form>
</div>

<script>
(function () {
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') || '' : '';
    }
    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }

    document.getElementById('btn-save-order').addEventListener('click', function () {
        var orders = [];
        document.querySelectorAll('.js-sort').forEach(function (inp) {
            orders.push({ id: parseInt(inp.getAttribute('data-id'), 10), sort_order: parseInt(inp.value, 10) || 0 });
        });
        postJson('/admin/menu-settings/saveOrder', { _csrf: csrf(), orders: orders }).then(function (res) {
            if (res.success) { alert('排序已保存'); location.reload(); }
            else { alert(res.error || '保存失败'); }
        }).catch(function () { alert('网络错误'); });
    });

    document.querySelectorAll('.js-vis').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var id = parseInt(cb.getAttribute('data-id'), 10);
            postJson('/admin/menu-settings/toggleVisible', {
                _csrf: csrf(),
                id: id,
                is_visible: cb.checked ? 1 : 0
            }).then(function (res) {
                if (!res.success) { alert(res.error || '更新失败'); cb.checked = !cb.checked; }
            }).catch(function () { alert('网络错误'); cb.checked = !cb.checked; });
        });
    });

    var modal = document.getElementById('menu-edit-modal');
    function openModal() { modal.classList.remove('hidden'); }
    function closeModal() { modal.classList.add('hidden'); }
    document.querySelectorAll('.js-close-modal').forEach(function (b) { b.addEventListener('click', closeModal); });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

    document.querySelectorAll('.js-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = parseInt(btn.getAttribute('data-id'), 10);
            var rows = window.__ADMIN_MENU_ROWS__ || [];
            var row = null;
            for (var i = 0; i < rows.length; i++) {
                if (parseInt(rows[i].id, 10) === id) { row = rows[i]; break; }
            }
            if (!row) return;
            document.getElementById('menu-edit-title').textContent = '编辑菜单 #' + row.id;
            document.getElementById('f-id').value = row.id;
            document.getElementById('f-title').value = row.title || '';
            document.getElementById('f-icon').value = row.icon || 'default';
            document.getElementById('f-route').value = row.route || '';
            document.getElementById('f-parent').value = (row.parent_id === null || row.parent_id === '') ? '0' : String(row.parent_id);
            document.getElementById('f-sort').value = row.sort_order || 0;
            document.getElementById('f-visible').checked = !!parseInt(String(row.is_visible), 10);
            document.getElementById('f-collapsible').checked = !!parseInt(String(row.is_collapsible), 10);
            document.getElementById('f-active').value = row.active_key || '';
            document.getElementById('f-perm').value = row.permission_key || '';
            openModal();
        });
    });
})();
</script>
