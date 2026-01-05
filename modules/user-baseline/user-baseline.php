<?php
namespace Politeia\UserBaseline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'POLITEIA_USER_BASELINE_DB_VERSION' ) ) {
	define( 'POLITEIA_USER_BASELINE_DB_VERSION', '1.0.0' );
}

add_action( 'plugins_loaded', array( '\\Politeia\\UserBaseline\\Upgrader', 'maybe_upgrade' ) );
