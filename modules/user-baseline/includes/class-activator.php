<?php
namespace Politeia\UserBaseline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	public static function activate(): void {
		Installer::install();
	}
}
