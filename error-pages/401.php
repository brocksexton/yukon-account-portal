<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 401,
    'title' => '401 – Please sign in',
    'headline' => 'You need to log in to continue.',
    'message' => 'This page is only available to logged-in penguins.',
    'detail' => 'Sign in and try again.',
    'kicker' => 'Authentication',
    'heroVariant' => 2,
]);
