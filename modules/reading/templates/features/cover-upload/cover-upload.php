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

                // Datos para AJAX.
                $ajax_url       = admin_url( 'admin-ajax.php' );
                $crop_nonce     = wp_create_nonce( 'prs_cover_save_crop' );
                $save_nonce     = wp_create_nonce( 'prs_cover_nonce' );
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



}

$prs_cover_upload_bootstrap = static function () {
	PRS_Cover_Upload_Feature::init();
};

if ( did_action( 'plugins_loaded' ) ) {
	$prs_cover_upload_bootstrap();
} else {
	add_action( 'plugins_loaded', $prs_cover_upload_bootstrap );
}
