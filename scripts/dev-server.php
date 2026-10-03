<?php
/**
 * LagosPanel — Router para o servidor embutido do PHP
 * Uso: php -S 0.0.0.0:8080 -t public router.php
 */
$root = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : __DIR__ . '/public';
$uri  = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';

if ($uri !== '/') {
    $file = $root . $uri;
    if (is_file($file)) {
        return false; // serve arquivo estático direto (css, js, imagens)
    }
    if (is_dir($file)) {
        if (substr($uri, -1) !== '/') {
            header('Location: ' . $uri . '/', true, 301);
            return true;
        }
        if (is_file($file . '/index.php')) {
            $_SERVER['SCRIPT_NAME']     = $uri . 'index.php';
            $_SERVER['SCRIPT_FILENAME'] = $file . '/index.php';
            $_SERVER['PHP_SELF']        = $uri . 'index.php';
            require $file . '/index.php';
            return true;
        }
    }
}

// Todo o resto vai para o WordPress
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
require $root . '/index.php';
