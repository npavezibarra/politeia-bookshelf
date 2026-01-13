<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Routes {
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_router' ) );
	}

	public static function register_rules(): void {
		add_rewrite_rule( '^members/([^/]+)/my-plans/?$', 'index.php?prs_my_plans=1&prs_my_plans_user=$matches[1]', 'top' );
		add_rewrite_rule( '^members/([^/]+)/my-reading-stats/?$', 'index.php?prs_my_reading_stats=1&prs_my_reading_stats_user=$matches[1]', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = 'prs_my_plans';
		$vars[] = 'prs_my_plans_user';
		$vars[] = 'prs_my_reading_stats';
		$vars[] = 'prs_my_reading_stats_user';
		return $vars;
	}

	public static function template_router( string $template ): string {
		if ( get_query_var( 'prs_my_plans' ) ) {
			return POLITEIA_READING_PLAN_PATH . 'templates/my-plans/my-plans.php';
		}
		if ( get_query_var( 'prs_my_reading_stats' ) ) {
			return POLITEIA_READING_PLAN_PATH . 'templates/my-reading-stats/my-reading-stats.php';
		}

		return $template;
	}
}

Routes::init();
