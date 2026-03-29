<?php
declare(strict_types=1);

$secrets = [];
$secretsPath = __DIR__ . '/secrets.php';
if (file_exists($secretsPath)) {
    $secrets = require $secretsPath;
}

$openaiSecrets = is_array($secrets['openai'] ?? null) ? $secrets['openai'] : [];

define('OPENAI_API_KEY', (string)($openaiSecrets['api_key'] ?? ''));
define('OPENAI_MODEL', (string)($openaiSecrets['model'] ?? 'gpt-5.4'));
