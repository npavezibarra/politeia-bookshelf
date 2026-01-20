<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'POLITEIA_READING_PLAN_DB_VERSION' ) ) {
	define( 'POLITEIA_READING_PLAN_DB_VERSION', '1.2.0' );
}

if ( ! defined( 'POLITEIA_READING_PLAN_PATH' ) ) {
	define( 'POLITEIA_READING_PLAN_PATH', __DIR__ . '/' );
}

if ( ! defined( 'POLITEIA_READING_PLAN_URL' ) ) {
	define( 'POLITEIA_READING_PLAN_URL', plugin_dir_url( __FILE__ ) );
}

add_action( 'plugins_loaded', array( '\\Politeia\\ReadingPlanner\\Upgrader', 'maybe_upgrade' ) );

require_once POLITEIA_READING_PLAN_PATH . 'includes/class-habit-validator.php';
