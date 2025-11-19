<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '') ?: '';

if (!defined('LAYOUT_WITH_SIDEBAR')) {
    define('LAYOUT_WITH_SIDEBAR', true);
}

$isSidebarEnabled = LAYOUT_WITH_SIDEBAR;

function nav_is_active(string $page, string $current, array $extra = []): bool
{
    $pages = array_merge([$page], $extra);
    return in_array($current, $pages, true);
}

function build_nav_item(string $label, string $href, string $icon, array $extra = []): array
{
    return [
        'label' => $label,
        'href' => $href,
        'icon' => $icon,
        'extra' => $extra,
    ];
}

$navItems = [
    build_nav_item('Dashboard', 'dashboard.php', 'bi-speedometer2'),
];

if (has_role('admin') || has_role('gestor')) {
    $navItems[] = build_nav_item('Usuários', 'users.php', 'bi-people', ['user_detail.php']);
}

if (has_role('admin')) {
    $navItems[] = build_nav_item('Alunos', 'admin_students.php', 'bi-mortarboard');
}

if (has_role('admin') || has_role('gestor')) {
    $navItems[] = build_nav_item('Cursos', 'courses.php', 'bi-journal-richtext', ['course_detail.php']);
    $navItems[] = build_nav_item('Questões', 'questions.php', 'bi-ui-checks-grid');
}

if (has_role('gestor')) {
    $navItems[] = build_nav_item('Alunos (Gestor)', 'manager_students.php', 'bi-person-workspace');
}

if (has_role('aluno')) {
    $navItems[] = build_nav_item('Meus cursos', 'my_courses.php', 'bi-collection-play');
    $navItems[] = build_nav_item('Meus certificados', 'my_certificates.php', 'bi-award');
}

$roleLabels = [
    'admin' => 'Administrador',
    'gestor' => 'Gestor',
    'aluno' => 'Aluno',
];
$roleLabel = $roleLabels[$user['role']] ?? ucfirst($user['role']);
$userInitial = mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8') ?: 'U';
$todayLabel = (new DateTimeImmutable())->format('d M Y');
?>

<div class="app-shell <?php echo $isSidebarEnabled ? 'has-sidebar' : 'app-shell--stacked'; ?>">
    <?php if ($isSidebarEnabled): ?>
        <aside class="app-sidebar" data-app-sidebar>
            <div class="app-sidebar__logo d-flex align-items-center justify-content-between gap-2">
                <div>
                    <a href="dashboard.php">WS Academy</a>
                    <small>Desenvolvimento contínuo</small>
                </div>
                <button type="button" class="app-sidebar__close" data-sidebar-toggle>
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <nav class="app-sidebar__nav">
                <?php foreach ($navItems as $item): ?>
                    <?php $active = nav_is_active($item['href'], $currentPage, $item['extra'] ?? []); ?>
                    <a href="<?php echo htmlspecialchars($item['href']); ?>" class="app-nav-link <?php echo $active ? 'is-active' : ''; ?>">
                        <i class="bi <?php echo htmlspecialchars($item['icon']); ?>"></i>
                        <span><?php echo htmlspecialchars($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="app-sidebar__footer">
                <span class="text-uppercase small d-block mb-1" style="letter-spacing:0.25em; color: rgba(255,255,255,0.6);">Último acesso</span>
                <strong><?php echo htmlspecialchars($todayLabel); ?></strong>
                <div class="role-pill mt-3"><?php echo htmlspecialchars($roleLabel); ?></div>
                <a href="logout.php" class="btn btn-outline-light mt-4 w-100 text-center">Sair</a>
            </div>
        </aside>
        <div class="app-sidebar-backdrop" data-sidebar-backdrop></div>
    <?php endif; ?>

    <div class="app-main">
        <header class="app-topbar">
            <?php if ($isSidebarEnabled): ?>
                <button type="button" class="app-menu-toggle" data-sidebar-toggle>
                    <i class="bi bi-list"></i>
                </button>
            <?php endif; ?>
            <div class="app-topbar__brand">
                <span>Plataforma corporativa</span>
                <strong>Open Academy WS by Washington Oliveira</strong>
            </div>
            <div class="app-search">
                <i class="bi bi-search"></i>
                <input type="search" placeholder="Busque cursos, alunos ou certificações" aria-label="Buscar na plataforma">
            </div>
            <div class="app-topbar__actions">
                <?php if (has_role('admin') || has_role('gestor')): ?>
                    <a href="courses.php" class="btn btn-danger btn-sm px-3">Catálogo</a>
                <?php endif; ?>
                <?php if (has_role('aluno')): ?>
                    <a href="my_courses.php" class="btn btn-outline-secondary btn-sm px-3">Minhas trilhas</a>
                <?php endif; ?>
                <div class="app-topbar__user">
                    <span class="app-topbar__avatar"><?php echo htmlspecialchars($userInitial); ?></span>
                    <div class="app-topbar__meta">
                        <span><?php echo htmlspecialchars($roleLabel); ?></span>
                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                    </div>
                </div>
            </div>
        </header>
        <main class="app-content">
