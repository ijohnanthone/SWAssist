<?php

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/functions.php';

function send_no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_auth(): void
{
    send_no_cache_headers();
    if (!user()) {
        redirect('index.php?page=login');
    }
}

function require_role(array $roles): void
{
    require_auth();
    if (!in_array(user()['role'], $roles, true)) {
        http_response_code(403);
        exit('You are not authorized to access this page.');
    }
}