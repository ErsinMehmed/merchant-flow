<?php

return [
    /*
     * Raw numeric IDs everywhere: Nova URLs, the Go API and the merchant portal
     * all address rows by their database key.
     */
    'hash-id' => env('HASH_ID', false),

    /*
     * Apiato's HTTP API layer is not used. The merchant-facing API is the Go
     * service; this application only serves Nova.
     */
    'api' => [
        'url' => env('API_URL', 'http://localhost'),
        'rate-limiter' => [
            'name' => 'api',
            'enabled' => false,
            'attempts' => 30,
            'expires' => 1,
        ],
    ],

    'requests' => [
        'force-accept-header' => false,
        'use-etag' => false,
    ],
];
