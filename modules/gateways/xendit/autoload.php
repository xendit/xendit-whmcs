<?php
if (!defined('XENDIT_ROOT')) {
    define('XENDIT_ROOT', __DIR__);
}

if (!defined('WHMCS_ROOT')) {
    define('WHMCS_ROOT', dirname(__DIR__, 3));
}

spl_autoload_register(function ($className) {
    if (strpos($className, 'Xendit') !== false) {
        require __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . str_replace('\\', '/', mb_strcut($className, 11)) . '.php';
    }
});
