<?php
namespace Politeia\Reading;

class Init {
    public static function register() {
        // Load PHP files in this module, except Init.php
        foreach (glob(__DIR__ . '/*.php') as $file) {
            if (basename($file) !== 'Init.php') {
                require_once $file;
            }
        }

        // Load includes
        foreach (glob(__DIR__ . '/includes/*.php') as $file) {
            require_once $file;
        }

        // Load submodules
        foreach (glob(__DIR__ . '/modules/**/*.php') as $file) {
            require_once $file;
        }

        // Load shortcodes
        foreach (glob(__DIR__ . '/shortcodes/*.php') as $file) {
            require_once $file;
        }

        // Defer template files until after WP query is set up
        add_action('wp', function () {
            foreach (glob(__DIR__ . '/templates/**/*.php') as $file) {
                require_once $file;
            }
        });
    }
}
