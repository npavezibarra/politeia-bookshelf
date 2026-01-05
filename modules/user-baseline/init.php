<?php
namespace Politeia\UserBaseline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Init {
	public static function register() {
		foreach ( glob( __DIR__ . '/*.php' ) as $file ) {
			if ( basename( $file ) !== 'init.php' ) {
				require_once $file;
			}
		}

		foreach ( glob( __DIR__ . '/includes/*.php' ) as $file ) {
			require_once $file;
		}
	}
}
