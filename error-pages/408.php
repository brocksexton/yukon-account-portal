<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 408,
    'title' => '408 – Request timeout',
    'headline' => 'That took a little too long.',
    'message' => 'The request timed out before it could complete.',
    'detail' => 'Try again. If you are on a slow connection, give it another moment.',
    'kicker' => 'Timeout',
    'heroVariant' => 1,
]);
