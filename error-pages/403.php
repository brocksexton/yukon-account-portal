<?php
require_once __DIR__ . '/partials/ep_layout.php';

ep_render([
    'code' => 403,
    'title' => '403 – Forbidden',
    'headline' => 'This door is locked.',
    'message' => 'You do not have permission to access this area of the island.',
    'detail' => 'If you believe this is a mistake, ask an admin to review your account permissions.',
    'kicker' => 'Access Control',
    'heroVariant' => 3,
]);
