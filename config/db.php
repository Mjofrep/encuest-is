<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $secrets = [];
    $secretsPath = __DIR__ . '/secrets.php';
    if (file_exists($secretsPath)) {
        $secrets = require $secretsPath;
    }

    $dbSecrets = is_array($secrets['db'] ?? null) ? $secrets['db'] : [];

    $host = (string)($dbSecrets['host'] ?? '127.0.0.1');
    $db   = (string)($dbSecrets['name'] ?? '');
    $user = (string)($dbSecrets['user'] ?? '');
    $pass = (string)($dbSecrets['pass'] ?? '');
    $charset = (string)($dbSecrets['charset'] ?? 'utf8mb4');

    if ($db === '' || $user === '') {
        throw new RuntimeException('DB credentials not configured. Create config/secrets.php');
    }

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
