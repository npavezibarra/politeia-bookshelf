<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function prs_current_user_id_or_die() {
	if ( ! is_user_logged_in() ) {
		wp_die( __( 'You must be logged in.', 'politeia-reading' ) );
	}
	return get_current_user_id();
}

function prs_find_or_create_book( $title, $author, $year = null, $attachment_id = null, $all_authors = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'politeia_books';

        $title  = trim( wp_strip_all_tags( $title ) );
        $author = trim( wp_strip_all_tags( $author ) );

        if ( $title === '' || $author === '' ) {
                return new WP_Error( 'prs_invalid_book', 'Missing title/author' );
        }

        $normalized_title  = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $title ) : $title;
        $normalized_author = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $author ) : $author;

        $normalized_title  = $normalized_title !== '' ? $normalized_title : null;
        $normalized_author = $normalized_author !== '' ? $normalized_author : null;

        if ( function_exists( 'politeia__title_author_hash' ) ) {
                $hash = politeia__title_author_hash( $title, $author );
        } else {
                $hash = hash( 'sha256', strtolower( trim( $title ) ) . '|' . strtolower( trim( $author ) ) );
        }

        if ( empty( $hash ) ) {
                $hash = hash( 'sha256', strtolower( trim( $title ) ) . '|' . strtolower( trim( $author ) ) );
        }

        $slug = sanitize_title( $title . '-' . $author . ( $year ? '-' . $year : '' ) );

        // Prefer lookup by unique hash to avoid duplicate key errors when slug differs.
        $existing_id = $wpdb->get_var(
                $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE title_author_hash = %s LIMIT 1",
                        $hash
                )
        );
        $authors_payload = $all_authors;
        if ( $authors_payload instanceof \Traversable ) {
                $authors_payload = iterator_to_array( $authors_payload, false );
        }
        if ( empty( $authors_payload ) ) {
                $authors_payload = array( $author );
        } elseif ( is_array( $authors_payload ) ) {
                $authors_payload[] = $author;
        } else {
                $authors_payload = array( $authors_payload, $author );
        }

        if ( $existing_id ) {
                $book_id = (int) $existing_id;
                prs_sync_book_author_links( $book_id, $authors_payload );
                return $book_id;
        }

        // Fallback to slug match for legacy rows that might not yet have hashes populated.
        if ( $slug ) {
                $existing_id = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                                $slug
                        )
                );
                if ( $existing_id ) {
                        $book_id = (int) $existing_id;
                        prs_sync_book_author_links( $book_id, $authors_payload );
                        return $book_id;
                }
        }

        $inserted = $wpdb->insert(
                $table,
                array(
                        'title'               => $title,
                        'author'              => $author,
                        'year'                => $year ? (int) $year : null,
                        'cover_attachment_id' => $attachment_id ? (int) $attachment_id : null,
                        'slug'                => $slug,
                        'normalized_title'    => $normalized_title,
                        'normalized_author'   => $normalized_author,
                        'title_author_hash'   => $hash,
                        'created_at'          => current_time( 'mysql' ),
                        'updated_at'          => current_time( 'mysql' ),
                )
        );

        if ( false === $inserted ) {
                return new WP_Error( 'prs_insert_failed', $wpdb->last_error ?: 'Could not insert book.' );
        }

        $book_id = (int) $wpdb->insert_id;
        prs_sync_book_author_links( $book_id, $authors_payload );

        return $book_id;
}

function prs_ensure_user_book( $user_id, $book_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'politeia_user_books';

	// Unique (user_id, book_id)
        $row = $wpdb->get_row(
                $wpdb->prepare(
                        "SELECT id, deleted_at FROM {$table} WHERE user_id = %d AND book_id = %d LIMIT 1",
                        $user_id,
                        $book_id
                )
        );
        if ( $row ) {
                $id = (int) $row->id;
                if ( ! empty( $row->deleted_at ) ) {
                        $wpdb->update(
                                $table,
                                array(
                                        'deleted_at' => null,
                                        'updated_at' => current_time( 'mysql' ),
                                ),
                                array( 'id' => $id )
                        );
                }
                return $id;
        }

	$wpdb->insert(
		$table,
		array(
			'user_id'        => (int) $user_id,
			'book_id'        => (int) $book_id,
			'reading_status' => 'not_started',
			'owning_status'  => 'in_shelf',
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		)
	);
	return (int) $wpdb->insert_id;
}

function prs_handle_cover_upload( $field_name = 'prs_cover' ) {
        if ( empty( $_FILES[ $field_name ]['name'] ) ) {
                return null;
        }

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$file = wp_handle_upload( $_FILES[ $field_name ], array( 'test_form' => false ) );
	if ( isset( $file['error'] ) ) {
		return null;
	}

	$attachment  = array(
		'post_mime_type' => $file['type'],
		'post_title'     => sanitize_file_name( basename( $file['file'] ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	$attach_id   = wp_insert_attachment( $attachment, $file['file'] );
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        return (int) $attach_id;
}

/**
 * Synchronize canonical author entries and the pivot table that links books to authors.
 *
 * @param int          $book_id Book identifier from wp_politeia_books.
 * @param array|string $authors Array of author names or a delimited string.
 *
 * @return int[] Ordered list of author IDs linked to the book.
 */
function prs_sync_book_author_links( $book_id, $authors ) {
        global $wpdb;

        $book_id = (int) $book_id;
        if ( $book_id <= 0 ) {
                return array();
        }

        if ( null === $authors ) {
                $authors = array();
        } elseif ( is_string( $authors ) ) {
                // Split on common delimiters while preserving names that include commas via JSON/array input.
                $parts   = preg_split( '/[;,\|]+/', $authors );
                $authors = is_array( $parts ) ? $parts : array( $authors );
        } elseif ( $authors instanceof \Traversable ) {
                $authors = iterator_to_array( $authors, false );
        } elseif ( ! is_array( $authors ) ) {
                $authors = array( $authors );
        }

        $canonical = array();
        $position  = 0;

        foreach ( $authors as $raw_author ) {
                $name = trim( wp_strip_all_tags( (string) $raw_author ) );
                if ( '' === $name ) {
                        continue;
                }

                $normalized = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $name ) : strtolower( $name );
                $normalized = ( '' !== trim( (string) $normalized ) ) ? $normalized : null;

                $hash_source = $normalized ?: strtolower( $name );
                if ( '' === $hash_source ) {
                        continue;
                }

                $hash = hash( 'sha256', $hash_source );

                if ( isset( $canonical[ $hash ] ) ) {
                        continue; // Mantén el primer orden declarado.
                }

                $canonical[ $hash ] = array(
                        'name'       => $name,
                        'normalized' => $normalized,
                        'hash'       => $hash,
                        'position'   => $position,
                );
                $position++;
        }

        $book_author_table = $wpdb->prefix . 'politeia_book_authors';
        if ( empty( $canonical ) ) {
                // Sin autores => limpia vínculos existentes.
                $wpdb->delete( $book_author_table, array( 'book_id' => $book_id ) );
                return array();
        }

        uasort(
                $canonical,
                static function ( $a, $b ) {
                        return $a['position'] <=> $b['position'];
                }
        );

        $authors_table = $wpdb->prefix . 'politeia_authors';
        $hashes        = array_keys( $canonical );
        $existing      = array();

        if ( ! empty( $hashes ) ) {
                $placeholders = implode( ', ', array_fill( 0, count( $hashes ), '%s' ) );
                $sql          = "SELECT id, name_hash FROM {$authors_table} WHERE name_hash IN ({$placeholders})";
                $rows         = $wpdb->get_results( $wpdb->prepare( $sql, $hashes ) );

                foreach ( $rows as $row ) {
                        $existing[ $row->name_hash ] = (int) $row->id;
                }
        }

        $now = current_time( 'mysql', true );

        foreach ( $canonical as $hash => &$author ) {
                if ( isset( $existing[ $hash ] ) ) {
                        $author['id'] = $existing[ $hash ];
                        continue;
                }

                $slug_base = sanitize_title( $author['name'] );
                $slug      = prs_generate_unique_author_slug( $slug_base, $authors_table, $hash );

                $wpdb->insert(
                        $authors_table,
                        array(
                                'display_name'    => $author['name'],
                                'normalized_name' => $author['normalized'],
                                'name_hash'       => $author['hash'],
                                'slug'            => $slug,
                                'created_at'      => $now,
                                'updated_at'      => $now,
                        ),
                        array( '%s', '%s', '%s', '%s', '%s', '%s' )
                );

                if ( $wpdb->last_error ) {
                        continue;
                }

                $author['id'] = (int) $wpdb->insert_id;
        }
        unset( $author );

        $author_ids = array();
        foreach ( $canonical as $author ) {
                if ( empty( $author['id'] ) ) {
                        continue;
                }
                $author_ids[] = (int) $author['id'];
        }

        $existing_links = $wpdb->get_results(
                $wpdb->prepare( "SELECT id, author_id FROM {$book_author_table} WHERE book_id = %d", $book_id )
        );
        $existing_map   = array();

        foreach ( $existing_links as $link ) {
                $existing_map[ (int) $link->author_id ] = (int) $link->id;
        }

        $position = 0;
        foreach ( $canonical as $author ) {
                if ( empty( $author['id'] ) ) {
                        continue;
                }

                $author_id = (int) $author['id'];

                if ( isset( $existing_map[ $author_id ] ) ) {
                        $wpdb->update(
                                $book_author_table,
                                array(
                                        'sort_order' => $position,
                                        'updated_at' => $now,
                                ),
                                array( 'id' => $existing_map[ $author_id ] ),
                                array( '%d', '%s' ),
                                array( '%d' )
                        );

                        unset( $existing_map[ $author_id ] );
                } else {
                        $wpdb->insert(
                                $book_author_table,
                                array(
                                        'book_id'    => $book_id,
                                        'author_id'  => $author_id,
                                        'sort_order' => $position,
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                ),
                                array( '%d', '%d', '%d', '%s', '%s' )
                        );
                }

                $position++;
        }

        if ( ! empty( $existing_map ) ) {
                $ids_to_delete = array_values( $existing_map );
                $placeholders  = implode( ', ', array_fill( 0, count( $ids_to_delete ), '%d' ) );
                $wpdb->query(
                        $wpdb->prepare(
                                "DELETE FROM {$book_author_table} WHERE id IN ({$placeholders})",
                                $ids_to_delete
                        )
                );
        }

        return $author_ids;
}

/**
 * Generate a unique slug for an author entry.
 */
function prs_generate_unique_author_slug( $base_slug, $table, $hash_source = '' ) {
        global $wpdb;

        $slug = $base_slug;
        if ( '' === $slug ) {
                $slug      = 'author-' . substr( $hash_source ?: hash( 'sha256', microtime( true ) ), 0, 8 );
                $base_slug = $slug;
        }

        $candidate = $slug;
        $suffix    = 2;

        while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $candidate ) ) ) {
                $candidate = $base_slug . '-' . $suffix;

                if ( strlen( $candidate ) > 191 ) {
                        $candidate = substr( $base_slug, 0, max( 1, 191 - strlen( (string) $suffix ) - 1 ) ) . '-' . $suffix;
                }

                $suffix++;

                if ( $suffix > 20 ) {
                        $fallback  = $hash_source ?: hash( 'crc32', $base_slug . microtime( true ) );
                        $candidate = substr( $base_slug, 0, 180 ) . '-' . substr( $fallback, 0, 8 );
                        break;
                }
        }

        return $candidate;
}

function prs_maybe_alter_user_books() {
	global $wpdb;
	$t = $wpdb->prefix . 'politeia_user_books';

	$cols = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
			DB_NAME,
			$t
		)
	);
	$has  = array_map( 'strtolower', (array) $cols );

        $alters = array();

        $has_type_book = in_array( 'type_book', $has, true );
        if ( ! $has_type_book ) {
                $alters[]     = "ADD COLUMN type_book ENUM('p','d') NULL DEFAULT NULL AFTER owning_status";
                $has_type_book = true;
        }

        if ( ! in_array( 'pages', $has, true ) ) {
                $after_column = $has_type_book ? 'type_book' : 'owning_status';
                $alters[]     = sprintf( 'ADD COLUMN pages INT UNSIGNED NULL AFTER %s', $after_column );
        }
	if ( ! in_array( 'purchase_date', $has, true ) ) {
		$alters[] = 'ADD COLUMN purchase_date DATE NULL';
	}
	if ( ! in_array( 'purchase_channel', $has, true ) ) {
		$alters[] = "ADD COLUMN purchase_channel ENUM('online','store') NULL";
	}
	if ( ! in_array( 'purchase_place', $has, true ) ) {
		$alters[] = 'ADD COLUMN purchase_place VARCHAR(255) NULL';
	}
	if ( ! in_array( 'counterparty_name', $has, true ) ) {
		$alters[] = 'ADD COLUMN counterparty_name VARCHAR(255) NULL';
	}
	if ( ! in_array( 'counterparty_email', $has, true ) ) {
		$alters[] = 'ADD COLUMN counterparty_email VARCHAR(190) NULL';
	}

	if ( $alters ) {
		$wpdb->query( "ALTER TABLE {$t} " . implode( ', ', $alters ) );
	}
}
add_action( 'plugins_loaded', 'prs_maybe_alter_user_books' );

function prs_maybe_create_loans_table() {
	global $wpdb;
	$table = $wpdb->prefix . 'politeia_loans';

	$exists = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
			DB_NAME,
			$table
		)
	);

        if ( ! $exists ) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                $charset_collate = $wpdb->get_charset_collate();
                $sql             = "CREATE TABLE {$table} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          book_id BIGINT UNSIGNED NOT NULL,
          counterparty_name  VARCHAR(255) NULL,
          counterparty_email VARCHAR(190) NULL,
          amount DECIMAL(10,2) NULL,
          start_date DATETIME NOT NULL,
          end_date   DATETIME NULL,
          notes TEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_user_book (user_id, book_id),
          KEY idx_active (user_id, book_id, end_date)
        ) {$charset_collate};";
                dbDelta( $sql );
        }

        $columns = $wpdb->get_col(
                $wpdb->prepare(
                        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
                        DB_NAME,
                        $table
                )
        );

        if ( $columns && ! in_array( 'amount', array_map( 'strtolower', $columns ), true ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN amount DECIMAL(10,2) NULL AFTER counterparty_email" );
        }
}
add_action( 'plugins_loaded', 'prs_maybe_create_loans_table' );

/**
 * Render the reusable owning overlay markup once per request.
 *
 * @param array $args {
 *     Optional. Arguments to customize the overlay.
 *
 *     @type string $heading Default heading text.
 * }
 *
 * Note: The browser validates the email format via the HTML `type="email"` attribute.
 * For deeper verification (such as ensuring the domain can receive mail), consider
 * adding a lightweight server-side MX or domain existence check instead of attempting
 * full mailbox validation.
 */
function prs_render_owning_overlay( $args = array() ) {
        static $rendered = false;

        if ( $rendered ) {
                return;
        }

        $defaults = array(
                'heading' => __( 'Borrowing to:', 'politeia-reading' ),
        );

        $args     = wp_parse_args( $args, $defaults );
        $heading  = is_string( $args['heading'] ) ? $args['heading'] : '';
        $rendered = true;

        ?>
        <div id="owning-overlay" class="prs-overlay" style="display:none;">
                <div class="prs-overlay-backdrop"></div>

                <div class="prs-overlay-content">
                        <h2 id="owning-overlay-title"><?php echo esc_html( $heading ); ?></h2>

                        <input type="text" id="owning-overlay-name" class="prs-contact-input" placeholder="<?php echo esc_attr__( 'Name', 'politeia-reading' ); ?>" required>
                        <input type="email" id="owning-overlay-email" class="prs-contact-input" placeholder="<?php echo esc_attr__( 'Email', 'politeia-reading' ); ?>" required>
                        <input type="number" id="owning-overlay-amount" class="prs-contact-input" placeholder="<?php echo esc_attr__( 'Amount (e.g. 12000)', 'politeia-reading' ); ?>" step="0.01" style="display:none;">

                        <div class="prs-overlay-actions">
                                <button type="button" id="owning-overlay-confirm" class="prs-btn"><?php esc_html_e( 'Confirm', 'politeia-reading' ); ?></button>
                                <button type="button" id="owning-overlay-cancel" class="prs-btn prs-btn-secondary"><?php esc_html_e( 'Cancel', 'politeia-reading' ); ?></button>
                        </div>

                        <span id="owning-overlay-status" class="prs-help"></span>
                </div>
        </div>
        <div id="bought-overlay" class="prs-overlay" style="display:none;">
                <div class="prs-overlay-backdrop"></div>
                <div class="prs-overlay-content" style="max-width:360px;">
                        <h2><?php esc_html_e( 'Confirm Re-acquisition', 'politeia-reading' ); ?></h2>
                        <p><?php esc_html_e( 'You are marking this book as Bought Again. It will return to your shelf and become editable.', 'politeia-reading' ); ?></p>
                        <div class="prs-overlay-actions">
                                <button type="button" id="bought-overlay-confirm" class="prs-btn"><?php esc_html_e( 'Confirm', 'politeia-reading' ); ?></button>
                                <button type="button" id="bought-overlay-cancel" class="prs-btn prs-btn-secondary"><?php esc_html_e( 'Cancel', 'politeia-reading' ); ?></button>
                        </div>
                </div>
        </div>
        <?php
}

// Devuelve el start_date (GMT) del loan activo o null
function prs_get_active_loan_start_date( $user_id, $book_id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'politeia_loans';
	return $wpdb->get_var(
		$wpdb->prepare(
			"SELECT start_date FROM {$t}
         WHERE user_id=%d AND book_id=%d AND end_date IS NULL
         ORDER BY id DESC LIMIT 1",
			$user_id,
			$book_id
		)
	);
}
