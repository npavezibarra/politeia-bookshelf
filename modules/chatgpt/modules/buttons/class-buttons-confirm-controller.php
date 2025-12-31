<?php
// modules/buttons/class-buttons-confirm-controller.php
if ( ! defined('ABSPATH') ) exit;

/**
 * AJAX controller for confirming books from wp_politeia_book_confirm.
 *
 * Endpoints:
 *  - politeia_buttons_confirm       (confirma 1..n items)
 *  - politeia_buttons_confirm_all   (confirma todos los que recibe)
 *
 * Flujo por ítem:
 *  1) Asegura/crea el libro en wp_politeia_books (usando hash/match interno)
 *  2) Enlaza al usuario en wp_politeia_user_books (evita duplicados)
 *  3) (Opcional) si viene "year" y el libro no lo tenía, lo actualiza
 *  4) Elimina el registro confirmado desde wp_politeia_book_confirm
 *
 * Entrada esperada (POST):
 *  - nonce
 *  - items: JSON array de objetos, cada uno idealmente con:
 *      {
 *        "id":    <int id en wp_politeia_book_confirm>,
 *        "title": <string>,
 *        "author":<string>,
 *        "year":  <int|null>
 *      }
 *
 * Salida JSON:
 *  success: true/false
 *  data: {
 *    confirmed: <int>,
 *    confirmed_ids: [<int>...],   // ids borrados de wp_politeia_book_confirm
 *    errors: [ {item:index, message:string} ... ] (opcional)
 *  }
 */

if ( ! class_exists('Politeia_Buttons_Confirm_Controller') ) :

class Politeia_Buttons_Confirm_Controller {

	/** Ensure dependencies are loaded. */
	protected static function ensure_deps() {
		// Cargamos DB handler si no está.
		if ( ! function_exists( 'prs_promote_candidate_to_canonical' ) ) {
			$plugin_root = dirname( __FILE__, 5 );
			$helpers = $plugin_root . '/modules/reading/includes/helpers.php';
			if ( file_exists( $helpers ) ) {
				require_once $helpers;
			}
		}

		if ( ! function_exists( 'prs_promote_candidate_to_canonical' ) ) {
			throw new \Exception( 'Promotion helper not available.' );
		}
	}

	/** Parse and sanitize incoming items JSON. */
	protected static function parse_items_from_request() {
		$items_raw = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
		$items     = json_decode($items_raw, true);
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array($items) ) {
			throw new \Exception('Invalid items payload.');
		}

		$clean = [];
		foreach ( $items as $i => $it ) {
			$id     = isset($it['id'])     ? (int) $it['id'] : 0;
			$title  = isset($it['title'])  ? sanitize_text_field( (string) $it['title'] )  : '';
			$author = isset($it['author']) ? sanitize_text_field( (string) $it['author'] ) : '';
			$year   = null;

			if ( isset($it['year']) && $it['year'] !== '' && $it['year'] !== null ) {
				$y = (int) $it['year'];
				// aceptamos años de 3-4 dígitos razonables
				if ( $y >= 800 && $y <= 2100 ) $year = $y;
			}

			if ( $title === '' || $author === '' ) {
				// Saltamos silenciosamente entradas inválidas.
				continue;
			}

			$clean[] = compact('id','title','author','year');
		}
		return $clean;
	}

	/** Core processing: confirm array of items for current user. */
	protected static function confirm_items( array $items ) {
		global $wpdb;

		self::ensure_deps();

		$user_id        = get_current_user_id();
		$confirmed_ids  = [];
		$confirmed_cnt  = 0;
		$errors         = [];

		foreach ( $items as $idx => $it ) {
			$year   = $it['year'];
			$id_cnf = (int) $it['id'];

			// 1) Promover candidato a canónico (con re-check interno)
			if ( $id_cnf <= 0 ) {
				$errors[] = [ 'item' => $idx, 'message' => 'Missing candidate ID.' ];
				continue;
			}

			$promotion = prs_promote_candidate_to_canonical( $id_cnf, $user_id, $year );
			if ( is_wp_error( $promotion ) ) {
				$errors[] = [ 'item' => $idx, 'message' => $promotion->get_error_message() ];
				continue;
			}

			$confirmed_cnt++;

			// 3) Marcar para eliminación de la cola (si vino con id válido)
			$confirmed_ids[] = $id_cnf;
		}

		// 4) Eliminar confirmados de la cola
		if ( ! empty( $confirmed_ids ) ) {
			$tbl_confirm   = $wpdb->prefix . 'politeia_book_confirm';
			$ids_to_delete = array_map( 'intval', $confirmed_ids );

			$in = implode(',', array_fill(0, count($ids_to_delete), '%d'));
			// Construimos el array de args: todos los IDs + user_id al final
			$args = array_merge( $ids_to_delete, [ get_current_user_id() ] );

			// PREPARE seguro con número variable de %d + user_id
			$query = $wpdb->prepare(
				"DELETE FROM {$tbl_confirm} WHERE id IN ($in) AND user_id=%d",
				$args
			);
			$wpdb->query( $query );
		}

		return [
			'confirmed'     => $confirmed_cnt,
			'confirmed_ids' => $confirmed_ids,
			'errors'        => $errors,
		];
	}

	/** Public helper for server-side confirmation flows. */
	public static function confirm_items_direct( array $items ) {
		return self::confirm_items( $items );
	}

	/* ---------------- AJAX: confirmar (1..n) ---------------- */
	public static function ajax_confirm() {
		try {
			check_ajax_referer( 'politeia-chatgpt-nonce', 'nonce' );
			if ( ! is_user_logged_in() ) {
				throw new \Exception('Unauthorized.');
			}

			$items = self::parse_items_from_request();
			if ( empty( $items ) ) {
				wp_send_json_success( [ 'confirmed' => 0, 'confirmed_ids' => [], 'errors' => [] ] );
			}

			$result = self::confirm_items( $items );
			wp_send_json_success( $result );

		} catch ( \Throwable $e ) {
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log('[politeia_buttons_confirm] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
			}
			wp_send_json_error( $e->getMessage() );
		}
	}

	/* ---------------- AJAX: confirmar todos (los que recibe) ---------------- */
	public static function ajax_confirm_all() {
		try {
			check_ajax_referer( 'politeia-chatgpt-nonce', 'nonce' );
			if ( ! is_user_logged_in() ) {
				throw new \Exception('Unauthorized.');
			}

			$items = self::parse_items_from_request();
			if ( empty( $items ) ) {
				wp_send_json_success( [ 'confirmed' => 0, 'confirmed_ids' => [], 'errors' => [] ] );
			}

			$result = self::confirm_items( $items );
			wp_send_json_success( $result );

		} catch ( \Throwable $e ) {
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log('[politeia_buttons_confirm_all] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
			}
			wp_send_json_error( $e->getMessage() );
		}
	}
}

/* Hooks */
add_action( 'wp_ajax_politeia_buttons_confirm',      [ 'Politeia_Buttons_Confirm_Controller', 'ajax_confirm' ] );
add_action( 'wp_ajax_politeia_buttons_confirm_all',  [ 'Politeia_Buttons_Confirm_Controller', 'ajax_confirm_all' ] );

endif; // class_exists
