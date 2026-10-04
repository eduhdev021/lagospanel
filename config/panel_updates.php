<?php

return [
    'enabled' => (bool) env('PANEL_UPDATES_ENABLED', false),
    'composer' => env('PANEL_UPDATES_COMPOSER', '/usr/local/bin/composer'),
    'repository' => 'https://github.com/eduhdev021/lagospanel.git',
    'api' => 'https://api.github.com/repos/eduhdev021/lagospanel',
    'branch' => 'main',
];
