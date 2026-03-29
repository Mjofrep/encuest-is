<?php
declare(strict_types=1);

function getMailConfig(): array
{
    $secrets = [];
    $secretsPath = __DIR__ . '/../config/secrets.php';
    if (file_exists($secretsPath)) {
        $secrets = require $secretsPath;
    }

    return is_array($secrets['mail'] ?? null) ? $secrets['mail'] : [];
}

function sendMail(string $to, string $subject, string $body): bool
{
    $mail = getMailConfig();
    $fromEmail = (string)($mail['from_email'] ?? '');
    $fromName = (string)($mail['from_name'] ?? '');

    if ($fromEmail === '') {
        return false;
    }

    $headers = [];
    $fromHeader = $fromEmail;
    if ($fromName !== '') {
        $fromHeader = $fromName . ' <' . $fromEmail . '>';
    }

    $headers[] = 'From: ' . $fromHeader;
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    return mail($to, $subject, $body, implode("\r\n", $headers));
}
