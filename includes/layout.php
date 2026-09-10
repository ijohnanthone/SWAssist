<?php

function render_header(string $title): void
{
    $currentUser = user();
    $flash = take_flash();
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
                <a href="index.php">Dashboard</a>
                <a href="index.php?page=cases">Cases</a>
                <a href="index.php?page=case-create">New case</a>
                <a href="index.php?page=activity-create">Add activity</a>
                <a href="index.php?page=qr">QR utility</a>
                <?php if (in_array($currentUser['role'], ['admin', 'supervisor'], true)): ?><a href="index.php?page=users">Users</a><?php endif; ?>
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