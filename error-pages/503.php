<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 503,
    'title' => '503 – Maintenance',
    'headline' => 'The island is under maintenance.',
    'message' => 'Our construction crew is patching ice, polishing signposts, and keeping the island running smoothly.',
    'detail' => 'Please check back soon. Thanks for your patience!',
    'kicker' => 'Waddle back later',
    'heroVariant' => 1,
]);
