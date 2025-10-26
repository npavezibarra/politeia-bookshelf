<?php
/**
 * Feature: Upload Book Cover (modal + crop/zoom centrado)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRS_Cover_Upload_Feature {

	public static function init() {
                add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
                add_shortcode( 'prs_cover_button', array( __CLASS__, 'shortcode_button' ) );
                add_action( 'wp_ajax_prs_cover_save_crop', array( __CLASS__, 'ajax_save_crop' ) );
                add_action( 'wp_ajax_prs_cover_save_external', array( __CLASS__, 'ajax_save_external' ) );
                add_action( 'wp_ajax_prs_cover_search_google', array( __CLASS__, 'ajax_search_google' ) );
        }

	public static function assets() {
		// Solo en la pantalla del libro (usas query var prs_book_slug en tu template).
		if ( ! get_query_var( 'prs_book_slug' ) ) {
			return;
		}

		// CSS + JS de la feature
		wp_register_style(
			'prs-cover-upload',
			plugins_url( 'templates/features/cover-upload/cover-upload.css', dirname( __DIR__, 2 ) ),
			array(),
			'0.1.0'
		);
		wp_register_script(
			'prs-cover-upload',
			plugins_url( 'templates/features/cover-upload/cover-upload.js', dirname( __DIR__, 2 ) ),
			array(),
			'0.1.0',
			true
		);

		wp_enqueue_style( 'prs-cover-upload' );
		wp_enqueue_script( 'prs-cover-upload' );

		// Datos para AJAX
		global $wpdb;
		// Necesitamos el user_book_id y book_id que ya tienes en PRS_BOOK.
		// Si por alguna razón no están, el JS leerá de window.PRS_BOOK.
                wp_localize_script(
                        'prs-cover-upload',
                        'PRS_COVER',
                        array(
                                'ajax'        => admin_url( 'admin-ajax.php' ),
                                'nonce'       => wp_create_nonce( 'prs_cover_save_crop' ),
                                'coverWidth'  => 240,
                                'coverHeight' => 450,
                                'onlyOne'     => 1,
                                'externalNonce' => wp_create_nonce( 'prs_cover_save_external' ),
                                'searchNonce'   => wp_create_nonce( 'prs_cover_search_google' ),
                        )
                );
        }

        public static function shortcode_button( $atts ) {
                // Botón compacto para insertar sobre la portada
                return '<div class="prs-cover-actions">'
                        . '<button type="button" id="prs-cover-open" class="prs-btn prs-cover-btn prs-cover-upload-button">Upload Book Cover</button>'
                        . '<button type="button" id="prs-cover-search" class="prs-btn prs-cover-btn prs-cover-search-button">Search Cover</button>'
                        . '</div>';
        }

	/**
	 * Recibe un dataURL (JPG/PNG) ya recortado a 240x450,
	 * lo guarda como attachment, borra portadas anteriores del mismo user_book,
	 * y actualiza politeia_user_books.cover_attachment_id_user
	 */
	public static function ajax_save_crop() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'auth' ), 401 );
		}
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'prs_cover_save_crop' ) ) {
			wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
		}

		$user_id      = get_current_user_id();
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		$book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$data_url     = isset( $_POST['image'] ) ? (string) $_POST['image'] : '';

		if ( ! $user_book_id || ! $book_id || ! $data_url ) {
			wp_send_json_error( array( 'message' => 'missing_params' ), 400 );
		}

		// Validar pertenencia del user_book
		global $wpdb;
		$t   = $wpdb->prefix . 'politeia_user_books';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE id=%d AND user_id=%d AND book_id=%d LIMIT 1",
				$user_book_id,
				$user_id,
				$book_id
			)
		);
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		// Decodificar dataURL
		if ( ! preg_match( '#^data:image/(png|jpeg);base64,#i', $data_url, $m ) ) {
			wp_send_json_error( array( 'message' => 'bad_image' ), 400 );
		}
		$ext = strtolower( $m[1] ) === 'png' ? 'png' : 'jpg';
		$bin = base64_decode( preg_replace( '#^data:image/\w+;base64,#i', '', $data_url ) );
		if ( ! $bin ) {
			wp_send_json_error( array( 'message' => 'decode_fail' ), 400 );
		}

		// Guardar archivo en uploads
		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) {
			wp_send_json_error( array( 'message' => 'upload_dir_error' ), 500 );
		}

		$key      = 'u' . $user_id . 'ub' . $user_book_id;
		$filename = 'book-cover-' . $key . '-' . gmdate( 'Ymd-His' ) . '.' . $ext;
		$path     = trailingslashit( $up['path'] ) . $filename;

		if ( ! wp_mkdir_p( $up['path'] ) ) {
			wp_send_json_error( array( 'message' => 'mkdir_fail' ), 500 );
		}
		if ( ! file_put_contents( $path, $bin ) ) {
			wp_send_json_error( array( 'message' => 'write_fail' ), 500 );
		}

		// Insertar attachment
		$filetype = wp_check_filetype( $path, null );
		$att_id   = wp_insert_attachment(
			array(
				'post_mime_type' => $filetype['type'],
				'post_title'     => sanitize_file_name( preg_replace( '/\.[^.]+$/', '', $filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => $user_id,
			),
			$path
		);

		if ( ! $att_id ) {
			@unlink( $path );
			wp_send_json_error( array( 'message' => 'attach_fail' ), 500 );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_generate_attachment_metadata( $att_id, $path );
		wp_update_attachment_metadata( $att_id, $meta );

		// Etiquetas para poder limpiar
		update_post_meta( $att_id, '_prs_cover_user_id', $user_id );
		update_post_meta( $att_id, '_prs_cover_user_book_id', $user_book_id );
		update_post_meta( $att_id, '_prs_cover_key', $key );

		// Borrar otras portadas del mismo user_book
		$others = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'author'         => $user_id,
				'exclude'        => array( $att_id ),
				'meta_query'     => array(
					array(
						'key'   => '_prs_cover_key',
						'value' => $key,
					),
				),
			)
		);
		foreach ( $others as $oid ) {
			wp_delete_attachment( $oid, true );
		}

		// Persistir en politeia_user_books
                $wpdb->update(
                        $t,
                        array(
                                'cover_attachment_id_user' => (int) $att_id,
                                'updated_at'               => current_time( 'mysql', true ),
                        ),
                        array( 'id' => $user_book_id )
                );

                // Responder con URL para reemplazar la portada en el front
                $src = wp_get_attachment_image_url( $att_id, 'large' );
                wp_send_json_success(
                        array(
                                'id'  => (int) $att_id,
                                'src' => $src ?: '',
                        )
                );
        }

        /**
         * Guarda una portada externa seleccionada por el usuario.
         */
        public static function ajax_save_external() {
                if ( ! is_user_logged_in() ) {
                        wp_send_json_error( array( 'message' => 'auth' ), 401 );
                }
                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'prs_cover_save_external' ) ) {
                        wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
                }

                $user_id      = get_current_user_id();
                $user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
                $book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
                $image_url    = isset( $_POST['image_url'] ) ? (string) wp_unslash( $_POST['image_url'] ) : '';
                $image_url    = $image_url ? esc_url_raw( $image_url ) : '';
                $source_link  = isset( $_POST['source_link'] ) ? (string) wp_unslash( $_POST['source_link'] ) : '';
                $source_link  = $source_link ? esc_url_raw( $source_link ) : '';

                if ( ! $user_book_id || ! $book_id || ! $image_url ) {
                        wp_send_json_error( array( 'message' => 'missing_params' ), 400 );
                }

                if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
                        wp_send_json_error( array( 'message' => 'bad_url' ), 400 );
                }

                $scheme = wp_parse_url( $image_url, PHP_URL_SCHEME );
                if ( ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ) {
                        wp_send_json_error( array( 'message' => 'unsupported_scheme' ), 400 );
                }

                $image_host = wp_parse_url( $image_url, PHP_URL_HOST );
                if ( ! $image_host || false === stripos( $image_host, 'books.google' ) ) {
                        wp_send_json_error( array( 'message' => 'invalid_image_host' ), 400 );
                }

                if ( $source_link ) {
                        if ( ! filter_var( $source_link, FILTER_VALIDATE_URL ) ) {
                                wp_send_json_error( array( 'message' => 'bad_source_url' ), 400 );
                        }

                        $source_scheme = wp_parse_url( $source_link, PHP_URL_SCHEME );
                        if ( ! in_array( strtolower( (string) $source_scheme ), array( 'http', 'https' ), true ) ) {
                                wp_send_json_error( array( 'message' => 'unsupported_source_scheme' ), 400 );
                        }

                        $source_host = wp_parse_url( $source_link, PHP_URL_HOST );
                        if ( ! $source_host || false === stripos( $source_host, 'books.google' ) ) {
                                wp_send_json_error( array( 'message' => 'invalid_source_host' ), 400 );
                        }
                }

                global $wpdb;

                $user_books_table = $wpdb->prefix . 'politeia_user_books';
                $books_table      = $wpdb->prefix . 'politeia_books';

                $row = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT id FROM {$user_books_table} WHERE id=%d AND user_id=%d AND book_id=%d LIMIT 1",
                                $user_book_id,
                                $user_id,
                                $book_id
                        )
                );

                if ( ! $row ) {
                        wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
                }

                $now = current_time( 'mysql', true );
                if ( $source_link ) {
                        $sql = $wpdb->prepare(
                                "UPDATE {$books_table} SET cover_url = %s, cover_source = %s, updated_at = %s WHERE id = %d",
                                $image_url,
                                $source_link,
                                $now,
                                $book_id
                        );
                } else {
                        $sql = $wpdb->prepare(
                                "UPDATE {$books_table} SET cover_url = %s, cover_source = NULL, updated_at = %s WHERE id = %d",
                                $image_url,
                                $now,
                                $book_id
                        );
                }

                $updated = $wpdb->query( $sql );

                if ( false === $updated ) {
                        wp_send_json_error( array( 'message' => 'db_error' ), 500 );
                }

                wp_send_json_success(
                        array(
                                'src' => $image_url,
                                'source' => $source_link,
                        )
                );
        }

        public static function ajax_search_google() {
                if ( ! is_user_logged_in() ) {
                        wp_send_json_error( array( 'message' => 'auth' ), 401 );
                }
                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'prs_cover_search_google' ) ) {
                        wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
                }

                $title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
                $author   = isset( $_POST['author'] ) ? sanitize_text_field( wp_unslash( $_POST['author'] ) ) : '';
                $language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';
                $language = self::normalize_language( $language );

                if ( '' === $title ) {
                        wp_send_json_error( array( 'message' => 'missing_title' ), 400 );
                }

                $api_key = trim( (string) get_option( 'politeia_bookshelf_google_api_key' ) );
                if ( '' === $api_key ) {
                        wp_send_json_error( array( 'message' => 'missing_api_key' ), 400 );
                }

                $query = $title;
                if ( $author ) {
                        $query .= ' inauthor:' . $author;
                }

                $args = array(
                        'q'            => $query,
                        'langRestrict' => $language ? $language : 'en',
                        'maxResults'   => 5,
                        'printType'    => 'books',
                        'key'          => $api_key,
                );

                $url      = add_query_arg( $args, 'https://www.googleapis.com/books/v1/volumes' );
                $response = wp_remote_get(
                        $url,
                        array(
                                'timeout' => 10,
                        )
                );

                if ( is_wp_error( $response ) ) {
                        wp_send_json_error( array( 'message' => 'api_error' ), 500 );
                }

                $code = (int) wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body, true );

                if ( ! is_array( $data ) ) {
                        wp_send_json_error( array( 'message' => 'api_error' ), 500 );
                }

                if ( $code >= 400 ) {
                        $message = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'api_error';
                        wp_send_json_error( array( 'message' => $message ), $code );
                }

                if ( empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
                        wp_send_json_error( array( 'message' => 'no_results' ), 404 );
                }

                $preferred = array();
                $fallback  = array();
                $seen      = array();

                foreach ( $data['items'] as $entry ) {
                        if ( ! is_array( $entry ) ) {
                                continue;
                        }

                        $volume = isset( $entry['volumeInfo'] ) && is_array( $entry['volumeInfo'] ) ? $entry['volumeInfo'] : array();
                        $vol_title = isset( $volume['title'] ) ? (string) $volume['title'] : '';
                        if ( '' === $vol_title ) {
                                continue;
                        }

                        $similarity = self::title_similarity( $title, $vol_title );
                        if ( $similarity < 0.7 ) {
                                continue;
                        }

                        $links = isset( $volume['imageLinks'] ) && is_array( $volume['imageLinks'] ) ? $volume['imageLinks'] : array();
                        $best  = self::best_image_link( $links );
                        if ( '' === $best ) {
                                continue;
                        }

                        $cover_url = self::sanitize_google_image_url( $best );
                        if ( '' === $cover_url ) {
                                continue;
                        }

                        $info_link = isset( $volume['infoLink'] ) ? self::sanitize_google_info_link( (string) $volume['infoLink'] ) : '';
                        if ( '' === $info_link ) {
                                continue;
                        }

                        if ( isset( $seen[ $cover_url ] ) ) {
                                continue;
                        }

                        $seen[ $cover_url ] = true;

                        $vol_language = isset( $volume['language'] ) ? self::normalize_language( $volume['language'] ) : '';
                        $item         = array(
                                'url'      => $cover_url,
                                'source'   => $info_link,
                                'language' => $vol_language,
                                'title'    => $vol_title,
                        );

                        if ( $language && $vol_language && $language !== $vol_language ) {
                                $fallback[] = $item;
                        } else {
                                $preferred[] = $item;
                        }
                }

                $items = array_merge( $preferred, $fallback );
                if ( empty( $items ) ) {
                        wp_send_json_error( array( 'message' => 'no_results' ), 404 );
                }

                $items = array_slice( $items, 0, 3 );

                wp_send_json_success(
                        array(
                                'items' => $items,
                        )
                );
        }

        private static function normalize_language( $code ) {
                $code = strtolower( trim( (string) $code ) );
                if ( '' === $code ) {
                        return '';
                }

                $code = preg_replace( '/^languages\//', '', $code );
                $code = preg_replace( '/^lang\//', '', $code );
                $code = str_replace( '_', '-', $code );

                if ( strlen( $code ) === 2 ) {
                        return $code;
                }

                $map = array(
                        'eng' => 'en',
                        'spa' => 'es',
                        'esl' => 'es',
                        'fre' => 'fr',
                        'fra' => 'fr',
                        'por' => 'pt',
                        'ptg' => 'pt',
                        'ger' => 'de',
                        'deu' => 'de',
                        'ita' => 'it',
                        'cat' => 'ca',
                        'glg' => 'gl',
                );

                if ( isset( $map[ $code ] ) ) {
                        return $map[ $code ];
                }

                if ( strlen( $code ) > 2 ) {
                        return substr( $code, 0, 2 );
                }

                return $code;
        }

        private static function normalize_title( $title ) {
                $title = strtolower( (string) $title );
                $title = preg_replace( "/[\"'`\x{2018}\x{2019}\x{201C}\x{201D}]/u", '', $title );
                $title = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $title );
                $title = preg_replace( '/\s+/u', ' ', $title );
                return trim( $title );
        }

        private static function title_similarity( $a, $b ) {
                $norm_a = self::normalize_title( $a );
                $norm_b = self::normalize_title( $b );
                if ( '' === $norm_a || '' === $norm_b ) {
                        return 0.0;
                }

                $words_a = array_unique( preg_split( '/\s+/', $norm_a, -1, PREG_SPLIT_NO_EMPTY ) );
                $words_b = array_unique( preg_split( '/\s+/', $norm_b, -1, PREG_SPLIT_NO_EMPTY ) );
                if ( empty( $words_a ) || empty( $words_b ) ) {
                        return 0.0;
                }

                $overlap = count( array_intersect( $words_a, $words_b ) );
                return $overlap / max( count( $words_a ), count( $words_b ) );
        }

        private static function best_image_link( array $links ) {
                $order = array( 'extraLarge', 'large', 'medium', 'thumbnail', 'smallThumbnail' );
                foreach ( $order as $key ) {
                        if ( ! empty( $links[ $key ] ) ) {
                                return (string) $links[ $key ];
                        }
                }
                return '';
        }

        private static function sanitize_google_image_url( $url ) {
                if ( ! $url ) {
                        return '';
                }

                $url = str_replace( 'http://', 'https://', trim( (string) $url ) );
                $parts = wp_parse_url( $url );
                if ( empty( $parts['host'] ) || false === stripos( $parts['host'], 'books.google' ) ) {
                        return '';
                }

                return esc_url_raw( $url );
        }

        private static function sanitize_google_info_link( $url ) {
                if ( ! $url ) {
                        return '';
                }

                $url   = str_replace( 'http://', 'https://', trim( (string) $url ) );
                $parts = wp_parse_url( $url );
                if ( empty( $parts['host'] ) || false === stripos( $parts['host'], 'books.google' ) ) {
                        return '';
                }

                return esc_url_raw( $url );
        }
}
PRS_Cover_Upload_Feature::init();
