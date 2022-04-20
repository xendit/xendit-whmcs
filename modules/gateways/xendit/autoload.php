<?php
if (!defined('XENDIT_ROOT')) {
    define('XENDIT_ROOT', __DIR__);
}

if (!defined('WHMCS_ROOT')) {
    define('WHMCS_ROOT', dirname(__DIR__, 3));
}

spl_autoload_register(function ($className) {
    if (strpos($className, 'Xendit') !== false) {
        $classPath = explode("\\", $className);
        unset($classPath[0]);

        try {
            $filePath = __DIR__ . DIRECTORY_SEPARATOR . implode("/", array_map(function ($path) {
                    return $path == "Lib" ? strtolower($path) : $path;
            }, $classPath)) . ".php";
            if (file_exists($filePath)) {
                require $filePath;
            }
        } catch (Exception $e) {
        }
    }
});
