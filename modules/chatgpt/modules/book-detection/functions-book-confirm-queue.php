<?php
// modules/book-detection/functions-book-confirm-queue.php
/**
 * Politeia ChatGPT – Book confirmation queue helpers
 *
 * Prepara / encola candidatos en wp_politeia_book_confirm:
 * - Calcula hash determinista (sha256) de (title,author) con el MISMO algoritmo del schema.
 * - Si el usuario YA tiene el libro (books + user_books) => NO encola; devuelve en in_shelf[] (efímero).
 * - Si NO lo tiene, lo encola con status='pending' evitando duplicados por (user_id,hash,status).
 *
 * Firma compatible con dos formas de llamada:
 *   A) politeia_chatgpt_queue_confirm_items($user_id, $candidates, $input_type, $source_note)
 *   B) politeia_chatgpt_queue_confirm_items($candidates, ['user_id'=>..,'input_type'=>..,'source_note'=>..,'raw_response'=>..])
 *
 * @return array {
 *   @type int     $queued     Filas insertadas en cola (pending).
 *   @type int     $skipped    Saltadas (vacías, ya en librería, o ya pending).
 *   @type array   $pending    Ítems confirmables insertados en DB:
 *                             [ { id:int,title,author,year:int|null } ]
 *   @type array   $in_shelf   Ítems EFÍMEROS (no insertados) ya en la biblioteca:
 *                             [ { title,author,year:int|null,in_shelf:true } ]
 * }
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! function_exists('politeia_chatgpt_queue_confirm_items') ) :

function politeia_chatgpt_queue_confirm_items( $arg1, $arg2 = null, $arg3 = null, $arg4 = '' ) {
	global $wpdb;

	// Asegura tabla/índices (idempotente)
	if ( class_exists('Politeia_Book_Confirm_Schema') ) {
		Politeia_Book_Confirm_Schema::ensure();
	}

	// --------- Parseo de parámetros (firma A o B) ----------
	$user_id     = 0;
	$candidates  = [];
	$input_type  = 'text';
	$source_note = '';
	$raw_payload = null;

	if ( is_array($arg1) && ( is_array($arg2) || $arg2 === null ) ) {
		// Firma B: ($candidates, $meta)
		$candidates  = (array) $arg1;
		$meta        = is_array($arg2) ? $arg2 : [];
		$user_id     = isset($meta['user_id'])      ? (int) $meta['user_id']      : get_current_user_id();
		$input_type  = isset($meta['input_type'])   ? sanitize_text_field($meta['input_type']) : 'text';
		$source_note = isset($meta['source_note'])  ? sanitize_text_field($meta['source_note']) : '';
		if ( array_key_exists('raw_response', $meta) ) {
			$raw_payload = is_string($meta['raw_response']) ? $meta['raw_response'] : wp_json_encode($meta['raw_response']);
		}
	} else {
		// Firma A: ($user_id, $candidates, $input_type, $source_note)
		$user_id     = (int) $arg1;
		$candidates  = (array) $arg2;
		$input_type  = $arg3 ? sanitize_text_field($arg3) : 'text';
		$source_note = $arg4 ? sanitize_text_field($arg4) : '';
	}

	$user_id = $user_id ?: (int) get_current_user_id();

	$tbl_books   = $wpdb->prefix . 'politeia_books';
	$tbl_users   = $wpdb->prefix . 'politeia_user_books';
	$tbl_confirm = $wpdb->prefix . 'politeia_book_confirm';
	$tbl_authors = $wpdb->prefix . 'politeia_authors';
	$tbl_pivot   = $wpdb->prefix . 'politeia_book_authors';
	$tbl_slugs   = $wpdb->prefix . 'politeia_book_slugs';

	$queued    = 0;
	$skipped   = 0;
	$pending   = [];
	$in_shelf  = [];

	// --------- Pre-cargar librería del usuario (una sola vez) ----------
	$user_lib_slug = []; // slug -> ['id','year','slug']
	$user_lib_key  = []; // normalized title|authors -> ['id','year','slug']

	if ( class_exists('Politeia_Book_Confirm_Schema') ) {
		// Trae la biblioteca del usuario
		$slugs_table_exists = (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1',
				$tbl_slugs
			)
		);
		$slug_select = $slugs_table_exists ? 'COALESCE(s.slug, b.slug) AS slug' : 'b.slug AS slug';
		$slug_join   = $slugs_table_exists ? "LEFT JOIN {$tbl_slugs} s ON s.book_id = b.id AND s.is_primary = 1" : '';

		$sql = $wpdb->prepare("
			SELECT b.id, b.title, b.year, {$slug_select},
			       (
			       		SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
			       		FROM {$tbl_pivot} ba
			       		LEFT JOIN {$tbl_authors} a ON a.id = ba.author_id
			       		WHERE ba.book_id = b.id
			       ) AS authors
			  FROM {$tbl_books} b
			  JOIN {$tbl_users} ub ON ub.book_id=b.id AND ub.user_id=%d
			  {$slug_join}
		", $user_id);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results($sql, ARRAY_A);
		foreach ($rows as $r) {
			$slug = isset($r['slug']) ? (string)$r['slug'] : '';
			if ($slug) {
				$user_lib_slug[$slug] = [
					'id'   => (int)$r['id'],
					'year' => $r['year']? (int)$r['year']:null,
					'slug' => $slug,
				];
			}

			$norm_title  = politeia__normalize_candidate_text( $r['title'] ?? '' );
			$norm_author = politeia__normalize_candidate_text( $r['authors'] ?? '' );
			if ( $norm_title !== '' && $norm_author !== '' ) {
				$key = strtolower( $norm_title . '|' . $norm_author );
				$user_lib_key[$key] = [
					'id'   => (int)$r['id'],
					'year' => $r['year']? (int)$r['year']:null,
					'slug' => $slug,
				];
			}
		}
	}

	// --------- Deduplicación dentro del mismo lote ----------
	$seen_keys = [];

	foreach ( (array) $candidates as $b ) {
		$title  = isset($b['title'])  ? trim((string) $b['title'])  : '';
		$author = isset($b['author']) ? trim((string) $b['author']) : '';
		if ( $title === '' || $author === '' ) { $skipped++; continue; }

		$norm_title  = politeia__normalize_candidate_text( $title );
		$norm_author = politeia__normalize_candidate_text( $author );
		if ( '' === $norm_title ) {
			$norm_title = strtolower( trim( $title ) );
		}
		if ( '' === $norm_author ) {
			$norm_author = strtolower( trim( $author ) );
		}
		$key         = strtolower( $norm_title . '|' . $norm_author );

		// Lote: si ya vimos el mismo key en esta misma respuesta, saltar
		if ( isset($seen_keys[$key]) ) { $skipped++; continue; }
		$seen_keys[$key] = true;

		// -----------------------------------------------
		// 1) ¿YA está en librería? (match por slug exacto, fallback por título+autor)
		// -----------------------------------------------
		$owned_year = null;
		$base_slug = sanitize_title( $title );
		$candidate_slug = $base_slug;
		$candidate_slug_year = ( $base_slug && isset( $b['year'] ) && $b['year'] ) ? $base_slug . '-' . (int) $b['year'] : '';

		if ( $candidate_slug && isset($user_lib_slug[$candidate_slug]) ) {
			$owned_year = $user_lib_slug[$candidate_slug]['year'];
		} elseif ( $candidate_slug_year && isset($user_lib_slug[$candidate_slug_year]) ) {
			$owned_year = $user_lib_slug[$candidate_slug_year]['year'];
		} elseif ( isset($user_lib_key[$key]) ) {
			$owned_year = $user_lib_key[$key]['year'];
		} elseif ( $candidate_slug || $candidate_slug_year ) {
			$slug_checks = array_filter( array( $candidate_slug, $candidate_slug_year ) );
			$placeholders = implode( ', ', array_fill( 0, count( $slug_checks ), '%s' ) );
			$owned = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT b.id, b.year
					   FROM {$tbl_books} b
					   JOIN {$tbl_users} ub ON ub.book_id=b.id AND ub.user_id=%d
					  WHERE b.slug IN ({$placeholders})
					  LIMIT 1",
					array_merge( array( $user_id ), $slug_checks )
				),
				ARRAY_A
			);
			if ($owned) {
				$owned_year = $owned['year'] !== null && $owned['year'] !== '' ? (int)$owned['year'] : null;
			}
		}

		if ( $owned_year !== null ) {
			// EFÍMERO (no insertamos en cola)
			$item = [
				'title'    => $title,
				'author'   => $author,
				'year'     => $owned_year,
				'in_shelf' => true,
			];
			$in_shelf[] = $item;
			$skipped++;
			continue;
		}

		// -----------------------------------------------
		// 2) ¿Ya pending en la cola para este usuario+texto normalizado?
		// -----------------------------------------------
		$pending_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, title, author FROM {$tbl_confirm}
				  WHERE user_id=%d AND status='pending' AND normalized_title=%s AND normalized_author=%s
				  LIMIT 1",
				$user_id,
				$norm_title,
				$norm_author
			),
			ARRAY_A
		);

		if ( $pending_row ) {
			$pending[] = [
				'id'     => (int) $pending_row['id'],
				'title'  => (string) $pending_row['title'],
				'author' => (string) $pending_row['author'],
				'year'   => null,
			];
			$skipped++;
			continue;
		}

		// -----------------------------------------------
		// 3) Insertar en cola (status='pending')
		// -----------------------------------------------
		$data = [
			'user_id'           => $user_id,
			'input_type'        => $input_type,
			'source_note'       => $source_note,
			'title'             => $title,
			'author'            => $author,
			'normalized_title'  => $norm_title,
			'normalized_author' => $norm_author,
			'status'            => 'pending',
		];
		$fmt  = [ '%d','%s','%s','%s','%s','%s','%s','%s' ];

		if ( isset($b['isbn']) )         { $data['external_isbn']         = sanitize_text_field((string)$b['isbn']);         $fmt[]='%s'; }
		if ( isset($b['source']) )       { $data['external_source']       = sanitize_text_field((string)$b['source']);       $fmt[]='%s'; }
		if ( isset($b['score']) )        { $data['external_score']        = (float) $b['score'];                              $fmt[]='%f'; }
		if ( isset($b['method']) )       { $data['match_method']          = sanitize_text_field((string)$b['method']);       $fmt[]='%s'; }
		if ( isset($b['matched_book_id'])){ $data['matched_book_id']      = (int) $b['matched_book_id'];                      $fmt[]='%d'; }
		if ( isset($b['cover_url']) )    { $data['external_cover_url']    = esc_url_raw((string)$b['cover_url']);            $fmt[]='%s'; }
		if ( isset($b['cover_source']) ) { $data['external_cover_source'] = sanitize_text_field((string)$b['cover_source']); $fmt[]='%s'; }

		$raw_candidate = array(
			'original_input' => array(
				'title'  => $title,
				'author' => $author,
			),
			'normalized'     => array(
				'title'  => $norm_title,
				'author' => $norm_author,
			),
			'external'       => array(
				'source' => isset( $b['source'] ) ? (string) $b['source'] : null,
				'isbn'   => isset( $b['isbn'] ) ? (string) $b['isbn'] : null,
				'id'     => isset( $b['external_id'] ) ? (string) $b['external_id'] : ( isset( $b['id'] ) ? (string) $b['id'] : null ),
				'score'  => isset( $b['score'] ) ? (float) $b['score'] : null,
			),
		);

		if ( isset( $b['year'] ) && $b['year'] !== null && $b['year'] !== '' ) {
			$raw_candidate['original_input']['year'] = (int) $b['year'];
		}

		if ( isset( $b['raw_payload'] ) ) {
			$raw_candidate['external']['raw_payload'] = $b['raw_payload'];
		}

		if ( isset( $b['raw_response'] ) ) {
			$raw_candidate['external']['raw_response'] = $b['raw_response'];
		}

		if ( $raw_payload !== null ) {
			$raw_candidate['raw_payload'] = $raw_payload;
		}

		$data['raw_response'] = wp_json_encode( $raw_candidate );
		$fmt[] = '%s';

		$ok = $wpdb->insert( $tbl_confirm, $data, $fmt );
		if ( ! $ok ) { $skipped++; continue; }

		$pending[] = [
			'id'     => (int) $wpdb->insert_id,
			'title'  => $title,
			'author' => $author,
			'year'   => null,
		];
		$queued++;
	}

	// Opcional defensivo: empuja efímeros al transient (para sobrevivir al render)
	if ( !empty($in_shelf) ) {
		$key   = 'pol_confirm_ephemeral_' . (int) $user_id;
		$prev  = get_transient($key);
		$prev  = is_array($prev) ? $prev : [];
		$store = array_merge($prev, $in_shelf);
		set_transient($key, $store, 15 * MINUTE_IN_SECONDS);
	}

	$result = [
		'queued'   => $queued,
		'skipped'  => $skipped,
		'pending'  => $pending,
		'in_shelf' => $in_shelf,
	];

	return apply_filters( 'politeia_chatgpt_after_queue_items', $result, $user_id, $candidates );
}

endif; // function_exists

/* =========================== Helpers =========================== */
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

if ( ! function_exists('politeia__normalize_candidate_text') ) {
	function politeia__normalize_candidate_text( $s ) {
		if ( class_exists('Politeia_Book_Confirm_Schema') ) {
			// Mirror schema normalization for temporary candidate fields.
			$normalized = Politeia_Book_Confirm_Schema::normalize_pair_key( $s, '' );
			return trim( (string) $normalized );
		}

		$s = (string) $s;
		$s = wp_strip_all_tags( $s );
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$s = remove_accents( $s );
		$s = strtolower( $s );

		$stop = array( ' el ', ' la ', ' los ', ' las ', ' un ', ' una ', ' unos ', ' unas ', ' de ', ' del ', ' y ', ' e ', ' a ', ' en ', ' the ', ' of ', ' and ', ' to ', ' for ' );
		$s = ' ' . preg_replace( '/\s+/', ' ', $s ) . ' ';
		foreach ( $stop as $st ) {
			$s = str_replace( $st, ' ', $s );
		}

		$s = preg_replace( '/[^a-z0-9\s]/', ' ', $s );
		$tokens = array_filter( explode( ' ', trim( preg_replace( '/\s+/', ' ', $s ) ) ) );
		sort( $tokens, SORT_STRING );
		return implode( ' ', $tokens );
	}
}
