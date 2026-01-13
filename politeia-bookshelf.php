<?php
/**
 * Plugin Name: Politeia Bookshelf
 * Description: Unifies Politeia Reading and Politeia ChatGPT into a single modular plugin.
 * Version: 0.1.0
 * Author: Nicolás Pavez
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Autoload de Composer.
require_once __DIR__ . '/vendor/autoload.php';

// Cargar utilidades del administrador.
require_once __DIR__ . '/admin/google-books-settings.php';

// Inicializar módulos.
Politeia\Reading\Init::register();
Politeia\ChatGPT\Init::register();
require_once __DIR__ . '/modules/reading-planner/init.php';
require_once __DIR__ . '/modules/reading-planner/reading-planner.php';
Politeia\ReadingPlanner\Init::register();
require_once __DIR__ . '/modules/user-baseline/init.php';
require_once __DIR__ . '/modules/user-baseline/user-baseline.php';
Politeia\UserBaseline\Init::register();

register_activation_hook( __FILE__, array( '\\Politeia\\ReadingPlanner\\Activator', 'activate' ) );
register_activation_hook( __FILE__, array( '\\Politeia\\UserBaseline\\Activator', 'activate' ) );

/**
 * Register Politeia Bookshelf admin menu.
 */
function politeia_bookshelf_register_menu() {
    add_menu_page(
        __( 'Politeia Bookshelf', 'politeia-bookshelf' ),
        __( 'Politeia Bookshelf', 'politeia-bookshelf' ),
        'manage_options',
        'politeia-bookshelf',
        'politeia_bookshelf_render_admin_page',
        'dashicons-book-alt',
        6
    );

    add_submenu_page(
        'politeia-bookshelf',
        __( 'Overview', 'politeia-bookshelf' ),
        __( 'Overview', 'politeia-bookshelf' ),
        'manage_options',
        'politeia-bookshelf',
        'politeia_bookshelf_render_admin_page'
    );

    add_submenu_page(
        'politeia-bookshelf',
        __( 'Google Books API', 'politeia-bookshelf' ),
        __( 'Google Books API', 'politeia-bookshelf' ),
        'manage_options',
        'politeia-bookshelf-google-books',
        'politeia_bookshelf_render_admin_page'
    );
}
add_action( 'admin_menu', 'politeia_bookshelf_register_menu' );
