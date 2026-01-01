<?php
// modules/book-detection/ajax-confirm-inline-update.php
if ( ! defined('ABSPATH') ) exit;

/**
 * Inline update for a pending confirm row (title|author).
 * POST: nonce, id, field ('title'|'author'), value
 * OUT: { success:true, data:{ id, title, author, hash } }
 */
function politeia_confirm_update_field_ajax() {
	try {
		check_ajax_referer('politeia-chatgpt-nonce', 'nonce');

		$id    = isset($_POST['id'])    ? (int) $_POST['id'] : 0;
		$field = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';
		$value = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

		if ( ! $id || ! in_array($field, ['title','author'], true) ) {
			wp_send_json_error('invalid_request');
		}

		global $wpdb;
		$tbl = $wpdb->prefix . 'politeia_book_confirm';
		$user_id = get_current_user_id();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tbl} WHERE id=%d AND user_id=%d AND status='pending' LIMIT 1",
				$id, $user_id
			),
			ARRAY_A
		);
		if ( ! $row ) {
			wp_send_json_error('not_found');
		}

		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( $value === '' ) {
			wp_send_json_error('empty_value');
		}

		// Helpers (fallback)
		if ( ! function_exists('politeia__normalize_text') ) {
			function politeia__normalize_text( $s ) {
				$s = (string) $s;
				$s = wp_strip_all_tags( $s );
				$s = html_entity_decode( $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
				$s = trim( $s );
				$s = remove_accents( $s );
				if ( function_exists( 'mb_strtolower' ) ) {
					$s = mb_strtolower( $s, 'UTF-8' );
				} else {
					$s = strtolower( $s );
				}
				$s = preg_replace( '/[^a-z0-9\s\-\_\'\":]+/u', ' ', $s );
				$s = preg_replace( '/\s+/u', ' ', $s );
				return trim( $s );
			}
		}
		// Compose new values and recompute normalized
		$title  = ($field === 'title')  ? $value : $row['title'];
		$author = ($field === 'author') ? $value : $row['author'];

		$norm_title  = politeia__normalize_text( $title );
		$norm_author = politeia__normalize_text( $author );

		// Avoid duplicate pending for same user+normalized values (merge by deleting the OTHER duplicate)
		$dup_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tbl}
				  WHERE user_id=%d AND status='pending' AND normalized_title=%s AND normalized_author=%s AND id<>%d
				  LIMIT 1",
				$user_id, $norm_title, $norm_author, $id
			)
		);
		if ( $dup_id ) {
			$wpdb->delete( $tbl, [ 'id' => $dup_id, 'user_id' => $user_id ], [ '%d', '%d' ] );
		}

		$wpdb->update(
			$tbl,
			[
				'title'             => $title,
				'author'            => $author,
				'normalized_title'  => $norm_title,
				'normalized_author' => $norm_author,
				'updated_at'        => current_time( 'mysql', 1 ),
			],
			[ 'id' => $id, 'user_id' => $user_id ],
			[ '%s','%s','%s','%s','%s' ],
			[ '%d','%d' ]
		);

		wp_send_json_success([
			'id'     => $id,
			'title'  => $title,
			'author' => $author,
		]);

	} catch (Throwable $e) {
		error_log('[politeia_confirm_update_field] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
		wp_send_json_error( WP_DEBUG ? $e->getMessage() : 'internal_error' );
	}
}
add_action('wp_ajax_politeia_confirm_update_field',        'politeia_confirm_update_field_ajax');
add_action('wp_ajax_nopriv_politeia_confirm_update_field', 'politeia_confirm_update_field_ajax'); // si no quieres visitantes, coméntala
