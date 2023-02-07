<?php

return [
    /*
    |-------------------------------------
    | Messenger display name
    |-------------------------------------
    */
    'name' => env('TOMATO_CHAT_NAME', 'Tomato Messenger'),

    'guard' => env('TOMATO_CHAT_GUARD', 'web'),

    'users_model' => env('TOMATO_CHAT_USER_MODEL', \App\Models\User::class),

    'video_chat' => true,

    'audio_chat' => true,

    /*
    |-------------------------------------
    | The disk on which to store added
    | files and derived images by default.
    |-------------------------------------
    */
    'storage_disk_name' => env('TOMATO_CHAT_STORAGE_DISK', 'public'),

    /*
    |-------------------------------------
    | Routes configurations
    |-------------------------------------
    */
    'routes' => [
        'name' => env('TOMATO_CHAT_ROUTES_NAME', 'admin.chat.'),
        'prefix' => env('TOMATO_CHAT_ROUTES_PREFIX', 'admin/chat'),
        'middleware' => env('TOMATO_CHAT_ROUTES_MIDDLEWARE', ['web', 'auth', 'splade']),
        'namespace' => env('TOMATO_CHAT_ROUTES_NAMESPACE', 'TomatoPHP\TomatoChat\Http\Controllers'),
    ],
    'api_routes' => [
        'prefix' => env('TOMATO_CHAT_API_ROUTES_PREFIX', 'admin/chat/api'),
        'middleware' => env('TOMATO_CHAT_API_ROUTES_MIDDLEWARE', ['auth:sanctum']),
        'namespace' => env('TOMATO_CHAT_API_ROUTES_NAMESPACE', 'TomatoPHP\TomatoChat\Http\Controllers\Api'),
    ],

    /*
    |-------------------------------------
    | Pusher API credentials
    |-------------------------------------
    */
    'pusher' => [
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
            'port' => env('PUSHER_PORT', 443),
            'scheme' => env('PUSHER_SCHEME', 'https'),
            'encrypted' => true,
            'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            'curl_options' => [
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],
    ],

    /*
    |-------------------------------------
    | User Avatar
    |-------------------------------------
    */
    'user_avatar' => [
        'folder' => 'users-avatar',
        'default' => 'avatar.png',
    ],

    /*
    |-------------------------------------
    | Gravatar
    |
    | imageset property options:
    | [ 404 | mp | identicon (default) | monsterid | wavatar ]
    |-------------------------------------
    */
    'gravatar' => [
        'enabled' => false,
        'image_size' => 200,
        'imageset' => 'identicon'
    ],

    /*
    |-------------------------------------
    | Attachments
    |-------------------------------------
    */
    'attachments' => [
        'folder' => 'attachments',
        'download_route_name' => 'attachments.download',
        'allowed_images' => (array) ['png','jpg','jpeg','gif'],
        'allowed_files' => (array) ['zip','rar','txt'],
        'max_upload_size' => env('TOMATO_CHAT_MAX_FILE_SIZE', 150), // MB
    ],

    /*
    |-------------------------------------
    | Messenger's colors
    |-------------------------------------
    */
    'colors' => (array) [
        '#2180f3',
        '#2196F3',
        '#00BCD4',
        '#3F51B5',
        '#673AB7',
        '#4CAF50',
        '#FFC107',
        '#FF9800',
        '#ff2522',
        '#9C27B0',
    ],
];
