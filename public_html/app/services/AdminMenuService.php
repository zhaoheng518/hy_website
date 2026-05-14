<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * 后台 Sidebar 菜单：从 admin_menus 读取、建树、权限过滤、输出 HTML。
 */
class AdminMenuService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * 检测表是否存在（未迁移时避免致命错误）。
     */
    public function tableExists(): bool
    {
        $dbName = $this->getCurrentDatabaseName();
        if ($dbName === '') {
            return false;
        }
        $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :tbl LIMIT 1';
        $row = $this->db->fetch($sql, ['schema' => $dbName, 'tbl' => 'admin_menus']);

        return $row !== null;
    }

    private function getCurrentDatabaseName(): string
    {
        try {
            $row = $this->db->fetch('SELECT DATABASE() AS d', []);

            return trim((string) ($row['d'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * 读取全部菜单行（含隐藏），管理页使用。
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAllRows(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $sql = 'SELECT * FROM admin_menus ORDER BY COALESCE(parent_id, 0) ASC, sort_order ASC, id ASC';

        return $this->db->fetchAll($sql);
    }

    /**
     * 读取可见菜单行（前台 Sidebar）。
     *
     * @return list<array<string, mixed>>
     */
    public function fetchVisibleRows(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $sql = 'SELECT * FROM admin_menus WHERE is_visible = 1 ORDER BY COALESCE(parent_id, 0) ASC, sort_order ASC, id ASC';

        return $this->db->fetchAll($sql);
    }

    /**
     * 将扁平行转为无限级树（children 数组）。
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function buildTree(array $rows): array
    {
        $byParent = [];
        foreach ($rows as $row) {
            $pid = $row['parent_id'] === null || $row['parent_id'] === '' ? 0 : (int) $row['parent_id'];
            $byParent[$pid][] = $row;
        }
        $sortFn = static function (array $a, array $b): int {
            $c = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));

            return $c !== 0 ? $c : ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        };
        foreach ($byParent as &$list) {
            usort($list, $sortFn);
        }
        unset($list);

        $build = function (int $parentId) use (&$build, &$byParent): array {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $node) {
                $id = (int) ($node['id'] ?? 0);
                $node['children'] = $build($id);
                $out[] = $node;
            }

            return $out;
        };

        return $build(0);
    }

    /**
     * 按 Auth::can 过滤节点；父级 permission 为空时，仅当存在可见子级时保留父级外壳。
     *
     * @param list<array<string, mixed>> $tree
     * @param callable(string):bool      $canRead
     * @return list<array<string, mixed>>
     */
    public function filterTreeByPermission(array $tree, callable $canRead): array
    {
        $out = [];
        foreach ($tree as $node) {
            $children = isset($node['children']) && is_array($node['children'])
                ? $this->filterTreeByPermission($node['children'], $canRead)
                : [];

            $pk = trim((string) ($node['permission_key'] ?? ''));
            $selfOk = ($pk === '') ? true : $canRead($pk);

            $route = (string) ($node['route'] ?? '');
            $isGroup = ($route === '' || $route === '#');

            if ($isGroup && $children !== []) {
                // 分组：无自身权限要求或已通过；至少一个子级可见则保留
                if ($pk !== '' && !$selfOk) {
                    continue;
                }
                $node['children'] = $children;
                $out[] = $node;
                continue;
            }

            if ($isGroup && $children === []) {
                continue;
            }

            if (!$selfOk) {
                continue;
            }

            $node['children'] = $children;
            $out[] = $node;
        }

        return $out;
    }

    /**
     * 判断当前 activeMenu 是否命中节点的 active_key（支持逗号分隔）。
     */
    public function isNodeActive(array $node, string $activeMenu): bool
    {
        $activeMenu = trim($activeMenu);
        $keys = trim((string) ($node['active_key'] ?? ''));
        if ($keys === '') {
            return false;
        }
        foreach (array_map('trim', explode(',', $keys)) as $k) {
            if ($k !== '' && $k === $activeMenu) {
                return true;
            }
        }

        return false;
    }

    /**
     * 子树是否包含激活节点（用于自动展开父级）。
     */
    public function subtreeContainsActive(array $node, string $activeMenu): bool
    {
        if ($this->isNodeActive($node, $activeMenu)) {
            return true;
        }
        foreach ($node['children'] ?? [] as $ch) {
            if (is_array($ch) && $this->subtreeContainsActive($ch, $activeMenu)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 输出 Sidebar 内部 HTML（不含 nav 外层）。
     *
     * @param callable(string):bool $canRead Auth::can($perm,'read')
     */
    public function renderSidebarHtml(string $activeMenu, callable $canRead, int $unreadInquiries = 0): string
    {
        if (!$this->tableExists()) {
            return '<div class="px-5 py-4 text-sm text-amber-300">未检测到 <code class="text-amber-200">admin_menus</code> 表，请执行迁移：<code class="text-amber-200">database_migrations/20260514_admin_menus.sql</code></div>';
        }

        $rows = $this->fetchVisibleRows();
        if ($rows === []) {
            return '<div class="px-5 py-4 text-sm text-slate-400">暂无可见菜单，请在「系统 → 侧边栏菜单」中配置。</div>';
        }

        $tree = $this->buildTree($rows);
        $tree = $this->filterTreeByPermission($tree, $canRead);

        $html = '<div class="px-3 mb-2"><span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">主菜单</span></div>';
        $html .= $this->renderBranch($tree, $activeMenu, $unreadInquiries, 0);

        return $html;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function renderBranch(array $nodes, string $activeMenu, int $unreadInquiries, int $depth): string
    {
        $buf = '';
        foreach ($nodes as $node) {
            $buf .= $this->renderNode($node, $activeMenu, $unreadInquiries, $depth);
        }

        return $buf;
    }

    private function renderNode(array $node, string $activeMenu, int $unreadInquiries, int $depth): string
    {
        $id = (int) ($node['id'] ?? 0);
        $title = htmlspecialchars((string) ($node['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $route = (string) ($node['route'] ?? '');
        $iconKey = (string) ($node['icon'] ?? 'default');
        $collapsible = !empty($node['is_collapsible']);
        $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
        $hasChildren = $children !== [];
        $isGroup = $hasChildren && ($route === '' || $route === '#');
        $open = $hasChildren && $this->subtreeContainsActive($node, $activeMenu);
        $padClass = $depth > 0 ? ' ps-8' : '';

        if ($isGroup && $collapsible) {
            $branchClass = 'admin-nav-branch' . ($open ? ' is-open' : '');
            $btn = '<button type="button" class="admin-nav-parent flex items-center w-full text-start px-5 py-3 hover:bg-slate-800 transition-colors text-white' . $padClass . '" data-sidebar-toggle="' . $id . '" aria-expanded="' . ($open ? 'true' : 'false') . '">'
                . $this->iconSvg($iconKey)
                . '<span class="flex-1">' . $title . '</span>'
                . '<svg class="w-4 h-4 text-slate-400 admin-nav-chevron transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>'
                . '</button>';
            $sub = '<div class="admin-nav-submenu" id="admin-submenu-' . $id . '">'
                . '<div class="admin-nav-submenu-inner">'
                . $this->renderBranch($children, $activeMenu, $unreadInquiries, $depth + 1)
                . '</div></div>';

            return '<div class="' . $branchClass . '" data-depth="' . $depth . '">' . $btn . $sub . '</div>';
        }

        if ($hasChildren && !$collapsible) {
            $h = '<div class="px-5 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">' . $title . '</div>';
            $h .= $this->renderBranch($children, $activeMenu, $unreadInquiries, $depth);

            return $h;
        }

        // 叶子链接
        $active = $this->isNodeActive($node, $activeMenu);
        $ac = $active ? ' bg-slate-800 border-e-4 border-primary-500' : '';
        $href = htmlspecialchars($route, ENT_QUOTES, 'UTF-8');
        $badge = '';
        if (strpos($route, '/admin/inquiries') === 0 && $unreadInquiries > 0) {
            $badge = '<span class="ms-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">' . (int) $unreadInquiries . '</span>';
        }

        return '<a href="' . $href . '" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors' . $ac . $padClass . '">'
            . $this->iconSvg($iconKey)
            . '<span>' . $title . '</span>' . $badge . '</a>';
    }

    /**
     * 预设 SVG，与旧版 layout 图标风格一致。
     */
    public function iconSvg(string $key): string
    {
        $paths = [
            'dashboard' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
            'image' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
            'package' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            'tag' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
            'document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'edit' => 'M4 6h16M4 12h16M4 18h7',
            'blog' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
            'star' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
            'factory' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
            'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'mail' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            'chat' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
            'menu' => 'M4 6h16M4 12h16M4 18h16',
            'search' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
            'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 8l2 2 4-4',
            'ban' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
            'link' => 'M7 7h10M7 12h7m-7 5h10M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z',
            'archive' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M8 12l4 4m0 0l4-4m-4 4V4',
            'cog' => '__DOUBLE__M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z__PATH2__M15 12a3 3 0 11-6 0 3 3 0 016 0z',
            'folder' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
            'globe' => 'M3 5h12M9 3v2m1 20l4-9m-4 9l4-9m-4 9H7m4 0h6m-2-4v4M7 13h10',
            'users' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
            'chart' => 'M7 12v4m0-4h10M5 20h14a1 1 0 001-1v-6a1 1 0 00-1-1H5a1 1 0 00-1 1v6a1 1 0 001 1zm4-10V8m4 4V4m4 8v-4',
        ];
        $d = $paths[$key] ?? $paths['dashboard'];
        if (strpos($d, '__DOUBLE__') === 0) {
            $rest = substr($d, strlen('__DOUBLE__'));
            $parts = explode('__PATH2__', $rest, 2);
            $inner = '';
            foreach ($parts as $seg) {
                $seg = trim($seg);
                if ($seg === '') {
                    continue;
                }
                $inner .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' . htmlspecialchars($seg, ENT_QUOTES, 'UTF-8') . '"></path>';
            }

            return '<svg class="w-5 h-5 me-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">' . $inner . '</svg>';
        }

        return '<svg class="w-5 h-5 me-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '"></path></svg>';
    }

    public function getById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        return $this->db->fetch('SELECT * FROM admin_menus WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function countChildren(int $parentId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $n = $this->db->fetchColumn('SELECT COUNT(*) FROM admin_menus WHERE parent_id = :p', ['p' => $parentId]);

        return (int) $n;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRow(array $data): int
    {
        $sql = 'INSERT INTO admin_menus (parent_id, title, icon, route, sort_order, is_visible, is_collapsible, active_key, permission_key, created_at)
                VALUES (:parent_id, :title, :icon, :route, :sort_order, :is_visible, :is_collapsible, :active_key, :permission_key, NOW())';

        return $this->db->insert($sql, [
            'parent_id' => $data['parent_id'] !== null && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null,
            'title' => (string) ($data['title'] ?? ''),
            'icon' => (string) ($data['icon'] ?? 'default'),
            'route' => (string) ($data['route'] ?? ''),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_visible' => !empty($data['is_visible']) ? 1 : 0,
            'is_collapsible' => !empty($data['is_collapsible']) ? 1 : 0,
            'active_key' => ($data['active_key'] ?? null) === '' ? null : ($data['active_key'] ?? null),
            'permission_key' => (string) ($data['permission_key'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateRow(int $id, array $data): int
    {
        $sql = 'UPDATE admin_menus SET
            parent_id = :parent_id,
            title = :title,
            icon = :icon,
            route = :route,
            sort_order = :sort_order,
            is_visible = :is_visible,
            is_collapsible = :is_collapsible,
            active_key = :active_key,
            permission_key = :permission_key,
            updated_at = NOW()
            WHERE id = :id';

        return $this->db->execute($sql, [
            'id' => $id,
            'parent_id' => isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null,
            'title' => (string) ($data['title'] ?? ''),
            'icon' => (string) ($data['icon'] ?? 'default'),
            'route' => (string) ($data['route'] ?? ''),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_visible' => !empty($data['is_visible']) ? 1 : 0,
            'is_collapsible' => !empty($data['is_collapsible']) ? 1 : 0,
            'active_key' => ($data['active_key'] ?? null) === '' ? null : ($data['active_key'] ?? null),
            'permission_key' => (string) ($data['permission_key'] ?? ''),
        ]);
    }

    public function deleteRow(int $id): int
    {
        if ($this->countChildren($id) > 0) {
            throw new \RuntimeException('请先删除子菜单');
        }

        return $this->db->execute('DELETE FROM admin_menus WHERE id = :id', ['id' => $id]);
    }

    /**
     * 批量更新排序。
     *
     * @param list<array{id:int,sort_order:int}> $orders
     */
    public function updateSortOrders(array $orders): void
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('UPDATE admin_menus SET sort_order = :s, updated_at = NOW() WHERE id = :id');
            foreach ($orders as $row) {
                $st->execute([
                    's' => (int) ($row['sort_order'] ?? 0),
                    'id' => (int) ($row['id'] ?? 0),
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function updateVisible(int $id, bool $visible): int
    {
        return $this->db->execute(
            'UPDATE admin_menus SET is_visible = :v, updated_at = NOW() WHERE id = :id',
            ['v' => $visible ? 1 : 0, 'id' => $id]
        );
    }

    /**
     * 父级不能指向自己或子孙（防环）。
     */
    public function isValidParent(int $id, ?int $newParentId): bool
    {
        if ($newParentId === null || $newParentId === 0) {
            return true;
        }
        if ($newParentId === $id) {
            return false;
        }
        $walk = $newParentId;
        $guard = 0;
        while ($walk > 0 && $guard < 64) {
            if ($walk === $id) {
                return false;
            }
            $row = $this->getById($walk);
            if ($row === null) {
                return true;
            }
            $walk = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
            $guard++;
        }

        return true;
    }
}
