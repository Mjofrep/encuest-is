<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'feedback_db',
        'user' => 'feedback_user',
        'pass' => 'change_me',
        'charset' => 'utf8mb4'
    ],
    'openai' => [
        'api_key' => '',
        'model' => 'gpt-5.4'
    ],
    'mail' => [
        'from_email' => 'no-reply@tudominio.cl',
        'from_name' => 'Feedback',
        'use_mail' => true
    ]
];
