<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 501,
    'title' => 'Not ready yet',
    'headline' => 'This area is still being built.',
    'message' => 'The construction team is finishing up the details. This feature is not available yet.',
    'detail' => 'Check back soon — we are working on it.',
    'kicker' => 'In Progress',
    'heroVariant' => 4,
]);
