<?php
if (is_array($adminUser ?? null)) {
    $row = $adminUser;
    $adminUser = (string) ($row['username'] ?? $row['email'] ?? '管理员');
    if (($adminEmail ?? '') === '') {
        $adminEmail = (string) ($row['email'] ?? '');
    }
} else {
    $adminUser = (string) ($adminUser ?? '管理员');
}
$adminEmail = (string) ($adminEmail ?? '');
$canNav = static function (string $perm): bool {
    return \App\Core\Auth::can($perm, 'read');
};
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? View::e($pageTitle) . ' - 后台管理' : '后台管理'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?php echo View::cacheBust('css/admin.css'); ?>">
</head>
<body class="bg-gray-50 overflow-x-hidden">
    <div class="min-h-screen min-h-[100dvh] flex">

        <!-- ========================================
             左侧边栏 (Sidebar)
             ======================================== -->
        <aside class="w-64 bg-slate-900 text-white flex flex-col fixed inset-y-0 start-0 z-30 h-screen max-h-[100dvh] overflow-hidden transition-transform duration-300" id="sidebar">
            <!-- Logo 区域 -->
            <div class="shrink-0 p-5 border-b border-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-white"><?php echo View::e($siteName ?? 'Stitch Tech'); ?></h1>
                        <p class="text-xs text-slate-400 mt-1">后台管理系统</p>
                    </div>
                </div>
            </div>

            <!-- 主导航（数据库 + AdminMenuService；折叠脚本见底部） -->
            <nav id="admin-sidebar-nav" class="flex-1 min-h-0 overflow-y-auto overscroll-y-contain py-4">
                <?php if (!empty($adminSidebarError ?? '')): ?>
                    <div class="px-5 py-3 text-xs text-red-300"><?php echo View::e($adminSidebarError); ?></div>
                <?php endif; ?>
                <?php echo $adminSidebarNavHtml ?? ''; ?>
            </nav>

            <!-- 底部链接 -->
            <div class="shrink-0 p-4 border-t border-slate-700">
                <a href="<?php echo View::e(View::langUrl(\App\Core\Config::get('default_lang', 'en'))); ?>" target="_blank" rel="noopener" class="flex items-center px-4 py-3 mb-2 text-primary-400 hover:text-primary-300 transition-colors rounded-lg hover:bg-slate-800">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    <span class="font-medium">查看前台网站</span>
                </a>
                <a href="/admin/logout" class="flex items-center px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 transition-colors rounded-lg">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span>退出登录</span>
                </a>
            </div>
        </aside>

        <!-- ========================================
             主内容区（仅 main 滚动，与侧栏导航滚动分离）
             ======================================== -->
        <div class="flex-1 ms-64 flex flex-col min-h-0 h-screen max-h-[100dvh] overflow-hidden">

            <!-- 顶部导航 (Header) -->
            <header class="shrink-0 h-16 bg-white border-b border-gray-200 px-6 flex items-center justify-between z-20">
                <!-- 左侧：面包屑 + 标题 -->
                <div class="flex items-center">
                    <!-- 移动端菜单按钮 -->
                    <button onclick="toggleSidebar()" class="lg:hidden me-4 text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <!-- 面包屑 -->
                    <nav class="flex items-center text-sm text-gray-500">
                        <a href="/admin" class="hover:text-primary-600 transition-colors">后台</a>
                        <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <svg class="w-4 h-4 mx-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                <?php if (isset($crumb['url'])): ?>
                                    <a href="<?php echo View::e($crumb['url']); ?>" class="hover:text-primary-600 transition-colors"><?php echo View::e($crumb['label']); ?></a>
                                <?php else: ?>
                                    <span class="text-gray-700 font-medium"><?php echo View::e($crumb['label']); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <svg class="w-4 h-4 mx-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                            <span class="text-gray-700 font-medium"><?php echo View::e($pageTitle ?? '仪表盘'); ?></span>
                        <?php endif; ?>
                    </nav>
                </div>

                <!-- 右侧：用户信息 -->
                <div class="flex items-center space-x-4">
                    <!-- 通知图标 -->
                    <?php if ($canNav('inquiries')): ?>
                    <a href="/admin/inquiries" class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if (isset($unreadInquiries) && $unreadInquiries > 0): ?>
                            <span class="absolute -top-1 -end-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center"><?php echo $unreadInquiries > 9 ? '9+' : $unreadInquiries; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>

                    <!-- 用户下拉菜单 -->
                    <div class="relative">
                        <button onclick="toggleUserMenu()" class="flex items-center space-x-3 hover:bg-gray-100 rounded-full px-3 py-2 transition-colors">
                            <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                <?php echo strtoupper(substr($adminUser ?? 'A', 0, 1)); ?>
                            </div>
                            <span class="hidden md:block text-sm font-medium text-gray-700"><?php echo View::e($adminUser ?? '管理员'); ?></span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <!-- 下拉菜单 -->
                        <div id="user-dropdown" class="hidden absolute end-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-sm font-medium text-gray-900"><?php echo View::e($adminUser ?? '管理员'); ?></p>
                                <p class="text-xs text-gray-500"><?php echo View::e($adminEmail ?? ''); ?></p>
                            </div>
                            <a href="/admin/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4 inline me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                个人设置
                            </a>
                            <a href="/admin/logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                <svg class="w-4 h-4 inline me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                退出登录
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- 内容区域 -->
            <main class="flex-1 min-h-0 overflow-y-auto overscroll-y-contain p-6 bg-gray-50">
                <!-- 提示消息 -->
                <?php if (!empty($error)): ?>
                    <div class="mb-6 bg-red-50 border-s-4 border-red-500 p-4 rounded-r-lg flex items-center">
                        <svg class="w-5 h-5 text-red-500 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-red-700"><?php echo View::e($error); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="mb-6 bg-green-50 border-s-4 border-green-500 p-4 rounded-r-lg flex items-center">
                        <svg class="w-5 h-5 text-green-500 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-green-700"><?php echo View::e($success); ?></span>
                    </div>
                <?php endif; ?>

                <!-- 页面内容 -->
                <?php echo $content; ?>
            </main>

            <!-- 页脚 -->
            <footer class="shrink-0 bg-white border-t border-gray-200 px-6 py-4">
                <p class="text-sm text-gray-500 text-center">
                    &copy; <?php echo date('Y'); ?> <?php echo View::e($siteName ?? 'Stitch Tech'); ?> - 后台管理系统
                </p>
            </footer>
        </div>
    </div>

    <!-- 移动端侧边栏遮罩 -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <!-- JavaScript -->
    <script>
        // 移动端侧边栏切换
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        // 用户下拉菜单切换
        function toggleUserMenu() {
            const dropdown = document.getElementById('user-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // 点击外部关闭下拉菜单
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('user-dropdown');
            const button = event.target.closest('button');
            if (!button && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // 响应式处理
        if (window.innerWidth < 1024) {
            document.getElementById('sidebar').classList.add('-translate-x-full');
        }
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        });

        (function () {
            var STORAGE_KEY = 'admin_sidebar_nav_scroll';

            function getSidebarNav() {
                return document.getElementById('admin-sidebar-nav');
            }

            function saveSidebarNavScroll() {
                var el = getSidebarNav();
                if (!el) return;
                try {
                    sessionStorage.setItem(STORAGE_KEY, String(Math.max(0, el.scrollTop)));
                } catch (e) {}
            }

            function restoreSidebarNavScroll() {
                var el = getSidebarNav();
                if (!el) return;
                try {
                    var raw = sessionStorage.getItem(STORAGE_KEY);
                    if (raw !== null && raw !== '') {
                        var y = parseInt(raw, 10);
                        if (!isNaN(y)) {
                            el.scrollTop = y;
                        }
                    }
                } catch (e) {}

                var active = el.querySelector('a.border-primary-500');
                if (active && typeof active.scrollIntoView === 'function') {
                    active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            }

            function bindSidebarScrollPersistence() {
                window.addEventListener('beforeunload', saveSidebarNavScroll);
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'hidden') {
                        saveSidebarNavScroll();
                    }
                });
                document.addEventListener('click', function (e) {
                    var a = e.target.closest('#admin-sidebar-nav a[href]');
                    if (!a) return;
                    var href = a.getAttribute('href') || '';
                    if (href.charAt(0) === '#') return;
                    saveSidebarNavScroll();
                }, true);
            }

            document.addEventListener('DOMContentLoaded', function () {
                bindSidebarScrollPersistence();
                restoreSidebarNavScroll();
                requestAnimationFrame(function () {
                    restoreSidebarNavScroll();
                });
            });

            window.addEventListener('pageshow', function (ev) {
                if (ev.persisted) {
                    restoreSidebarNavScroll();
                }
            });
        })();
    </script>
    <script>
    /* 优化后的 Sidebar：单开手风琴 + 点击子菜单不收缩 */
    (function () {
        document.addEventListener('DOMContentLoaded', function () {
            var nav = document.getElementById('admin-sidebar-nav');
            if (!nav) return;

            nav.addEventListener('click', function (e) {
                var toggleBtn = e.target.closest('[data-sidebar-toggle]');
                
                // === 点击的是折叠按钮（一级菜单）===
                if (toggleBtn) {
                    e.preventDefault();
                    var currentBranch = toggleBtn.closest('.admin-nav-branch');
                    if (!currentBranch) return;

                    // 关闭其他所有已打开的分支（单开模式）
                    document.querySelectorAll('.admin-nav-branch.is-open').forEach(function (branch) {
                        if (branch !== currentBranch) {
                            branch.classList.remove('is-open');
                            var otherToggle = branch.querySelector('[data-sidebar-toggle]');
                            if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
                        }
                    });

                    // 切换当前分支状态
                    var willOpen = !currentBranch.classList.contains('is-open');
                    currentBranch.classList.toggle('is-open', willOpen);
                    toggleBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                    
                    return;
                }

                // === 点击的是子菜单链接（链接本身）===
                var link = e.target.closest('a[href]');
                if (link && !toggleBtn) {
                    // 确保点击子菜单时，父级菜单保持打开状态
                    var parentBranch = link.closest('.admin-nav-branch');
                    if (parentBranch) {
                        parentBranch.classList.add('is-open');
                        var parentToggle = parentBranch.querySelector('[data-sidebar-toggle]');
                        if (parentToggle) {
                            parentToggle.setAttribute('aria-expanded', 'true');
                        }
                    }
                }
            });

            // 在上面的脚本里 DOMContentLoaded 内部最下方加入：
requestAnimationFrame(() => {
    const activeLink = nav.querySelector('a[href].active, a.border-primary-500');
    if (activeLink) {
        const branch = activeLink.closest('.admin-nav-branch');
        if (branch) {
            // 关闭其他
            document.querySelectorAll('.admin-nav-branch.is-open').forEach(b => {
                if (b !== branch) b.classList.remove('is-open');
            });
            branch.classList.add('is-open');
        }
    }
});
        });
    })();
</script>
    <script src="<?php echo View::cacheBust('js/admin.js'); ?>"></script>
</body>
</html>
