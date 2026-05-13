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

            <!-- 主导航（独立滚动；scroll 位置由底部脚本写入 sessionStorage 恢复） -->
            <nav id="admin-sidebar-nav" class="flex-1 min-h-0 overflow-y-auto overscroll-y-contain py-4">
                <div class="px-3 mb-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">主菜单</span>
                </div>
                <?php if ($canNav('dashboard')): ?>
                <a href="/admin" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'dashboard' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span>仪表盘</span>
                </a>
                <?php endif; ?>

                <!-- 首页管理 -->
                <?php if ($canNav('home')): ?>
                <div class="px-3 mt-4 mb-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">首页管理</span>
                </div>
                <a href="/admin/home/banner" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'home_banner' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>横幅设置</span>
                </a>
                <a href="/admin/home/products" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'home_products' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <span>产品展示</span>
                </a>
                <a href="/admin/home/factory" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'home_factory' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <span>工厂介绍</span>
                </a>
                <a href="/admin/home/cases" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'home_cases' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                    </svg>
                    <span>成功案例</span>
                </a>
                <a href="/admin/home/about" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'home_about' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>关于我们</span>
                </a>
                <a href="/admin/home/blog" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'home_blog' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                    </svg>
                    <span>博客新闻</span>
                </a>
                <a href="/admin/home/contact" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'home_contact' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span>联系信息</span>
                </a>
                <?php endif; ?>

                <!-- 产品管理 -->
                <div class="px-3 mt-4 mb-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">内容管理</span>
                </div>
                <?php if ($canNav('products')): ?>
                <a href="/admin/products" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'products' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span>产品管理</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('categories')): ?>
                <a href="/admin/categories" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'categories' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    <span>产品分类</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('pages')): ?>
                <a href="/admin/pages" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'pages' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span>页面管理</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('pages')): ?>
                <a href="/admin/page" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'page_cms' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                    <span>页面 CMS</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('blog')): ?>
                <a href="/admin/blog" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo in_array(($activeMenu ?? ''), ['blog', 'home_blog'], true) ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                    <span>博客管理</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('cases')): ?>
                <a href="/admin/case" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo in_array(($activeMenu ?? ''), ['cases_admin', 'case', 'home_cases'], true) ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                    <span>案例管理</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('inquiries')): ?>
                <a href="/admin/inquiries" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'inquiries' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                    </svg>
                    <span>询盘中心</span>
                    <?php if (isset($unreadInquiries) && $unreadInquiries > 0): ?>
                        <span class="ms-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full"><?php echo $unreadInquiries; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <?php if ($canNav('newsletter')): ?>
                <a href="/admin/newsletter" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'newsletter' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>邮件订阅</span>
                </a>
                <?php endif; ?>

                <!-- 系统设置 -->
                <div class="px-3 mt-4 mb-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">系统</span>
                </div>
                <?php if ($canNav('media')): ?>
                <a href="/admin/media" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'media' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>媒体库</span>
                </a>
                <a href="/admin/files" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'files' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    <span>文件管理</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('menu')): ?>
                <a href="/admin/menu" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'menu' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    <span>菜单</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('seo')): ?>
                <a href="/admin/seo" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'seo' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <span>SEO 总控</span>
                </a>
                <a href="/admin/seo/audit" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'seo_audit' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 8l2 2 4-4"></path></svg>
                    <span>SEO 健康检查</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('settings')): ?>
                <a href="/admin/404monitor" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === '404monitor' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                    <span>404 监控</span>
                </a>
                <a href="/admin/redirects" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'redirects' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 12h7m-7 5h10M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"></path></svg>
                    <span>301 重定向</span>
                </a>
                <a href="/admin/backup" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'backup' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M8 12l4 4m0 0l4-4m-4 4V4"></path></svg>
                    <span>备份中心</span>
                </a>
                <a href="/admin/settings" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'settings' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span>系统设置</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('users')): ?>
                <a href="/admin/users" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'users' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span>用户权限</span>
                </a>
                <?php endif; ?>
                <?php if ($canNav('languages')): ?>
                <a href="/admin/languages" class="flex items-center px-5 py-3 hover:bg-slate-800 transition-colors <?php echo ($activeMenu ?? '') === 'languages' ? 'bg-slate-800 border-e-4 border-primary-500' : ''; ?>">
                    <svg class="w-5 h-5 me-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1 20l4-9m-4 9l4-9m-4 9H7m4 0h6m-2-4v4M7 13h10"></path></svg>
                    <span>多语言数据</span>
                </a>
                <?php endif; ?>
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
    <script src="<?php echo View::cacheBust('js/admin.js'); ?>"></script>
</body>
</html>
