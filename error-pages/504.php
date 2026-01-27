<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 504,
    'title' => '504 – Gateway timeout',
    'headline' => 'A server took too long to respond.',
    'message' => 'One of our services did not answer in time.',
    'detail' => 'Refresh the page, or try again shortly.',
    'kicker' => 'Network',
    'heroVariant' => 1,
]);
