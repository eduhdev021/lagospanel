<?php

// Custom dependency directory: keep runtime/vendor metadata aligned with Composer.
$_ENV['APP_BASE_PATH'] = $_SERVER['APP_BASE_PATH'] = dirname(__DIR__);
$_ENV['COMPOSER_VENDOR_DIR'] = $_SERVER['COMPOSER_VENDOR_DIR'] = dirname(__DIR__).'/.cache/vendor';
