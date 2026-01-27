<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 503,
    'title' => 'Maintenance – Waddle back soon',
    'headline' => 'The island is under maintenance.',
    'message' => 'Gary is tuning the machinery and tightening a few bolts. This area will be back online soon.',
    'detail' => 'Thanks for your patience. Try again in a little while.',
    'kicker' => 'Maintenance',
    'heroVariant' => 4,
]);
