<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Routes {
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_rules' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_request_fallback' ) );
		add_action( 'template_redirect', array( __CLASS__, 'direct_template_fallback' ), 0 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'pre_handle_404', array( __CLASS__, 'prevent_404_for_custom_routes' ), 10, 2 );
		add_filter( 'template_include', array( __CLASS__, 'template_router' ) );
	}

	public static function register_rules(): void {
		add_rewrite_rule( '^members/([^/]+)/my-plans/?$', 'index.php?prs_my_plans=1&prs_my_plans_user=$matches[1]', 'top' );
		add_rewrite_rule( '^members/([^/]+)/my-plans-ver-2/?$', 'index.php?prs_my_plans_ver_2=1&prs_my_plans_ver_2_user=$matches[1]', 'top' );
		add_rewrite_rule( '^members/([^/]+)/my-reading-stats/?$', 'index.php?prs_my_reading_stats=1&prs_my_reading_stats_user=$matches[1]', 'top' );
		add_rewrite_rule( '^members/([^/]+)/my-reading-stats-2/?$', 'index.php?prs_my_reading_stats_2=1&prs_my_reading_stats_2_user=$matches[1]', 'top' );
		add_rewrite_rule( '^my-plan/([0-9]+)/?$', 'index.php?prs_my_single_plan=1&plan_id=$matches[1]', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = 'prs_my_plans';
		$vars[] = 'prs_my_plans_user';
		$vars[] = 'prs_my_plans_ver_2';
		$vars[] = 'prs_my_plans_ver_2_user';
		$vars[] = 'prs_my_reading_stats';
		$vars[] = 'prs_my_reading_stats_user';
		$vars[] = 'prs_my_reading_stats_2';
		$vars[] = 'prs_my_reading_stats_2_user';
		$vars[] = 'prs_my_single_plan';
		$vars[] = 'plan_id';
		return $vars;
	}

	public static function template_router( string $template ): string {
		$template_v2 = POLITEIA_READING_PLAN_PATH . 'templates/my-plans-ver-2/my-plans-ver-2.php';
		$template_single = POLITEIA_READING_PLAN_PATH . 'templates/my-single-plan/my-single-plan.php';

		if ( get_query_var( 'prs_my_plans_ver_2' ) ) {
			if ( $template_v2 && file_exists( $template_v2 ) ) {
				return $template_v2;
			}
			return POLITEIA_READING_PLAN_PATH . 'templates/my-plans/my-plans.php';
		}
		if ( get_query_var( 'prs_my_plans' ) ) {
			return POLITEIA_READING_PLAN_PATH . 'templates/my-plans/my-plans.php';
		}
		if ( get_query_var( 'prs_my_reading_stats' ) ) {
			return POLITEIA_READING_PLAN_PATH . 'templates/my-reading-stats/my-reading-stats.php';
		}
		if ( get_query_var( 'prs_my_reading_stats_2' ) ) {
			return POLITEIA_READING_PLAN_PATH . 'templates/my-reading-stats-2/my-reading-stats-2.php';
		}
		if ( get_query_var( 'prs_my_single_plan' ) ) {
			if ( $template_single && file_exists( $template_single ) ) {
				return $template_single;
			}
		}
		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		if ( is_string( $request_path ) && preg_match( '#^/members/[^/]+/my-plans-ver-2/?$#', $request_path ) ) {
			global $wp_query;
			if ( isset( $wp_query ) ) {
				$wp_query->is_404 = false;
			}
			status_header( 200 );
			if ( $template_v2 && file_exists( $template_v2 ) ) {
				return $template_v2;
			}
		}
		if ( is_string( $request_path ) && preg_match( '#^/my-plan/([0-9]+)/?$#', $request_path, $matches ) ) {
			global $wp_query;
			if ( isset( $wp_query ) ) {
				$wp_query->is_404 = false;
				$wp_query->query_vars['plan_id'] = isset( $matches[1] ) ? (int) $matches[1] : 0;
				$wp_query->query_vars['prs_my_single_plan'] = 1;
			}
			status_header( 200 );
			if ( $template_single && file_exists( $template_single ) ) {
				return $template_single;
			}
		}

		return $template;
	}

	public static function parse_request_fallback( $wp ): void {
		if ( ! isset( $wp ) || ! is_object( $wp ) ) {
			return;
		}
		if ( ! empty( $wp->query_vars['prs_my_single_plan'] ) ) {
			return;
		}

		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		if ( is_string( $request_path ) && preg_match( '#^/my-plan/([0-9]+)/?$#', $request_path, $matches ) ) {
			$wp->query_vars['prs_my_single_plan'] = 1;
			$wp->query_vars['plan_id'] = isset( $matches[1] ) ? (int) $matches[1] : 0;
		}
	}

	public static function prevent_404_for_custom_routes( $preempt, $wp_query ) {
		if ( get_query_var( 'prs_my_single_plan' ) ) {
			if ( isset( $wp_query ) && is_object( $wp_query ) ) {
				$wp_query->is_404 = false;
			}
			status_header( 200 );
			return true;
		}

		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		if ( is_string( $request_path ) && preg_match( '#^/my-plan/([0-9]+)/?$#', $request_path ) ) {
			if ( isset( $wp_query ) && is_object( $wp_query ) ) {
				$wp_query->is_404 = false;
			}
			status_header( 200 );
			return true;
		}

		return $preempt;
	}

	public static function direct_template_fallback(): void {
		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		if ( ! is_string( $request_path ) || ! preg_match( '#^/my-plan/([0-9]+)/?$#', $request_path, $matches ) ) {
			return;
		}

		$template_single = POLITEIA_READING_PLAN_PATH . 'templates/my-single-plan/my-single-plan.php';
		if ( ! file_exists( $template_single ) ) {
			return;
		}

		$plan_id = isset( $matches[1] ) ? (int) $matches[1] : 0;
		set_query_var( 'prs_my_single_plan', 1 );
		set_query_var( 'plan_id', $plan_id );

		global $wp_query;
		if ( isset( $wp_query ) && is_object( $wp_query ) ) {
			$wp_query->is_404 = false;
		}

		status_header( 200 );
		include $template_single;
		exit;
	}
}

Routes::init();
