<?php
/**
 * autoload.php
 * Automatically loads PHP classes based on their namespace and directory structure.
 */

spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/src/';

    $file = $baseDir . str_replace('\\', '/', $class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
