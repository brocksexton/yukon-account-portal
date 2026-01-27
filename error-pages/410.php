<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 410,
    'title' => '410 – Gone',
    'headline' => 'This page has sailed away.',
    'message' => 'This content used to exist, but it has been removed.',
    'detail' => 'Head back home to find what you need.',
    'kicker' => 'Retired',
    'heroVariant' => 3,
]);
