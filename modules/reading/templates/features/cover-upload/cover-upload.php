<?php
/**
 * Feature: Upload Book Cover (modal + crop/zoom centrado)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRS_Cover_Upload_Feature {

	/**
	 * Tracks whether hooks have already been registered for this feature.
	 *
	 * @var bool
	 */
	private static $bootstrapped = false;

	public static function init() {
		if ( self::$bootstrapped ) {
			return;
		}

		self::$bootstrapped = true;
                add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
                add_shortcode( 'prs_cover_button', array( __CLASS__, 'shortcode_button' ) );
                add_action( 'wp_ajax_prs_save_cropped_cover', array( __CLASS__, 'ajax_save_cropped_cover' ) );
                add_action( 'wp_ajax_nopriv_prs_save_cropped_cover', array( __CLASS__, 'ajax_save_cropped_cover' ) );
                add_action( 'wp_ajax_prs_cover_save_crop', array( __CLASS__, 'ajax_save_crop' ) );
                add_action( 'wp_ajax_prs_cover_save_external', array( __CLASS__, 'ajax_save_external' ) );
                add_action( 'wp_ajax_prs_cover_search_google', array( __CLASS__, 'ajax_search_google' ) );
                add_action( 'wp_ajax_prs_save_cover_url', array( __CLASS__, 'ajax_save_cover_url' ) );

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
                        array( 'jquery', 'prs-cover-modal' ),
                        '0.1.0',
                        true
                );

                wp_enqueue_style( 'prs-cover-upload' );
                wp_enqueue_script( 'prs-cover-upload' );

                if ( ! has_action( 'wp_footer', array( __CLASS__, 'render_modal_template' ) ) ) {
                        add_action( 'wp_footer', array( __CLASS__, 'render_modal_template' ) );
                }

                // Datos para AJAX
                global $wpdb;
                // Necesitamos el user_book_id y book_id que ya tienes en PRS_BOOK.
                // Si por alguna razón no están, el JS leerá de window.PRS_BOOK.
                $ajax_url       = admin_url( 'admin-ajax.php' );
                $crop_nonce     = wp_create_nonce( 'prs_cover_save_crop' );
                $save_nonce     = wp_create_nonce( 'prs_cover_nonce' );
                $external_nonce = wp_create_nonce( 'prs_cover_save_external' );
                $search_nonce   = wp_create_nonce( 'prs_cover_search_google' );
                $post_id        = get_queried_object_id();

                wp_localize_script(
                        'prs-cover-upload',
                        'PRS_COVER',
                        array(
                                'ajax'        => $ajax_url,
                                'nonce'       => $crop_nonce,
                                'cropNonce'   => $crop_nonce,
                                'coverWidth'  => 240,
                                'coverHeight' => 450,
                                'onlyOne'     => 1,
                                'externalNonce' => $external_nonce,
                                'searchNonce'   => $search_nonce,
                                'saveUrl'       => $ajax_url,
                                'saveNonce'     => $save_nonce,
                                'postId'        => (int) $post_id,
                        )
                );

                wp_localize_script(
                        'prs-cover-upload',
                        'prs_cover_data',
                        array(
                                'ajaxurl' => $ajax_url,
                                'nonce'   => $save_nonce,
                        )
                );

                $inline_config = sprintf(
                        "window.PRS_SAVE_URL = %s;\nwindow.PRS_NONCE = %s;\nwindow.PRS_COVER_CROP_NONCE = %s;\nwindow.PRS_POST_ID = %s;",
                        wp_json_encode( $ajax_url ),
                        wp_json_encode( $save_nonce ),
                        wp_json_encode( $crop_nonce ),
                        wp_json_encode( (int) $post_id )
                );

                wp_add_inline_script( 'prs-cover-upload', $inline_config, 'before' );
        }

        public static function render_modal_template() {
                if ( ! get_query_var( 'prs_book_slug' ) ) {
                        return;
                }

                $template = trailingslashit( POLITEIA_READING_PATH ) . 'templates/partials/prs-cover-modal.php';

                if ( file_exists( $template ) ) {
                        include $template;
                }
        }

        public static function shortcode_button( $atts ) {
                // Botón compacto para insertar sobre la portada
                return '<div class="prs-cover-actions">'
                        . '<button type="button" id="prs-cover-open" class="prs-btn prs-cover-btn prs-cover-upload-button">Upload Book Cover</button>'
                        . '<button type="button" id="prs-cover-search" class="prs-btn prs-cover-btn prs-cover-search-button">Search Cover</button>'
                        . '</div>';
        }

        public static function ajax_save_cropped_cover() {
                if ( ! is_user_logged_in() ) {
                        wp_send_json_error( array( 'message' => 'auth' ), 401 );
                }

                $nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
                if ( ! wp_verify_nonce( $nonce, 'prs_cover_nonce' ) ) {
                        wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
                }

                $post_id      = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
                $mime         = isset( $_POST['mime'] ) ? sanitize_text_field( wp_unslash( $_POST['mime'] ) ) : 'image/png';
                $data_url     = isset( $_POST['data'] ) ? (string) wp_unslash( $_POST['data'] ) : '';
                $user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
                $book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;

                if ( '' === $data_url || false === strpos( $data_url, 'base64,' ) ) {
                        wp_send_json_error( array( 'message' => 'invalid_payload' ), 400 );
                }

                $user_id = get_current_user_id();

                if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
                        wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
                }

                if ( $user_book_id && $book_id ) {
                        global $wpdb;
                        $table = $wpdb->prefix . 'politeia_user_books';
                        $row   = $wpdb->get_row(
                                $wpdb->prepare(
                                        "SELECT id FROM {$table} WHERE id=%d AND user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
                                        $user_book_id,
                                        $user_id,
                                        $book_id
                                )
                        );

                        if ( ! $row ) {
                                wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
                        }
                }

                $base64 = substr( $data_url, strpos( $data_url, 'base64,' ) + 7 );
                $binary = base64_decode( $base64 );

                if ( ! $binary ) {
                        wp_send_json_error( array( 'message' => 'decode_fail' ), 400 );
                }

                $normalized_mime = strtolower( $mime );
                if ( in_array( $normalized_mime, array( 'image/jpeg', 'image/jpg', 'jpeg', 'jpg' ), true ) ) {
                        $extension = 'jpg';
                        $mime_type = 'image/jpeg';
                } else {
                        $extension = 'png';
                        $mime_type = 'image/png';
                }

                $attachment = self::create_cropped_attachment( $binary, $extension, $mime_type, $user_id, $post_id, $user_book_id );

                if ( is_wp_error( $attachment ) ) {
                        wp_send_json_error( array( 'message' => $attachment->get_error_message() ), 500 );
                }

                $attachment_id = (int) $attachment['attachment_id'];

                if ( $user_book_id && $book_id ) {
                        global $wpdb;
                        $table   = $wpdb->prefix . 'politeia_user_books';
                        $updated = $wpdb->update(
                                $table,
                                array(
                                        'cover_reference' => (string) $attachment_id,
                                        'updated_at'      => current_time( 'mysql', true ),
                                ),
                                array( 'id' => $user_book_id ),
                                array( '%s', '%s' ),
                                array( '%d' )
                        );

                        if ( false === $updated ) {
                                wp_delete_attachment( $attachment_id, true );
                                wp_send_json_error( array( 'message' => 'db_error' ), 500 );
                        }

                        self::cleanup_cover_attachments( $user_id, $user_book_id, $attachment_id );
                }

                if ( $post_id ) {
                        set_post_thumbnail( $post_id, $attachment_id );
                }

                $url = wp_get_attachment_image_url( $attachment_id, 'full' );
                if ( ! $url ) {
                        $url = $attachment['url'];
                }

                wp_send_json_success(
                        array(
                                'attachment_id' => $attachment_id,
                                'url'           => $url,
                        )
                );
        }

        /**
         * Recibe un dataURL (JPG/PNG) ya recortado a 240x450,
         * lo guarda como attachment, borra portadas anteriores del mismo user_book,
        * y actualiza politeia_user_books.cover_reference
         */
        public static function ajax_save_crop() {
                error_log( '[Cover] ajax_save_crop() started' );
                error_log( '[Cover] POST keys: ' . implode( ', ', array_keys( $_POST ) ) );

                if ( ! is_user_logged_in() ) {
                        error_log( '[Cover] Permission denied: user not logged in' );
                        wp_send_json_error( array( 'message' => 'Permission denied' ), 401 );
                }

                $nonce_primary   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
                $nonce_secondary = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
                $nonce_sources   = array_filter(
                        array( $nonce_primary, $nonce_secondary ),
                        static function ( $value ) {
                                return '' !== $value;
                        }
                );

                $nonce_valid = false;
                foreach ( $nonce_sources as $token ) {
                        if ( wp_verify_nonce( $token, 'prs_cover_save_crop' ) ) {
                                $nonce_valid = true;
                                break;
                        }
                }

                if ( ! $nonce_valid ) {
                        foreach ( $nonce_sources as $token ) {
                                if ( wp_verify_nonce( $token, 'prs_cover_nonce' ) ) {
                                        $nonce_valid = true;
                                        break;
                                }
                        }
                }

                if ( ! $nonce_valid ) {
                        $token_lengths = array_map(
                                static function ( $value ) {
                                        return strlen( $value ) . ' chars';
                                },
                                $nonce_sources
                        );

                        error_log(
                                sprintf(
                                        '[Cover] Nonce validation failed for user %d (tokens: %s)',
                                        get_current_user_id(),
                                        implode( ', ', $token_lengths )
                                )
                        );
                        wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
                }

                error_log( '[Cover] Nonce validated' );

                $image_data   = isset( $_POST['image'] ) ? wp_unslash( $_POST['image'] ) : '';
                $user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
                $book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;

                if ( empty( $image_data ) ) {
                        error_log( '[Cover] Missing image data' );
                        wp_send_json_error( array( 'message' => 'No image data received' ) );
                }

                $image_data = preg_replace( '#^data:image/\w+;base64,#i', '', $image_data );
                $image_data = str_replace( ' ', '+', $image_data );
                $decoded    = base64_decode( $image_data );

                if ( ! $decoded ) {
                        error_log( '[Cover] Base64 decode failed' );
                        wp_send_json_error( array( 'message' => 'Invalid image payload' ) );
                }

                $upload_dir = wp_upload_dir();

                if ( ! empty( $upload_dir['error'] ) ) {
                        error_log( '[Cover] Upload dir error: ' . $upload_dir['error'] );
                        wp_send_json_error( array( 'message' => 'Upload directory unavailable' ) );
                }

                if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
                        error_log( '[Cover] Failed to ensure upload directory: ' . $upload_dir['path'] );
                        wp_send_json_error( array( 'message' => 'Upload directory unavailable' ) );
                }

                error_log( '[Cover] Upload path prepared: ' . $upload_dir['path'] );
                $file_name  = 'book-cover-' . uniqid() . '.png';
                $file_path  = trailingslashit( $upload_dir['path'] ) . $file_name;

                if ( false === file_put_contents( $file_path, $decoded ) ) {
                        error_log( '[Cover] File write failed at ' . $file_path );
                        wp_send_json_error( array( 'message' => 'Failed to write image' ) );
                }

                error_log( '[Cover] File saved: ' . $file_path . ' (' . strlen( $decoded ) . ' bytes)' );

                $wp_filetype   = wp_check_filetype( $file_name, null );
                $attachment    = array(
                        'post_mime_type' => $wp_filetype['type'],
                        'post_title'     => sanitize_file_name( $file_name ),
                        'post_status'    => 'inherit',
                );
                $attachment_id = wp_insert_attachment( $attachment, $file_path );

                if ( ! $attachment_id ) {
                        error_log( '[Cover] Attachment insert failed' );
                        wp_send_json_error( array( 'message' => 'Attachment creation failed' ) );
                }

                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
                wp_update_attachment_metadata( $attachment_id, $attach_data );

                $attachment_url = wp_get_attachment_url( $attachment_id );
                error_log( '[Cover] Upload OK: ' . $attachment_url );

                global $wpdb;
                $table = $wpdb->prefix . 'politeia_user_books';
                if ( $user_book_id > 0 ) {
                        $update = $wpdb->update(
                                $table,
                                array(
                                        'cover_attachment_id_user' => $attachment_id,
                                        'cover_reference'           => (string) $attachment_id,
                                        'cover_url'                 => $attachment_url,
                                        'updated_at'                => current_time( 'mysql' ),
                                ),
                                array( 'id' => $user_book_id ),
                                array( '%d', '%s', '%s', '%s' ),
                                array( '%d' )
                        );
                        if ( false === $update ) {
                                error_log( sprintf( '[Cover] Database update failed for user book %d (attachment %d)', $user_book_id, $attachment_id ) );
                        } else {
                                error_log( "[Cover] User book #{$user_book_id} updated with attachment {$attachment_id}" );
                        }
                }

                error_log( sprintf( '[Cover] ajax_save_crop() completed for attachment %d', $attachment_id ) );

                wp_send_json_success(
                        array(
                                'attachment_id' => $attachment_id,
                                'url'           => $attachment_url,
                                'user_book_id'  => $user_book_id,
                                'book_id'       => $book_id,
                        )
                );
        }

        protected static function create_cropped_attachment( $binary, $extension, $mime_type, $user_id, $post_id = 0, $user_book_id = 0 ) {
                $upload_dir = wp_upload_dir();

                if ( ! empty( $upload_dir['error'] ) ) {
                        return new WP_Error( 'upload_dir_error', $upload_dir['error'] );
                }

                if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
                        return new WP_Error( 'mkdir_fail', __( 'Unable to prepare upload directory.', 'politeia-reading' ) );
                }

                $key_fragment = $user_book_id ? self::build_cover_key( $user_id, $user_book_id ) : 'u' . (int) $user_id;
                $filename     = 'book-cover-' . $key_fragment . '-' . gmdate( 'Ymd-His' ) . '.' . $extension;
                $path         = trailingslashit( $upload_dir['path'] ) . $filename;

                if ( false === file_put_contents( $path, $binary ) ) {
                        return new WP_Error( 'write_fail', __( 'Failed to write cropped image.', 'politeia-reading' ) );
                }

                $attachment_id = wp_insert_attachment(
                        array(
                                'post_mime_type' => $mime_type,
                                'post_title'     => sanitize_file_name( preg_replace( '/\.[^.]+$/', '', $filename ) ),
                                'post_content'   => '',
                                'post_status'    => 'inherit',
                                'post_author'    => $user_id,
                                'guid'           => trailingslashit( $upload_dir['url'] ) . $filename,
                        ),
                        $path,
                        $post_id
                );

                if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
                        @unlink( $path );
                        return is_wp_error( $attachment_id ) ? $attachment_id : new WP_Error( 'attach_fail', __( 'Could not create attachment.', 'politeia-reading' ) );
                }

                require_once ABSPATH . 'wp-admin/includes/image.php';
                $meta = wp_generate_attachment_metadata( $attachment_id, $path );
                if ( $meta ) {
                        wp_update_attachment_metadata( $attachment_id, $meta );
                }

                update_post_meta( $attachment_id, '_prs_cover_user_id', $user_id );

                if ( $user_book_id ) {
                        update_post_meta( $attachment_id, '_prs_cover_user_book_id', $user_book_id );
                        update_post_meta( $attachment_id, '_prs_cover_key', self::build_cover_key( $user_id, $user_book_id ) );
                } else {
                        delete_post_meta( $attachment_id, '_prs_cover_user_book_id' );
                        delete_post_meta( $attachment_id, '_prs_cover_key' );
                }

                delete_post_meta( $attachment_id, '_prs_cover_source' );

                return array(
                        'attachment_id' => (int) $attachment_id,
                        'url'           => wp_get_attachment_url( $attachment_id ),
                        'path'          => $path,
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
                if ( ! $image_host || ! self::is_allowed_google_host( strtolower( (string) $image_host ), array( 'books.google', 'googleusercontent.com', 'ggpht.com' ) ) ) {
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
                        if ( ! $source_host || ! self::is_allowed_google_host( strtolower( (string) $source_host ), array( 'books.google', 'play.google' ) ) ) {
                                wp_send_json_error( array( 'message' => 'invalid_source_host' ), 400 );
                        }
                }

                global $wpdb;
                $user_books_table = $wpdb->prefix . 'politeia_user_books';

                $row = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id FROM {$user_books_table} WHERE id=%d AND user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
                                $user_book_id,
                                $user_id,
                                $book_id
                        )
                );

                if ( ! $row ) {
                        wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
                }

                $result = self::persist_user_cover_choice( (int) $row->id, $user_id, $book_id, $image_url, $source_link );

                if ( is_wp_error( $result ) ) {
                        error_log( sprintf( '[PRS_COVER] External cover save failed for user %d, book %d: %s', $user_id, $book_id, $result->get_error_message() ) );
                        wp_send_json_error( array( 'message' => 'db_error' ), 500 );
                }

                if ( 'attachment' === $result['type'] && ! empty( $result['attachment_id'] ) ) {
                        error_log( sprintf( '[PRS_COVER] Saved external cover for user %d, book %d as attachment %d.', $user_id, $book_id, (int) $result['attachment_id'] ) );
                } else {
                        error_log( sprintf( '[PRS_COVER] Saved external cover for user %d, book %d as direct URL.', $user_id, $book_id ) );
                }

                wp_send_json_success(
                        array(
                                'src'    => $result['src'],
                                'source' => $result['source'],
                        )
                );
        }

        /**
         * Guarda la URL de portada seleccionada desde Google Books.
         */
        public static function ajax_save_cover_url() {
                if ( ! is_user_logged_in() ) {
                        error_log( '[PRS_COVER] Unauthorized attempt to save Google cover.' );
                        wp_send_json_error( 'User not logged in.', 401 );
                }

                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'prs_cover_nonce' ) ) {
                        error_log( sprintf( '[PRS_COVER] Invalid nonce for user %d.', get_current_user_id() ) );
                        wp_send_json_error( 'Invalid nonce.', 403 );
                }

                $user_id       = get_current_user_id();
                $book_id       = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
                $cover_url     = isset( $_POST['cover_url'] ) ? esc_url_raw( wp_unslash( $_POST['cover_url'] ) ) : '';
                $cover_source  = isset( $_POST['cover_source'] ) ? esc_url_raw( wp_unslash( $_POST['cover_source'] ) ) : '';

                if ( ! $book_id || '' === $cover_url ) {
                        error_log( sprintf( '[PRS_COVER] Invalid payload for user %d. book_id=%d cover_url=%s', $user_id, $book_id, $cover_url ) );
                        wp_send_json_error( 'Invalid data.', 400 );
                }

                if ( ! filter_var( $cover_url, FILTER_VALIDATE_URL ) ) {
                        error_log( sprintf( '[PRS_COVER] Invalid cover URL for user %d, book %d: %s', $user_id, $book_id, $cover_url ) );
                        wp_send_json_error( 'Invalid cover URL.', 400 );
                }

                $scheme = wp_parse_url( $cover_url, PHP_URL_SCHEME );
                if ( ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ) {
                        error_log( sprintf( '[PRS_COVER] Invalid cover URL scheme for user %d, book %d: %s', $user_id, $book_id, $cover_url ) );
                        wp_send_json_error( 'Invalid cover URL scheme.', 400 );
                }

                $host = wp_parse_url( $cover_url, PHP_URL_HOST );
                if ( ! $host || ! self::is_allowed_google_host( strtolower( (string) $host ), array( 'books.google', 'googleusercontent.com', 'ggpht.com' ) ) ) {
                        error_log( sprintf( '[PRS_COVER] Cover host not permitted for user %d, book %d: %s', $user_id, $book_id, $cover_url ) );
                        wp_send_json_error( 'Cover host not permitted.', 400 );
                }

                if ( $cover_source ) {
                        if ( ! filter_var( $cover_source, FILTER_VALIDATE_URL ) ) {
                                error_log( sprintf( '[PRS_COVER] Invalid source URL for user %d, book %d: %s', $user_id, $book_id, $cover_source ) );
                                wp_send_json_error( 'Invalid source URL.', 400 );
                        }

                        $source_scheme = wp_parse_url( $cover_source, PHP_URL_SCHEME );
                        if ( ! in_array( strtolower( (string) $source_scheme ), array( 'http', 'https' ), true ) ) {
                                error_log( sprintf( '[PRS_COVER] Invalid source URL scheme for user %d, book %d: %s', $user_id, $book_id, $cover_source ) );
                                wp_send_json_error( 'Invalid source URL scheme.', 400 );
                        }

                        $source_host = wp_parse_url( $cover_source, PHP_URL_HOST );
                        if ( ! $source_host || ! self::is_allowed_google_host( strtolower( (string) $source_host ), array( 'books.google', 'play.google' ) ) ) {
                                error_log( sprintf( '[PRS_COVER] Source host not permitted for user %d, book %d: %s', $user_id, $book_id, $cover_source ) );
                                wp_send_json_error( 'Source host not permitted.', 400 );
                        }
                }

                global $wpdb;

                $user_books_table = $wpdb->prefix . 'politeia_user_books';

                $row = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id FROM {$user_books_table} WHERE book_id = %d AND user_id = %d AND deleted_at IS NULL LIMIT 1",
                                $book_id,
                                $user_id
                        )
                );

                if ( ! $row ) {
                        error_log( sprintf( '[PRS_COVER] Permission denied for user %d attempting to update book %d.', $user_id, $book_id ) );
                        wp_send_json_error( 'Permission denied.', 403 );
                }

                $result = self::persist_user_cover_choice( (int) $row->id, $user_id, $book_id, $cover_url, $cover_source );

                if ( is_wp_error( $result ) ) {
                        error_log( sprintf( '[PRS_COVER] Database update failed for user %d, book %d: %s', $user_id, $book_id, $result->get_error_message() ) );
                        wp_send_json_error( 'Database update failed.', 500 );
                }

                if ( 'attachment' === $result['type'] && ! empty( $result['attachment_id'] ) ) {
                        error_log( sprintf( '[PRS_COVER] Cover saved successfully for user %d, book %d as attachment %d.', $user_id, $book_id, (int) $result['attachment_id'] ) );
                } else {
                        error_log( sprintf( '[PRS_COVER] Cover saved successfully for user %d, book %d as direct URL.', $user_id, $book_id ) );
                }

                wp_send_json_success(
                        array(
                                'src'    => $result['src'],
                                'source' => $result['source'],
                        )
                );
        }

        protected static function persist_user_cover_choice( $user_book_id, $user_id, $book_id, $cover_url, $cover_source = '' ) {
                global $wpdb;

                $table          = $wpdb->prefix . 'politeia_user_books';
                $clean_url      = esc_url_raw( $cover_url );
                $clean_source   = $cover_source ? esc_url_raw( $cover_source ) : '';
                $attachment_id  = self::sideload_cover_attachment( $clean_url, $user_id, $user_book_id );
                $payload       = array_filter(
                        array(
                                'external_cover' => $clean_url,
                                'source'         => $clean_source,
                        )
                );
                $reference     = $attachment_id ? (string) (int) $attachment_id : ( $payload ? maybe_serialize( $payload ) : '' );
                $data          = array(
                        'cover_reference' => $reference,
                        'updated_at'      => current_time( 'mysql', true ),
                );
                $format        = array( '%s', '%s' );

                $updated = $wpdb->update(
                        $table,
                        $data,
                        array( 'id' => $user_book_id ),
                        $format,
                        array( '%d' )
                );

                if ( false === $updated ) {
                        if ( $attachment_id ) {
                                wp_delete_attachment( $attachment_id, true );
                        }

                        return new WP_Error( 'db_error', 'Database update failed.' );
                }

                if ( $attachment_id ) {
                        if ( $clean_source ) {
                                update_post_meta( $attachment_id, '_prs_cover_source', $clean_source );
                        } else {
                                delete_post_meta( $attachment_id, '_prs_cover_source' );
                        }

                        self::cleanup_cover_attachments( $user_id, $user_book_id, $attachment_id );

                        $src = wp_get_attachment_image_url( $attachment_id, 'large' );

                        return array(
                                'type'          => 'attachment',
                                'attachment_id' => $attachment_id,
                                'src'           => $src ?: '',
                                'source'        => $clean_source,
                        );
                }

                return array(
                        'type'          => 'external',
                        'attachment_id' => 0,
                        'src'           => $clean_url,
                        'source'        => $clean_source,
                );
        }

        protected static function sideload_cover_attachment( $cover_url, $user_id, $user_book_id ) {
                if ( '' === $cover_url ) {
                        return false;
                }

                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $tmp = download_url( $cover_url );

                if ( is_wp_error( $tmp ) ) {
                        error_log( sprintf( '[PRS_COVER] download_url failed for user %d, book %d: %s', $user_id, $user_book_id, $tmp->get_error_message() ) );
                        return false;
                }

                $path     = parse_url( $cover_url, PHP_URL_PATH );
                $basename = $path ? basename( $path ) : '';
                if ( ! $basename || '.' === $basename ) {
                        $basename = 'google-cover-' . $user_id . '-' . time() . '.jpg';
                }

                $file_array = array(
                        'name'     => sanitize_file_name( $basename ),
                        'tmp_name' => $tmp,
                );

                $attachment_id = media_handle_sideload(
                        $file_array,
                        0,
                        '',
                        array(
                                'post_author' => $user_id,
                        )
                );

                if ( is_wp_error( $attachment_id ) ) {
                        @unlink( $tmp );
                        error_log( sprintf( '[PRS_COVER] media_handle_sideload failed for user %d, book %d: %s', $user_id, $user_book_id, $attachment_id->get_error_message() ) );
                        return false;
                }

                $key = self::build_cover_key( $user_id, $user_book_id );
                update_post_meta( $attachment_id, '_prs_cover_user_id', $user_id );
                if ( $user_book_id ) {
                        update_post_meta( $attachment_id, '_prs_cover_user_book_id', $user_book_id );
                        update_post_meta( $attachment_id, '_prs_cover_key', $key );
                }

                return (int) $attachment_id;
        }

        protected static function cleanup_cover_attachments( $user_id, $user_book_id, $keep_attachment_id ) {
                if ( ! $user_book_id ) {
                        return;
                }

                $key    = self::build_cover_key( $user_id, $user_book_id );
                $args   = array(
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
                );
                if ( $keep_attachment_id ) {
                        $args['exclude'] = array( (int) $keep_attachment_id );
                }

                $others = get_posts( $args );
                foreach ( $others as $oid ) {
                        if ( $keep_attachment_id && (int) $keep_attachment_id === (int) $oid ) {
                                continue;
                        }
                        wp_delete_attachment( $oid, true );
                }
        }

        protected static function build_cover_key( $user_id, $user_book_id ) {
                return 'u' . (int) $user_id . 'ub' . (int) $user_book_id;
        }

        public static function parse_cover_value( $raw ) {
                $data = array(
                        'attachment_id' => 0,
                        'url'           => '',
                        'source'        => '',
                );

                if ( $raw instanceof WP_Post ) {
                        if ( isset( $raw->cover_reference ) ) {
                                $raw = $raw->cover_reference;
                        } elseif ( isset( $raw->cover_attachment_id_user ) ) {
                                $raw = $raw->cover_attachment_id_user;
                        } else {
                                $raw = '';
                        }
                }

                if ( is_array( $raw ) ) {
                        $raw = maybe_serialize( $raw );
                }

                if ( is_numeric( $raw ) && ! is_string( $raw ) ) {
                        $raw = (string) (int) $raw;
                }

                if ( is_string( $raw ) ) {
                        $raw = trim( $raw );

                        if ( '' === $raw ) {
                                return $data;
                        }

                        if ( is_numeric( $raw ) && (int) $raw > 0 ) {
                                $data['attachment_id'] = (int) $raw;
                                return $data;
                        }

                        if ( 0 === strpos( $raw, 'attachment:' ) ) {
                                $maybe_id = trim( substr( $raw, strlen( 'attachment:' ) ) );
                                if ( is_numeric( $maybe_id ) ) {
                                        $data['attachment_id'] = (int) $maybe_id;
                                        return $data;
                                }
                        }

                        if ( 0 === strpos( $raw, 'url:' ) ) {
                                $data['url'] = esc_url_raw( substr( $raw, 4 ) );
                                return $data;
                        }

                        if ( filter_var( $raw, FILTER_VALIDATE_URL ) ) {
                                $data['url'] = esc_url_raw( $raw );
                                return $data;
                        }

                        $maybe = maybe_unserialize( $raw );
                        if ( is_array( $maybe ) ) {
                                if ( isset( $maybe['attachment_id'] ) && is_numeric( $maybe['attachment_id'] ) ) {
                                        $data['attachment_id'] = (int) $maybe['attachment_id'];
                                }
                                if ( isset( $maybe['external_cover'] ) ) {
                                        $data['url'] = esc_url_raw( (string) $maybe['external_cover'] );
                                }
                                if ( isset( $maybe['source'] ) ) {
                                        $data['source'] = esc_url_raw( (string) $maybe['source'] );
                                }
                                if ( isset( $maybe['path'] ) ) {
                                        $data['url'] = esc_url_raw( (string) $maybe['path'] );
                                }
                                return $data;
                        }

                        $json = json_decode( $raw, true );
                        if ( is_array( $json ) ) {
                                if ( isset( $json['attachment_id'] ) && is_numeric( $json['attachment_id'] ) ) {
                                        $data['attachment_id'] = (int) $json['attachment_id'];
                                }
                                if ( isset( $json['url'] ) ) {
                                        $data['url'] = esc_url_raw( (string) $json['url'] );
                                }
                                if ( isset( $json['source'] ) ) {
                                        $data['source'] = esc_url_raw( (string) $json['source'] );
                                }
                                if ( isset( $json['external_cover'] ) && '' === $data['url'] ) {
                                        $data['url'] = esc_url_raw( (string) $json['external_cover'] );
                                }
                        }
                }

                return $data;
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
                        'q'          => $query,
                        'maxResults' => 15,
                        'printType'  => 'books',
                        'key'        => $api_key,
                );

                if ( $language ) {
                        $args['langRestrict'] = $language;
                }

                self::log_debug(
                        'google_cover_search_request',
                        array(
                                'query'    => $query,
                                'language' => $language,
                                'params'   => array(
                                        'maxResults'  => $args['maxResults'],
                                        'printType'   => $args['printType'],
                                        'langRestrict' => isset( $args['langRestrict'] ) ? $args['langRestrict'] : '',
                                ),
                        )
                );

                $url      = add_query_arg( $args, 'https://www.googleapis.com/books/v1/volumes' );
                $response = wp_remote_get(
                        $url,
                        array(
                                'timeout' => 10,
                        )
                );

                if ( is_wp_error( $response ) ) {
                        self::log_debug(
                                'google_cover_search_response_error',
                                array(
                                        'query'   => $query,
                                        'message' => $response->get_error_message(),
                                )
                        );
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
                        self::log_debug(
                                'google_cover_search_response_error',
                                array(
                                        'query'   => $query,
                                        'code'    => $code,
                                        'message' => $message,
                                )
                        );
                        wp_send_json_error( array( 'message' => $message ), $code );
                }

                $total_candidates  = ( isset( $data['items'] ) && is_array( $data['items'] ) ) ? count( $data['items'] ) : 0;
                $accepted_count    = 0;
                $rejected_count    = 0;
                $accepted_sources  = array();

                if ( empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
                        self::log_debug(
                                'google_cover_search_summary',
                                array(
                                        'query'            => $query,
                                        'language'         => $language,
                                        'total_candidates' => $total_candidates,
                                        'accepted'         => 0,
                                        'result'           => 'no_items',
                                )
                        );
                        self::log_cover_summary_line( $title, $author, $total_candidates, 0 );
                        wp_send_json_error( array( 'message' => 'no_results' ), 404 );
                }

                $preferred = array();
                $fallback  = array();
                $seen      = array();

                foreach ( $data['items'] as $entry ) {
                        if ( ! is_array( $entry ) ) {
                                $rejected_count++;
                                continue;
                        }

                        $volume           = isset( $entry['volumeInfo'] ) && is_array( $entry['volumeInfo'] ) ? $entry['volumeInfo'] : array();
                        $vol_title        = isset( $volume['title'] ) ? (string) $volume['title'] : '';
                        $vol_subtitle     = isset( $volume['subtitle'] ) ? (string) $volume['subtitle'] : '';
                        $candidate_title  = $vol_title;
                        if ( $vol_subtitle ) {
                                $candidate_title .= ' ' . $vol_subtitle;
                        }

                        if ( '' === $vol_title ) {
                                self::log_debug(
                                        'google_cover_candidate_skip',
                                        array(
                                                'id'     => isset( $entry['id'] ) ? (string) $entry['id'] : '',
                                                'reason' => 'missing_title',
                                        )
                                );
                                $rejected_count++;
                                continue;
                        }

                        $similarity = self::title_similarity( $title, $candidate_title );
                        if ( $similarity < 0.5 ) {
                                self::log_debug(
                                        'google_cover_candidate_skip',
                                        array(
                                                'id'         => isset( $entry['id'] ) ? (string) $entry['id'] : '',
                                                'title'      => $vol_title,
                                                'candidate'  => $candidate_title,
                                                'similarity' => $similarity,
                                                'reason'     => 'low_similarity',
                                        )
                                );
                                $rejected_count++;
                                continue;
                        }

                        if ( $similarity < 0.8 ) {
                                error_log( sprintf( "📚 Overlap for '%s' vs '%s' = %.3f", $title, $candidate_title, $similarity ) );
                        }

                        $links = isset( $volume['imageLinks'] ) && is_array( $volume['imageLinks'] ) ? $volume['imageLinks'] : array();
                        $best  = self::best_image_link( $links );
                        if ( '' === $best ) {
                                self::log_debug(
                                        'google_cover_candidate_skip',
                                        array(
                                                'id'     => isset( $entry['id'] ) ? (string) $entry['id'] : '',
                                                'title'  => $vol_title,
                                                'reason' => 'missing_image_link',
                                        )
                                );
                                $rejected_count++;
                                continue;
                        }

                        $cover_url = self::sanitize_google_image_url( $best );
                        if ( '' === $cover_url ) {
                                self::log_debug(
                                        'google_cover_candidate_skip',
                                        array(
                                                'id'     => isset( $entry['id'] ) ? (string) $entry['id'] : '',
                                                'title'  => $vol_title,
                                                'reason' => 'invalid_image_host',
                                        )
                                );
                                $rejected_count++;
                                continue;
                        }

                        $info_sources = array();
                        if ( isset( $volume['infoLink'] ) ) {
                                $info_sources[] = (string) $volume['infoLink'];
                        }
                        if ( isset( $volume['previewLink'] ) ) {
                                $info_sources[] = (string) $volume['previewLink'];
                        }
                        if ( isset( $volume['canonicalVolumeLink'] ) ) {
                                $info_sources[] = (string) $volume['canonicalVolumeLink'];
                        }

                        $info_link = '';
                        foreach ( $info_sources as $candidate_link ) {
                                $info_link = self::sanitize_google_info_link( $candidate_link );
                                if ( '' !== $info_link ) {
                                        break;
                                }
                        }
                        if ( '' === $info_link ) {
                                self::log_debug(
                                        'google_cover_candidate_skip',
                                        array(
                                                'id'     => isset( $entry['id'] ) ? (string) $entry['id'] : '',
                                                'title'  => $vol_title,
                                                'reason' => 'invalid_info_link',
                                        )
                                );
                                $rejected_count++;
                                continue;
                        }

                        $info_host = strtolower( (string) wp_parse_url( $info_link, PHP_URL_HOST ) );
                        if ( $info_host ) {
                                $accepted_sources[ $info_host ] = true;
                        }

                        if ( isset( $seen[ $cover_url ] ) ) {
                                self::log_debug(
                                        'google_cover_candidate_skip',
                                        array(
                                                'id'     => isset( $entry['id'] ) ? (string) $entry['id'] : '',
                                                'title'  => $vol_title,
                                                'reason' => 'duplicate_image',
                                        )
                                );
                                $rejected_count++;
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

                        self::log_debug(
                                'google_cover_candidate_accepted',
                                array(
                                        'id'         => isset( $entry['id'] ) ? (string) $entry['id'] : '',
                                        'title'      => $vol_title,
                                        'candidate'  => $candidate_title,
                                        'similarity' => $similarity,
                                        'language'   => $vol_language,
                                        'source_host'=> $info_host,
                                )
                        );
                        $accepted_count++;
                }

                $rejected_count = max( $rejected_count, max( $total_candidates - $accepted_count, 0 ) );

                $items = array_merge( $preferred, $fallback );
                if ( empty( $items ) ) {
                        self::log_debug(
                                'google_cover_search_summary',
                                array(
                                        'query'            => $query,
                                        'language'         => $language,
                                        'total_candidates' => $total_candidates,
                                        'accepted'         => 0,
                                        'result'           => 'filtered_out',
                                )
                        );
                        self::log_cover_summary_line( $title, $author, $total_candidates, 0 );
                        wp_send_json_error( array( 'message' => 'no_results' ), 404 );
                }

                $items = array_slice( $items, 0, 3 );

                self::log_debug(
                        'google_cover_search_summary',
                        array(
                                'query'            => $query,
                                'language'         => $language,
                                'total_candidates' => $total_candidates,
                                'accepted'         => count( $items ),
                                'accepted_total'   => $accepted_count,
                                'preferred_count'  => count( $preferred ),
                                'fallback_count'   => count( $fallback ),
                        )
                );
                self::log_cover_summary_line( $title, $author, $total_candidates, $accepted_count, array_keys( $accepted_sources ) );

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
                $title = trim( $title );

                if ( '' === $title ) {
                        return '';
                }

                $words = preg_split( '/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY );
                if ( empty( $words ) ) {
                        return '';
                }

                static $stopwords = null;
                if ( null === $stopwords ) {
                        $stopwords = array(
                                'a', 'an', 'and', 'the', 'of', 'for', 'to', 'in', 'on', 'at', 'by', 'with', 'de', 'del', 'la', 'el',
                                'los', 'las', 'una', 'un', 'unas', 'unos', 'y', 'en', 'por', 'para', 'con', 'da', 'do', 'das', 'dos',
                        );
                }

                $words = array_values(
                        array_filter(
                                $words,
                                static function ( $word ) use ( $stopwords ) {
                                        return '' !== $word && ! in_array( $word, $stopwords, true );
                                }
                        )
                );

                return implode( ' ', $words );
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

        private static function is_allowed_google_host( $host, ?array $needles = null ) {
                if ( '' === $host ) {
                        return false;
                }

                $host = strtolower( $host );
                if ( null === $needles ) {
                        $needles = array( 'books.google', 'googleusercontent.com', 'ggpht.com', 'play.google' );
                }

                foreach ( $needles as $needle ) {
                        if ( false !== strpos( $host, $needle ) ) {
                                return true;
                        }
                }

                return false;
        }

        private static function log_debug( $event, array $context = array() ) {
                if ( ! $event ) {
                        return;
                }

                $uploads = wp_upload_dir();
                if ( empty( $uploads['basedir'] ) ) {
                        return;
                }

                $dir  = trailingslashit( $uploads['basedir'] );
                $path = $dir . 'politeia-debug.log';

                if ( ! is_dir( $dir ) ) {
                        wp_mkdir_p( $dir );
                }

                $record = array(
                        'time'    => current_time( 'mysql' ),
                        'event'   => $event,
                        'context' => $context,
                );

                $line = wp_json_encode( $record );
                if ( false === $line ) {
                        return;
                }

                file_put_contents( $path, $line . PHP_EOL, FILE_APPEND | LOCK_EX );
        }

        private static function log_cover_summary_line( $title, $author, $found, $accepted, array $sources = array() ) {
                $uploads = wp_upload_dir();
                if ( empty( $uploads['basedir'] ) ) {
                        return;
                }

                $dir  = trailingslashit( $uploads['basedir'] );
                $path = $dir . 'politeia-debug.log';

                if ( ! is_dir( $dir ) ) {
                        wp_mkdir_p( $dir );
                }

                $clean_title  = self::sanitize_log_field( $title );
                $clean_author = self::sanitize_log_field( $author );
                $sources = array_filter(
                        array_map(
                                array( __CLASS__, 'sanitize_log_field' ),
                                array_unique( $sources )
                        )
                );

                $source_field = '';
                if ( ! empty( $sources ) ) {
                        $source_field = sprintf( ' Source=%s', implode( ',', $sources ) );
                }

                $message = sprintf(
                        '[BooksCoverSearch] Title="%s" Author="%s" Found=%d Accepted=%d%s',
                        $clean_title,
                        $clean_author,
                        (int) $found,
                        (int) $accepted,
                        $source_field
                );

                file_put_contents( $path, $message . PHP_EOL, FILE_APPEND | LOCK_EX );
        }

        private static function sanitize_log_field( $value ) {
                $value = is_scalar( $value ) ? (string) $value : '';
                $value = wp_strip_all_tags( $value );
                $value = preg_replace( '/["\r\n]+/', '', $value );

                return trim( $value );
        }

        private static function best_image_link( array $links ) {
                $order = array( 'extraLarge', 'large', 'medium', 'small', 'thumbnail', 'smallThumbnail' );
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
                $host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
                if ( empty( $host ) || ! self::is_allowed_google_host( $host, array( 'books.google', 'googleusercontent.com', 'ggpht.com' ) ) ) {
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
                $host  = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
                $path  = isset( $parts['path'] ) ? strtolower( (string) $parts['path'] ) : '';

                if ( '' === $host ) {
                        return '';
                }

                $books_pattern = '/(^|\.)books\.google\.(com|cl|es|co|com\.ar|com\.mx)$/';
                $play_pattern  = '/(^|\.)play\.google\.(com|cl|es|co|com\.ar|com\.mx)$/';

                $is_allowed = false;

                if ( preg_match( $books_pattern, $host ) ) {
                        $is_allowed = true;
                } elseif ( preg_match( $play_pattern, $host ) ) {
                        $is_allowed = true;
                } elseif ( in_array( $host, array( 'google.com', 'www.google.com' ), true ) && 0 === strpos( $path, '/books/' ) ) {
                        $is_allowed = true;
                }

                if ( ! $is_allowed ) {
                        return '';
                }

                return esc_url_raw( $url );
        }
}

$prs_cover_upload_bootstrap = static function () {
	PRS_Cover_Upload_Feature::init();
};

if ( did_action( 'plugins_loaded' ) ) {
	$prs_cover_upload_bootstrap();
} else {
	add_action( 'plugins_loaded', $prs_cover_upload_bootstrap );
}
