<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'POLITEIA_READING_PLAN_DB_VERSION' ) ) {
	define( 'POLITEIA_READING_PLAN_DB_VERSION', '1.0.0' );
}

add_action( 'plugins_loaded', array( '\\Politeia\\ReadingPlanner\\Upgrader', 'maybe_upgrade' ) );
