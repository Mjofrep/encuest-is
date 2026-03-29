<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return true;
    }

    return false;
}

function enforceHttpsIfNeeded(): void
{
    if (!APP_FORCE_HTTPS) {
        return;
    }

    if (PHP_SAPI === 'cli') {
        return;
    }

    if (isHttpsRequest()) {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($host === '') {
        return;
    }

    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    $csp = [
        "default-src 'self'",
        "img-src 'self' data: https:",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "font-src 'self' https://cdn.jsdelivr.net",
        "connect-src 'self'",
        "frame-ancestors 'none'"
    ];

    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function getBaseUrl(): string
{
    if (APP_BASE_URL !== '') {
        return rtrim(APP_BASE_URL, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return '';
    }

    $scheme = isHttpsRequest() ? 'https' : 'http';
    return $scheme . '://' . $host;
}
