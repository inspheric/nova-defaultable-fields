<?php

return [
    'cache' => [
        'key' => 'default_last', // The key to use in the cache. It will be made unique with the authenticated user's ID.
        'ttl' => 60 * 60, // The number of seconds to store each cached value.
    ],
];
