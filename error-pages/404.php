<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 404,
    'title' => '404 – Page not found',
    'headline' => 'This path leads to an empty iceberg.',
    'message' => "We couldn’t find that page. It may have melted, moved, or never existed.",
    'detail' => 'Tip: Check the URL, or head back to the island.',
    'kicker' => 'Lost & Found',
    'heroVariant' => 1,
]);
