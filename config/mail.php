<?php
// config/mail.php

return [
    'default' => env('MAIL_MAILER', 'resend'),

    'mailers' => [
        'mailjet' => [
        'transport' => 'mailjet',
        ],
        'resend' => [
            'transport' => 'resend',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'resend',
                'log',
            ],
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@campushub.fr'),
        'name' => env('MAIL_FROM_NAME', 'CampusHub'),
    ],
];