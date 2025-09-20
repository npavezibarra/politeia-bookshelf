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
		add_action( 'wp_ajax_prs_cover_fetch_remote', array( __CLASS__, 'ajax_fetch_remote' ) );
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
				'fetchNonce'  => wp_create_nonce( 'prs_cover_fetch_remote' ),
				'coverWidth'  => 240,
				'coverHeight' => 450,
				'onlyOne'     => 1,
			)
		);
}

	public static function shortcode_button( $atts ) {
		ob_start();
		?>
		<div class="prs-cover-actions">
			<div class="prs-cover-buttons">
				<button type="button" id="prs-cover-open" class="prs-btn prs-cover-btn">Upload Book Cover</button>
				<button type="button" id="prs-cover-fetch" class="prs-btn prs-cover-btn">Get Book Cover</button>
			</div>
			<span id="prs-cover-status" aria-live="polite"></span>
		</div>
		<?php
		return ob_get_clean();
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
				'cover_url_user'          => null,
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

	public static function ajax_fetch_remote() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required', 'politeia-reading' ) ), 401 );
		}

		$nonce = isset( $_POST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'prs_cover_fetch_remote' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'politeia-reading' ) ), 403 );
		}

		$user_id      = get_current_user_id();
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		$book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;

		if ( ! $user_book_id || ! $book_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing parameters', 'politeia-reading' ) ), 400 );
		}

		global $wpdb;
		$user_books_table = $wpdb->prefix . 'politeia_user_books';
		$books_table      = $wpdb->prefix . 'politeia_books';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$user_books_table} WHERE id = %d AND user_id = %d AND book_id = %d LIMIT 1",
				$user_book_id,
				$user_id,
				$book_id
			)
		);

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'You cannot modify this book.', 'politeia-reading' ) ), 403 );
		}

		$book = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT title, author FROM {$books_table} WHERE id = %d LIMIT 1",
				$book_id
			)
		);

		if ( ! $book ) {
			wp_send_json_error( array( 'message' => __( 'Book not found.', 'politeia-reading' ) ), 404 );
		}

		$title  = isset( $book->title ) ? wp_strip_all_tags( $book->title ) : '';
		$author = isset( $book->author ) ? wp_strip_all_tags( $book->author ) : '';

		$providers = array( 'open_library', 'google_books' );
		$result    = null;

		foreach ( $providers as $provider ) {
			if ( 'open_library' === $provider ) {
				$result = self::fetch_from_open_library( $title, $author );
			} elseif ( 'google_books' === $provider ) {
				$result = self::fetch_from_google_books( $title, $author );
			}

			if ( $result ) {
				break;
			}
		}

		if ( ! $result || empty( $result['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No cover found', 'politeia-reading' ) ), 404 );
		}

		$url    = self::normalize_remote_url( $result['url'] );
		$source = isset( $result['source'] ) ? $result['source'] : '';

		if ( ! $url ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cover URL returned', 'politeia-reading' ) ), 500 );
		}

                $key = 'u' . $user_id . 'ub' . $user_book_id;

                $existing_attachments = get_posts(
                        array(
                                'post_type'      => 'attachment',
                                'post_status'    => 'inherit',
                                'fields'         => 'ids',
                                'posts_per_page' => -1,
                                'author'         => $user_id,
                                'meta_query'     => array(
                                        array(
                                                'key'   => '_prs_cover_key',
                                                'value' => $key,
                                        ),
                                ),
                        )
                );

                foreach ( $existing_attachments as $attachment_id ) {
                        wp_delete_attachment( $attachment_id, true );
                }

                $wpdb->update(
                        $user_books_table,
                        array(
                                'cover_url_user'           => $url,
                                'cover_attachment_id_user' => null,
                                'updated_at'                => current_time( 'mysql', true ),
                        ),
                        array( 'id' => $user_book_id )
                );

		wp_send_json_success(
			array(
				'url'    => esc_url( $url ),
				'source' => $source,
			)
		);
	}

	private static function fetch_from_open_library( $title, $author ) {
		$args = array(
			'limit' => 5,
		);

		if ( $title ) {
			$args['title'] = $title;
		}

		if ( $author ) {
			$args['author'] = $author;
		}

		$url     = add_query_arg( $args, 'https://openlibrary.org/search.json' );
		$request = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $request ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $request );
		if ( 200 !== (int) $code ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $request ), true );
		if ( empty( $data['docs'] ) || ! is_array( $data['docs'] ) ) {
			return null;
		}

		foreach ( $data['docs'] as $doc ) {
			if ( ! empty( $doc['cover_i'] ) ) {
				$cover_id = (int) $doc['cover_i'];
				if ( $cover_id > 0 ) {
					return array(
						'url'    => sprintf( 'https://covers.openlibrary.org/b/id/%d-L.jpg', $cover_id ),
						'source' => 'open_library',
					);
				}
			}

			if ( ! empty( $doc['isbn'] ) && is_array( $doc['isbn'] ) ) {
				foreach ( $doc['isbn'] as $isbn ) {
					$isbn = preg_replace( '/[^0-9Xx]/', '', (string) $isbn );
					if ( $isbn ) {
						return array(
							'url'    => sprintf( 'https://covers.openlibrary.org/b/isbn/%s-L.jpg', rawurlencode( $isbn ) ),
							'source' => 'open_library',
						);
					}
				}
			}
		}

		return null;
	}

	private static function fetch_from_google_books( $title, $author ) {
		$query_parts = array();

		if ( $title ) {
			$query_parts[] = 'intitle:' . $title;
		}

		if ( $author ) {
			$query_parts[] = 'inauthor:' . $author;
		}

		if ( empty( $query_parts ) ) {
			return null;
		}

		$url      = add_query_arg(
			array(
				'q'          => implode( ' ', $query_parts ),
				'maxResults' => 5,
			),
			'https://www.googleapis.com/books/v1/volumes'
		);
		$request = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $request ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $request );
		if ( 200 !== (int) $code ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $request ), true );
		if ( empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return null;
		}

		$priority = array( 'extraLarge', 'large', 'medium', 'small', 'thumbnail', 'smallThumbnail' );

		foreach ( $data['items'] as $item ) {
			if ( empty( $item['volumeInfo']['imageLinks'] ) || ! is_array( $item['volumeInfo']['imageLinks'] ) ) {
				continue;
			}

			foreach ( $priority as $key ) {
				if ( empty( $item['volumeInfo']['imageLinks'][ $key ] ) ) {
					continue;
				}

				$url = self::normalize_remote_url( $item['volumeInfo']['imageLinks'][ $key ] );
				if ( $url ) {
					return array(
						'url'    => $url,
						'source' => 'google_books',
					);
				}
			}
		}

		return null;
	}

	private static function normalize_remote_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		if ( strpos( $url, '//' ) === 0 ) {
			$url = 'https:' . $url;
		}

		if ( 0 === strpos( $url, 'http://' ) ) {
			$url = 'https://' . substr( $url, 7 );
		}

		return esc_url_raw( $url, array( 'http', 'https' ) );
	}
}
PRS_Cover_Upload_Feature::init();
