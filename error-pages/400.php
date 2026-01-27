<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 400,
    'title' => '400 – Bad request',
    'headline' => 'That request looks a little wobbly.',
    'message' => 'The server could not understand what was sent.',
    'detail' => 'Try again, or return home.',
    'kicker' => 'Request Error',
    'heroVariant' => 4,
]);
