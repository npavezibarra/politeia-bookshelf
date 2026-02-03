<?php
if (!defined('ABSPATH')) {
	exit;
}

class Politeia_Reading_Routes
{
	public static function init()
	{
		add_action('init', array(__CLASS__, 'register_rules'));
		add_filter('query_vars', array(__CLASS__, 'query_vars'));
		add_filter('template_include', array(__CLASS__, 'template_router'));
	}

	public static function register_rules()
	{
		// /my-books  (archive)
		add_rewrite_rule('^my-books/?$', 'index.php?prs_my_books_archive=1', 'top');

		// /my-books/my-book-{slug}  (single)
		add_rewrite_rule('^my-books/my-book-([^/]+)/?$', 'index.php?prs_book_slug=$matches[1]', 'top');

		// /feed
		add_rewrite_rule('^feed/?$', 'index.php?prs_feed_page=1', 'top');
	}

	public static function query_vars($vars)
	{
		$vars[] = 'prs_my_books_archive';
		$vars[] = 'prs_book_slug';
		$vars[] = 'prs_feed_page';
		return $vars;
	}

	public static function template_router($template)
	{
		if (get_query_var('prs_my_books_archive')) {
			if (function_exists('politeia_bookshelf_get_selected_template_file')) {
				$custom = politeia_bookshelf_get_selected_template_file('my-books');
				if ($custom) {
					return $custom;
				}
			}
			return POLITEIA_READING_PATH . 'templates/archive-my-books.php';
		}
		if (get_query_var('prs_book_slug')) {
			if (function_exists('politeia_bookshelf_get_selected_template_file')) {
				$custom = politeia_bookshelf_get_selected_template_file('single-book');
				if ($custom) {
					return $custom;
				}
			}
			return POLITEIA_READING_PATH . 'templates/my-book-single.php';
		}
		if (get_query_var('prs_feed_page')) {
			if (function_exists('politeia_bookshelf_get_selected_template_file')) {
				$custom = politeia_bookshelf_get_selected_template_file('feed');
				if ($custom) {
					return $custom;
				}
			}
			// Fallback if no template selected or logic fails (though settings should handle defaults)
			// We assume modules/feed/templates/feed.php is the target if not overridden by settings
			return plugin_dir_path(dirname(__DIR__, 2)) . 'modules/feed/templates/feed.php';
		}
		return $template;
	}
}
Politeia_Reading_Routes::init();
