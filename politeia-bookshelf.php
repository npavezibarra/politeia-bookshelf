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

// Inicializar módulos.
Politeia\Reading\Init::register();
Politeia\ChatGPT\Init::register();
