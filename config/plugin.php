<?php

return [
    'marketplace' => [
        'enabled' => env('PLUGIN_MARKETPLACE_ENABLED', true),
        'repository' => [
            'type' => env('PLUGIN_MARKETPLACE_TYPE', 'gitee'),
            'owner' => env('GITEE_REPO_OWNER', ''),
            'repo' => env('GITEE_REPO_NAME', ''),
            'branch' => env('GITEE_REPO_BRANCH', 'master'),
            'access_token' => env('GITEE_ACCESS_TOKEN', ''),
        ],
        'cache' => [
            'index_ttl' => env('PLUGIN_CACHE_INDEX_TTL', 3600), // 1小时
            'detail_ttl' => env('PLUGIN_CACHE_DETAIL_TTL', 1800), // 30分钟
        ],
    ],
];
