<?php
/**
 * Feature: Upload Book Cover (modal + crop/zoom centrado)
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PRS_Cover_Upload_Feature {

    private static $allowed_providers = array( 'openlibrary', 'googlebooks' );
    private static $has_cover_url_user_column = null;

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
                'providers'   => self::$allowed_providers,
            )
        );
    }

    public static function shortcode_button( $atts ) {
        // Botón compacto para insertar sobre la portada
        return '<button type="button" id="prs-cover-open" class="prs-btn prs-cover-btn">Upload Book Cover</button>';
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

    public static function ajax_fetch_remote() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'auth' ), 401 );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'prs_cover_fetch_remote' ) ) {
            wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
        }

        $user_id      = get_current_user_id();
        $user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
        $book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
        $raw_isbns    = isset( $_POST['isbns'] ) ? wp_unslash( $_POST['isbns'] ) : array();
        $raw_provider = isset( $_POST['providers'] ) ? wp_unslash( $_POST['providers'] ) : array();

        if ( ! $user_book_id || ! $book_id ) {
            wp_send_json_error( array( 'message' => 'missing_params' ), 400 );
        }

        global $wpdb;
        $t   = $wpdb->prefix . 'politeia_user_books';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$t} WHERE id=%d AND user_id=%d AND book_id=%d LIMIT 1",
                $user_book_id,
                $user_id,
                $book_id
            )
        );
        if ( ! $row ) {
            wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
        }

        $isbns = self::parse_isbn_list( $raw_isbns );
        if ( empty( $isbns ) ) {
            wp_send_json_error( array( 'message' => 'no_isbn' ), 400 );
        }

        $providers = self::parse_provider_list( $raw_provider );
        $result    = null;

        foreach ( $providers as $provider ) {
            if ( 'openlibrary' === $provider ) {
                $result = self::fetch_from_open_library( $isbns );
            } elseif ( 'googlebooks' === $provider ) {
                $result = self::fetch_from_google_books( $isbns );
            } else {
                continue;
            }

            if ( $result && ! empty( $result['url'] ) ) {
                break;
            }
        }

        if ( ! $result || empty( $result['url'] ) ) {
            wp_send_json_error( array( 'message' => 'not_found' ), 404 );
        }

        $url = esc_url_raw( $result['url'] );
        if ( ! $url || ! self::validate_remote_image_url( $url ) ) {
            wp_send_json_error( array( 'message' => 'invalid_image' ), 400 );
        }

        $key    = 'u' . $user_id . 'ub' . $user_book_id;
        $others = get_posts(
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
        foreach ( $others as $oid ) {
            wp_delete_attachment( $oid, true );
        }

        $set_parts = array( 'cover_attachment_id_user = NULL', 'updated_at = %s' );
        $params    = array( current_time( 'mysql', true ) );

        if ( self::user_books_supports_cover_url_user() ) {
            $set_parts[] = 'cover_url_user = %s';
            $params[]    = $url;
        }

        $params[] = $user_book_id;
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . $t . ' SET ' . implode( ', ', $set_parts ) . ' WHERE id = %d',
                $params
            )
        );

        wp_send_json_success(
            array(
                'url'    => esc_url( $url ),
                'source' => isset( $result['source'] ) ? $result['source'] : '',
            )
        );
    }

    private static function parse_isbn_list( $input ) {
        $list = array();

        if ( is_array( $input ) ) {
            $list = $input;
        } elseif ( is_string( $input ) && $input !== '' ) {
            $decoded = json_decode( $input, true );
            if ( is_array( $decoded ) ) {
                $list = $decoded;
            } else {
                $list = preg_split( '/[\s,;|]+/', $input );
            }
        }

        $isbns = array();
        foreach ( (array) $list as $isbn ) {
            $isbn = strtoupper( preg_replace( '/[^0-9X]/i', '', (string) $isbn ) );
            if ( '' === $isbn ) {
                continue;
            }
            $isbns[] = $isbn;
        }

        $isbns = array_values( array_unique( $isbns ) );
        if ( count( $isbns ) > 10 ) {
            $isbns = array_slice( $isbns, 0, 10 );
        }

        return $isbns;
    }

    private static function parse_provider_list( $input ) {
        $default = self::$allowed_providers;
        $list    = array();

        if ( is_array( $input ) ) {
            $list = $input;
        } elseif ( is_string( $input ) && $input !== '' ) {
            $decoded = json_decode( $input, true );
            if ( is_array( $decoded ) ) {
                $list = $decoded;
            } else {
                $list = preg_split( '/[\s,;|]+/', $input );
            }
        }

        if ( ! $list ) {
            return $default;
        }

        $list = array_map( 'strtolower', array_map( 'trim', (array) $list ) );
        $list = array_values( array_intersect( $list, $default ) );

        return $list ? $list : $default;
    }

    private static function fetch_from_open_library( $isbns ) {
        foreach ( (array) $isbns as $isbn ) {
            if ( '' === $isbn ) {
                continue;
            }
            $url = sprintf( 'https://covers.openlibrary.org/b/isbn/%s-L.jpg?default=false', rawurlencode( $isbn ) );
            if ( self::validate_remote_image_url( $url ) ) {
                return array(
                    'url'    => $url,
                    'source' => 'openlibrary',
                );
            }
        }

        return null;
    }

    private static function fetch_from_google_books( $isbns ) {
        foreach ( (array) $isbns as $isbn ) {
            if ( '' === $isbn ) {
                continue;
            }

            $url      = add_query_arg(
                array(
                    'q'         => 'isbn:' . rawurlencode( $isbn ),
                    'maxResults' => 5,
                    'printType' => 'books',
                ),
                'https://www.googleapis.com/books/v1/volumes'
            );
            $response = wp_remote_get(
                $url,
                array(
                    'timeout'     => 10,
                    'redirection' => 3,
                )
            );

            if ( is_wp_error( $response ) ) {
                continue;
            }

            if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                continue;
            }

            $body = wp_remote_retrieve_body( $response );
            if ( ! $body ) {
                continue;
            }

            $data = json_decode( $body, true );
            if ( ! is_array( $data ) || empty( $data['items'] ) ) {
                continue;
            }

            foreach ( (array) $data['items'] as $item ) {
                if ( empty( $item['volumeInfo']['imageLinks'] ) || ! is_array( $item['volumeInfo']['imageLinks'] ) ) {
                    continue;
                }
                $links = $item['volumeInfo']['imageLinks'];
                foreach ( array( 'extraLarge', 'large', 'medium', 'thumbnail', 'small', 'smallThumbnail' ) as $key ) {
                    if ( empty( $links[ $key ] ) ) {
                        continue;
                    }
                    $maybe = esc_url_raw( $links[ $key ] );
                    if ( ! $maybe ) {
                        continue;
                    }
                    if ( self::validate_remote_image_url( $maybe ) ) {
                        return array(
                            'url'    => $maybe,
                            'source' => 'googlebooks',
                        );
                    }
                }
            }
        }

        return null;
    }

    private static function validate_remote_image_url( $url ) {
        $url = esc_url_raw( $url );
        if ( ! $url ) {
            return false;
        }

        static $cache = array();
        $key = md5( $url );
        if ( array_key_exists( $key, $cache ) ) {
            return $cache[ $key ];
        }

        $args     = array(
            'timeout'     => 10,
            'redirection' => 3,
        );
        $response = wp_remote_head( $url, $args );
        $code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) || in_array( $code, array( 400, 403, 405 ), true ) ) {
            $get_args = $args;
            $get_args['limit_response_size'] = 1024;
            $get_args['headers']            = array( 'Range' => 'bytes=0-0' );
            $response                       = wp_remote_get( $url, $get_args );
            $code                            = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        }

        if ( is_wp_error( $response ) || 200 !== $code ) {
            $cache[ $key ] = false;
            return false;
        }

        $type = wp_remote_retrieve_header( $response, 'content-type' );
        if ( is_array( $type ) ) {
            $type = reset( $type );
        }
        $type = (string) $type;
        if ( '' === $type || stripos( $type, 'image/' ) !== 0 ) {
            $cache[ $key ] = false;
            return false;
        }

        $length = wp_remote_retrieve_header( $response, 'content-length' );
        if ( is_array( $length ) ) {
            $length = reset( $length );
        }
        if ( '' !== $length && null !== $length && (int) $length <= 0 ) {
            $cache[ $key ] = false;
            return false;
        }

        $cache[ $key ] = true;
        return true;
    }

    private static function user_books_supports_cover_url_user() {
        if ( null !== self::$has_cover_url_user_column ) {
            return self::$has_cover_url_user_column;
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'politeia_user_books';
        $query  = $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'cover_url_user' );
        $result = $wpdb->get_var( $query );

        self::$has_cover_url_user_column = ! empty( $result );
        return self::$has_cover_url_user_column;
    }
}
PRS_Cover_Upload_Feature::init();
