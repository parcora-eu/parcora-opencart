<?php

// Version-agnostic core shared by the OpenCart 3 and 4 builds. Kept PHP 7.4
// compatible on purpose: OpenCart 3 shops still run it.
spl_autoload_register(function ($class) {
    $prefix = 'Parcora\\OpenCart\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
