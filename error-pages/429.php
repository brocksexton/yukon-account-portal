<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 429,
    'title' => '429 – Too many requests',
    'headline' => 'Easy there, speedy penguin.',
    'message' => 'You are making requests a little too quickly.',
    'detail' => 'Please wait a moment, then try again.',
    'kicker' => 'Rate Limit',
    'heroVariant' => 2,
]);
