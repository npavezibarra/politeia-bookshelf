<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('POLITEIA_READING_PLAN_DB_VERSION')) {
	define('POLITEIA_READING_PLAN_DB_VERSION', '1.16.3');
}

if (!defined('POLITEIA_READING_PLAN_PATH')) {
	define('POLITEIA_READING_PLAN_PATH', __DIR__ . '/');
}

if (!defined('POLITEIA_READING_PLAN_URL')) {
	define('POLITEIA_READING_PLAN_URL', plugin_dir_url(__FILE__));
}

add_action('plugins_loaded', array('\\Politeia\\ReadingPlanner\\Upgrader', 'maybe_upgrade'));

require_once POLITEIA_READING_PLAN_PATH . 'includes/class-config.php';
require_once POLITEIA_READING_PLAN_PATH . 'includes/class-habit-validator.php';
require_once POLITEIA_READING_PLAN_PATH . 'includes/class-plan-session-deriver.php';
require_once POLITEIA_READING_PLAN_PATH . 'includes/class-plan-settlement-engine.php';
require_once POLITEIA_READING_PLAN_PATH . 'includes/class-habit-settlement-engine.php';

// Admin functionality
if (is_admin()) {
	require_once POLITEIA_READING_PLAN_PATH . 'includes/class-admin.php';
	add_action('init', array('\\Politeia\\ReadingPlanner\\Admin', 'init'));
}
