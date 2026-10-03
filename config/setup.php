<?php

return ['state_path' => storage_path('app/private/setup.json'), 'lock_path' => storage_path('app/private/installed.lock'), 'mutex_path' => storage_path('app/private/setup.mutex'), 'env_path' => base_path('.env'), 'sqlite_path' => database_path('database.sqlite')];
