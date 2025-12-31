<?php
/**
 * Plugin Name: Politeia Bookshelf
 * Description: Unifica Politeia Reading y Politeia ChatGPT en un solo plugin modular.
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
// Temporary Phase 6 admin-only hook (remove after verification).
require_once __DIR__ . '/includes/helpers.php';

// Inicializar módulos.
Politeia\Reading\Init::register();
Politeia\ChatGPT\Init::register();

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
