<?php

function render_header(string $title): void
{
    $currentUser = user();
    $flash = take_flash();
    $activePage = $_GET['page'] ?? 'dashboard';
    $isActive = static function (array $pages) use ($activePage): string {
        return in_array($activePage, $pages, true) ? ' active' : '';
    };
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e(page_title($title)) ?></title>
        <link rel="stylesheet" href="assets/css/app.css">
    </head>
    <body>
    <?php if ($currentUser): ?>
        <aside class="sidebar">
            <a class="brand" href="index.php"><span>SW</span>Assist</a>
            <nav>
                <a class="<?= $isActive(['dashboard']) ?>" aria-current="<?= $activePage === 'dashboard' ? 'page' : 'false' ?>" href="index.php">Dashboard</a>
                <a class="<?= $isActive(['cases', 'case', 'case-edit', 'case-legacy', 'case-study', 'report']) ?>" aria-current="<?= in_array($activePage, ['cases', 'case', 'case-edit', 'case-legacy', 'case-study', 'report'], true) ? 'page' : 'false' ?>" href="index.php?page=cases">Cases</a>
                <a class="<?= $isActive(['case-create']) ?>" aria-current="<?= $activePage === 'case-create' ? 'page' : 'false' ?>" href="index.php?page=case-create">New case</a>
                <a class="<?= $isActive(['activity-create', 'activity-edit', 'activity-view']) ?>" aria-current="<?= in_array($activePage, ['activity-create', 'activity-edit', 'activity-view'], true) ? 'page' : 'false' ?>" href="index.php?page=activity-create">Add activity</a>
                <a class="<?= $isActive(['qr']) ?>" aria-current="<?= $activePage === 'qr' ? 'page' : 'false' ?>" href="index.php?page=qr">QR utility</a>
                <?php if (in_array($currentUser['role'], ['admin', 'supervisor'], true)): ?><a class="<?= $isActive(['users']) ?>" aria-current="<?= $activePage === 'users' ? 'page' : 'false' ?>" href="index.php?page=users">Users</a><?php endif; ?>
            </nav>
            <div class="sidebar-foot"><strong><?= e($currentUser['full_name']) ?></strong><small><?= e(ucfirst($currentUser['role'])) ?></small><a href="index.php?page=logout">Sign out</a></div>
        </aside>
        <main class="main">
    <?php else: ?>
        <main class="public-main">
    <?php endif; ?>
        <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?></main><script src="assets/js/app.js"></script></body></html><?php
}