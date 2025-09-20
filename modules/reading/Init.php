<?php
namespace Politeia\Reading;

class Init {
    public static function register() {
        // Cargar todos los archivos PHP dentro de este módulo, excepto Init.php
        foreach (glob(__DIR__ . '/*.php') as $file) {
            if (basename($file) !== 'Init.php') {
                require_once $file;
            }
        }

        // Cargar archivos dentro de includes/
        foreach (glob(__DIR__ . '/includes/*.php') as $file) {
            require_once $file;
        }

        // Cargar archivos dentro de submódulos
        foreach (glob(__DIR__ . '/modules/**/*.php') as $file) {
            require_once $file;
        }

        // Cargar shortcodes si existen
        foreach (glob(__DIR__ . '/shortcodes/*.php') as $file) {
            require_once $file;
        }

        // Cargar helpers de templates si los hay. Los archivos de templates imprimen
        // marcado y dependen del contexto de la consulta, por lo que deben incluirse
        // solo cuando WordPress los requiera explícitamente.
        foreach (glob(__DIR__ . '/templates/helpers/*.php') ?: array() as $file) {
            require_once $file;
        }
    }
}
