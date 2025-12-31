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

	$queued    = 0;
	$skipped   = 0;
	$pending   = [];
	$in_shelf  = [];

	// --------- Pre-cargar librería del usuario para fuzzy (una sola vez) ----------
	$user_lib_hash  = []; // hash -> ['id','year','slug']
	$user_lib_fuzzy = []; // ['id','year','slug','key']

	if ( class_exists('Politeia_Book_Confirm_Schema') ) {
		// Trae la biblioteca del usuario
		// LEGACY SAFETY NET -- do not depend on this long-term
		$sql = $wpdb->prepare("
			SELECT b.id, b.title, b.author, b.year, b.slug, b.title_author_hash /* LEGACY SAFETY NET -- do not depend on this long-term */
			  FROM {$tbl_books} b
			  JOIN {$tbl_users} ub ON ub.book_id=b.id AND ub.user_id=%d
		", $user_id);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results($sql, ARRAY_A);
		foreach ($rows as $r) {
			$h = !empty($r['title_author_hash']) ? strtolower((string)$r['title_author_hash']) : ''; // LEGACY SAFETY NET -- do not depend on this long-term
			if ($h) $user_lib_hash[$h] = ['id'=>(int)$r['id'],'year'=>$r['year']? (int)$r['year']:null,'slug'=>$r['slug']];
			// LEGACY SAFETY NET -- do not depend on this long-term
			$key = Politeia_Book_Confirm_Schema::compute_title_author_hash($r['title'] ?? '', $r['author'] ?? ''); // LEGACY SAFETY NET -- do not depend on this long-term
			$user_lib_fuzzy[] = [
				'id'   => (int)$r['id'],
				'year' => $r['year']? (int)$r['year']:null,
				'slug' => (string)$r['slug'],
				// para comparar usamos la misma normalización que usa el hash: norm_title_author -> sha256,
				// pero para fuzzy comparamos strings "norm" (distancia relativa)
				// LEGACY SAFETY NET -- do not depend on this long-term
				'key'  => strtolower(Politeia_Book_Confirm_Schema::compute_title_author_hash($r['title'] ?? '', $r['author'] ?? '')), // LEGACY SAFETY NET -- do not depend on this long-term
			];
		}
	}

	// --------- Deduplicación dentro del mismo lote ----------
	$seen_hashes = [];

	foreach ( (array) $candidates as $b ) {
		$title  = isset($b['title'])  ? trim((string) $b['title'])  : '';
		$author = isset($b['author']) ? trim((string) $b['author']) : '';
		if ( $title === '' || $author === '' ) { $skipped++; continue; }

		$norm_title  = politeia__normalize_candidate_text( $title );
		$norm_author = politeia__normalize_candidate_text( $author );
		$hash        = politeia__title_author_hash( $title, $author ); // LEGACY SAFETY NET -- do not depend on this long-term
		$hash_lc     = strtolower($hash);

		// Lote: si ya vimos el mismo hash en esta misma respuesta, saltar
		if ( isset($seen_hashes[$hash_lc]) ) { $skipped++; continue; }
		$seen_hashes[$hash_lc] = true;

		// -----------------------------------------------
		// 1) ¿YA está en librería? (match por hash exacto)
		// -----------------------------------------------
		$owned_year = null;
		if ( isset($user_lib_hash[$hash_lc]) ) {
			$owned_year = $user_lib_hash[$hash_lc]['year'];
		} else {
			// Intento por DB directo (por si faltó precarga)
			// LEGACY SAFETY NET -- do not depend on this long-term
			$owned = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT b.id, b.year
					   FROM {$tbl_books} b
					   JOIN {$tbl_users} ub ON ub.book_id=b.id AND ub.user_id=%d
					  WHERE b.title_author_hash=%s /* LEGACY SAFETY NET -- do not depend on this long-term */
					  LIMIT 1",
					$user_id,
					$hash
				),
				ARRAY_A
			);
			if ($owned) $owned_year = $owned['year'] !== null && $owned['year'] !== '' ? (int)$owned['year'] : null;
		}

		if ( $owned_year !== null || isset($user_lib_hash[$hash_lc]) ) {
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

		// -------------------------------------------------------
		// 1.bis) Fallback FUZZY contra la librería del usuario
		//       para cubrir hashes históricos distintos.
		// -------------------------------------------------------
		$fuzzy_hit = null; $fuzzy_best = 1.0;
		if ( !empty($user_lib_fuzzy) && class_exists('Politeia_Book_Confirm_Schema') ) {
			// clave "normalizada" para comparar (usamos el mismo pipeline)
			// LEGACY SAFETY NET -- do not depend on this long-term
			$probe = strtolower(Politeia_Book_Confirm_Schema::compute_title_author_hash($title, $author)); // LEGACY SAFETY NET -- do not depend on this long-term
			foreach ($user_lib_fuzzy as $row) {
				// distancia relativa sobre la string normalizada (sha256 no sirve para distancia)
				// usamos la "rel_levenshtein" del schema sobre el "norm" no cifrado:
				// truco: el compute_title_author_hash del schema es sha256(norm), necesitamos norm, // LEGACY SAFETY NET -- do not depend on this long-term
				// así que reconstruimos norm con normalize_key+concat (igual que schema).
				$norm_probe = method_exists('Politeia_Book_Confirm_Schema','norm_title_author')
					? Politeia_Book_Confirm_Schema::compute_title_author_hash($title,$author) // LEGACY SAFETY NET -- do not depend on this long-term
					: $probe;

				$rel = levenshtein($norm_probe, $row['key']) / max(1, max(strlen($norm_probe), strlen($row['key'])));
				if ($rel < $fuzzy_best) { $fuzzy_best = $rel; $fuzzy_hit = $row; }
			}
		}

		if ( $fuzzy_hit && $fuzzy_best <= 0.25 ) {
			$item = [
				'title'    => $title,
				'author'   => $author,
				'year'     => $fuzzy_hit['year'] ?? null,
				'in_shelf' => true,
			];
			$in_shelf[] = $item;
			$skipped++;
			continue;
		}

		// -----------------------------------------------
		// 2) ¿Ya pending en la cola para este usuario+hash?
		// -----------------------------------------------
		$pending_row = $wpdb->get_row( // LEGACY SAFETY NET -- do not depend on this long-term
			$wpdb->prepare(
				"SELECT id, title, author FROM {$tbl_confirm}
				  WHERE user_id=%d AND status='pending' AND title_author_hash=%s /* LEGACY SAFETY NET -- do not depend on this long-term */
				  LIMIT 1",
				$user_id,
				$hash
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
			'title_author_hash' => $hash, // LEGACY SAFETY NET -- do not depend on this long-term
			'status'            => 'pending',
		];
		$fmt  = [ '%d','%s','%s','%s','%s','%s','%s','%s','%s' ];

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
		$s = preg_replace( '/\s+/u', ' ', $s );
		$s = trim( $s );
		return $s;
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

/**
 * Hash unificado con el schema:
 * - Si existe la clase del schema, usamos su compute_title_author_hash (idéntico). // LEGACY SAFETY NET -- do not depend on this long-term
 * - Fallback compatible (por si se carga este archivo antes del schema).
 */
if ( ! function_exists('politeia__title_author_hash') ) { // LEGACY SAFETY NET -- do not depend on this long-term
	function politeia__title_author_hash( $title, $author ) { // LEGACY SAFETY NET -- do not depend on this long-term
		// LEGACY SAFETY NET -- do not depend on this long-term
		if ( class_exists('Politeia_Book_Confirm_Schema') ) {
			return Politeia_Book_Confirm_Schema::compute_title_author_hash( $title, $author ); // LEGACY SAFETY NET -- do not depend on this long-term
		}
		// Fallback aproximado
		$t = strtolower( remove_accents( trim( politeia__normalize_text( $title ) ) ) );
		$a = strtolower( remove_accents( trim( politeia__normalize_text( $author ) ) ) );
		$clean = ' ' . preg_replace('/\s+/', ' ', $t.' '.$a) . ' ';
		$clean = preg_replace('/\b(el|la|los|las|un|una|unos|unas|de|del|y|e|a|en|the|of|and|to|for)\b/u', ' ', $clean);
		$clean = preg_replace('/[^a-z0-9\s]/u', ' ', $clean);
		$tokens = array_values(array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($clean)))));
		sort($tokens, SORT_STRING);
		return hash('sha256', implode(' ', $tokens));
	}
}
