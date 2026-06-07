<?php

if (PHP_SAPI === 'cli-server') {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    $file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);

    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
require __DIR__ . '/index.php';
