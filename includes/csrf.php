<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

function ensureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', isHttpsRequest() ? '1' : '0');

    session_start();
}

function csrfToken(): string
{
    ensureSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function csrfValidate(?string $token): bool
{
    ensureSession();

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($sessionToken === '' || $token === null || $token === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}
