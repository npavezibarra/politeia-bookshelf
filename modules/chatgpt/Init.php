<?php
namespace Politeia\ChatGPT;

class Init {
    public static function register() {
        // Cargar todos los archivos PHP dentro de este módulo, excepto Init.php
        foreach (glob(__DIR__ . '/*.php') as $file) {
            if (basename($file) !== 'Init.php') {
                require_once $file;
            }
        }

        foreach (glob(__DIR__ . '/modules/**/*.php') as $file) {
            require_once $file;
        }

        foreach (glob(__DIR__ . '/admin/*.php') as $file) {
            require_once $file;
        }
    }
}
