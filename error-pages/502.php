<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 502,
    'title' => '502 – Bad gateway',
    'headline' => 'We couldn’t reach the next room.',
    'message' => 'A server in the chain didn’t respond correctly.',
    'detail' => 'Try refreshing, or come back shortly.',
    'kicker' => 'Network',
    'heroVariant' => 1,
]);
