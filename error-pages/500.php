<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 500,
    'title' => '500 – Server error',
    'headline' => 'The island hit a snag.',
    'message' => 'Something went wrong on our side.',
    'detail' => 'Please try again in a moment.',
    'kicker' => 'System Alert',
    'heroVariant' => 2,
]);
