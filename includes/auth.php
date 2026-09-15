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

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https://api.qrserver.com; style-src 'self'; script-src 'self'");
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_auth(): void
{
    send_no_cache_headers();
    send_security_headers();
    if (!user()) {
        redirect('index.php?page=login');
    }
    // Session idle timeout: 30 minutes
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_unset();
        session_destroy();
        // Start a new session for the flash message
        session_start();
        flash('error', 'Your session expired due to inactivity. Please sign in again.');
        redirect('index.php?page=login');
    }
    $_SESSION['last_activity'] = time();
}

function require_role(array $roles): void
{
    require_auth();
    if (!in_array(user()['role'], $roles, true)) {
        http_response_code(403);
        exit('You are not authorized to access this page.');
    }
}