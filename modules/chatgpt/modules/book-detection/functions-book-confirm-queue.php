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
        $user_lib_hash  = []; // hash -> ['id','year','slug','authors']
        $user_lib_fuzzy = []; // ['id','year','slug','key','authors']

	if ( class_exists('Politeia_Book_Confirm_Schema') ) {
		// Trae la biblioteca del usuario
		$sql = $wpdb->prepare("
			SELECT b.id, b.title, b.author, b.year, b.slug, b.title_author_hash
			  FROM {$tbl_books} b
			  JOIN {$tbl_users} ub ON ub.book_id=b.id AND ub.user_id=%d
		", $user_id);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results($sql, ARRAY_A);
                foreach ($rows as $r) {
                        $authors_for_hash = politeia__extract_authors_array( $r['author'] ?? [] );
                        $h = !empty($r['title_author_hash']) ? strtolower((string)$r['title_author_hash']) : '';
                        if ($h) {
                                $user_lib_hash[$h] = [
                                        'id'      => (int) $r['id'],
                                        'year'    => $r['year'] ? (int) $r['year'] : null,
                                        'slug'    => (string) $r['slug'],
                                        'authors' => $authors_for_hash,
                                ];
                        }

                        if ( method_exists( 'Politeia_Book_Confirm_Schema', 'normalize_pair_key' ) ) {
                                $key = strtolower( Politeia_Book_Confirm_Schema::normalize_pair_key( $r['title'] ?? '', $authors_for_hash ) );
                        } else {
                                $key = strtolower( Politeia_Book_Confirm_Schema::compute_title_author_hash( $r['title'] ?? '', $authors_for_hash ) );
                        }

                        $user_lib_fuzzy[] = [
                                'id'      => (int) $r['id'],
                                'year'    => $r['year'] ? (int) $r['year'] : null,
                                'slug'    => (string) $r['slug'],
                                'authors' => $authors_for_hash,
                                'key'     => $key,
                        ];
                }
        }

	// --------- Deduplicación dentro del mismo lote ----------
	$seen_hashes = [];

        foreach ( (array) $candidates as $b ) {
                $title   = isset($b['title']) ? trim((string) $b['title']) : '';
                $authors = [];

                if ( isset( $b['authors'] ) ) {
                        $authors = politeia__extract_authors_array( $b['authors'] );
                } elseif ( isset( $b['author'] ) ) {
                        $authors = politeia__extract_authors_array( $b['author'] );
                }

                if ( $title === '' || empty( $authors ) ) { $skipped++; continue; }

                $author_display = implode( ', ', $authors );

                $norm_title = politeia__normalize_text( $title );
                $norm_parts = [];
                foreach ( $authors as $author_name ) {
                        $clean = politeia__normalize_text( $author_name );
                        if ( '' !== $clean ) {
                                $norm_parts[] = $clean;
                        }
                }
                $norm_author = ! empty( $norm_parts ) ? implode( ', ', $norm_parts ) : null;
                $hash        = politeia__title_author_hash( $title, $authors );
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
			$owned = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT b.id, b.year
					   FROM {$tbl_books} b
					   JOIN {$tbl_users} ub ON ub.book_id=b.id AND ub.user_id=%d
					  WHERE b.title_author_hash=%s
					  LIMIT 1",
					$user_id,
					$hash
				),
				ARRAY_A
			);
			if ($owned) $owned_year = $owned['year'] !== null && $owned['year'] !== '' ? (int)$owned['year'] : null;
		}

                if ( $owned_year !== null || isset($user_lib_hash[$hash_lc]) ) {
                        $library_entry = isset( $user_lib_hash[ $hash_lc ] ) ? $user_lib_hash[ $hash_lc ] : null;
                        $authors_for_response = $library_entry && ! empty( $library_entry['authors'] ) ? $library_entry['authors'] : $authors;
                        $author_display       = implode( ', ', $authors_for_response );
                        // EFÍMERO (no insertamos en cola)
                        $item = [
                                'title'    => $title,
                                'author'   => $author_display,
                                'authors'  => $authors_for_response,
                                'year'     => $owned_year,
                                'in_shelf' => true,
                        ];
                        if ( $library_entry && isset( $library_entry['slug'] ) ) {
                                $item['slug'] = $library_entry['slug'];
                        }
                        if ( $library_entry && isset( $library_entry['id'] ) ) {
                                $item['book_id'] = $library_entry['id'];
                        }
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
                        $probe = strtolower(Politeia_Book_Confirm_Schema::compute_title_author_hash($title, $authors));
                        foreach ($user_lib_fuzzy as $row) {
                                $norm_probe = method_exists('Politeia_Book_Confirm_Schema','normalize_pair_key')
                                        ? Politeia_Book_Confirm_Schema::normalize_pair_key($title, $authors)
                                        : $probe;

                                $rel = levenshtein($norm_probe, $row['key']) / max(1, max(strlen($norm_probe), strlen($row['key'])));
                                if ($rel < $fuzzy_best) { $fuzzy_best = $rel; $fuzzy_hit = $row; }
                        }
                }

                if ( $fuzzy_hit && $fuzzy_best <= 0.25 ) {
                        $fuzzy_authors = ! empty( $fuzzy_hit['authors'] ) ? (array) $fuzzy_hit['authors'] : $authors;
                        $author_display = implode( ', ', $fuzzy_authors );
                        $item = [
                                'title'    => $title,
                                'author'   => $author_display,
                                'authors'  => $fuzzy_authors,
                                'year'     => $fuzzy_hit['year'] ?? null,
                                'in_shelf' => true,
                        ];
                        if ( isset( $fuzzy_hit['slug'] ) ) {
                                $item['slug'] = $fuzzy_hit['slug'];
                        }
                        if ( isset( $fuzzy_hit['id'] ) ) {
                                $item['book_id'] = $fuzzy_hit['id'];
                        }
                        $in_shelf[] = $item;
                        $skipped++;
                        continue;
                }

		// -----------------------------------------------
		// 2) ¿Ya pending en la cola para este usuario+hash?
		// -----------------------------------------------
		$pending_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, title, author FROM {$tbl_confirm}
				  WHERE user_id=%d AND status='pending' AND title_author_hash=%s
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
                                'authors'=> politeia__extract_authors_array( $pending_row['author'] ?? [] ),
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
                        'author'            => $author_display,
                        'normalized_title'  => $norm_title,
                        'normalized_author' => $norm_author !== null ? $norm_author : '',
                        'title_author_hash' => $hash,
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
		if ( $raw_payload !== null )     { $data['raw_response']          = $raw_payload;                                     $fmt[]='%s'; }

		$ok = $wpdb->insert( $tbl_confirm, $data, $fmt );
		if ( ! $ok ) { $skipped++; continue; }

                $pending[] = [
                        'id'     => (int) $wpdb->insert_id,
                        'title'  => $title,
                        'author' => $author_display,
                        'authors'=> $authors,
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

if ( ! function_exists( 'politeia__extract_authors_array' ) ) {
        function politeia__extract_authors_array( $authors ) {
                if ( is_array( $authors ) ) {
                        $candidates = $authors;
                } elseif ( is_string( $authors ) ) {
                        $normalized = str_replace( [ ' & ', ' and ', ' y ' ], ',', $authors );
                        $candidates = preg_split( '/[,;\|]+/u', (string) $normalized );
                } else {
                        $candidates = [];
                }

                $clean = [];
                foreach ( $candidates as $entry ) {
                        $entry = trim( wp_strip_all_tags( (string) $entry ) );
                        if ( '' !== $entry ) {
                                $clean[] = $entry;
                        }
                }

                return array_values( array_unique( $clean ) );
        }
}

/**
 * Hash unificado con el schema:
 * - Si existe la clase del schema, usamos su compute_title_author_hash (idéntico).
 * - Fallback compatible (por si se carga este archivo antes del schema).
 */
if ( ! function_exists('politeia__title_author_hash') ) {
        function politeia__title_author_hash( $title, $authors ) {
                if ( class_exists('Politeia_Book_Confirm_Schema') ) {
                        return Politeia_Book_Confirm_Schema::compute_title_author_hash( $title, $authors );
                }

                $normalize = static function ( $value ) {
                        $value = strtolower( remove_accents( trim( politeia__normalize_text( $value ) ) ) );
                        $value = ' ' . preg_replace( '/\s+/', ' ', $value ) . ' ';
                        $value = preg_replace( '/\b(el|la|los|las|un|una|unos|unas|de|del|y|e|a|en|the|of|and|to|for)\b/u', ' ', $value );
                        $value = preg_replace( '/[^a-z0-9\s]/u', ' ', $value );
                        $value = preg_replace( '/\s+/', ' ', trim( $value ) );
                        return $value;
                };

                $title_normalized = $normalize( $title );

                $author_list = politeia__extract_authors_array( $authors );

                $author_tokens = array();
                foreach ( $author_list as $author ) {
                        $normalized = $normalize( $author );
                        if ( '' !== $normalized ) {
                                $author_tokens[] = $normalized;
                        }
                }

                $author_tokens = array_values( array_unique( $author_tokens ) );
                sort( $author_tokens, SORT_STRING );

                return hash( 'sha256', $title_normalized . '|' . implode( '|', $author_tokens ) );
        }
}
