<?php
/**
 * Plugin Name: Politeia Bookshelf
 * Description: Unifica Politeia Reading y Politeia ChatGPT en un solo plugin modular.
 * Version: 0.1.0
 * Author: Nicolás Pavez
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Autoload de Composer.
require_once __DIR__ . '/vendor/autoload.php';

// Inicializar módulos.
Politeia\Reading\Init::register();
Politeia\ChatGPT\Init::register();

/**
 * Ensure Google Books cover columns exist on activation.
 */
if ( ! function_exists( 'politeia_bookshelf_check_cover_columns' ) ) {
    /**
     * Checks and adds missing cover columns to the politeia_user_books table.
     *
     * @return void
     */
    function politeia_bookshelf_check_cover_columns() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'politeia_user_books';

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        if ( $table_exists !== $table_name ) {
            error_log( sprintf( '[PRS_COVER] Table %s not found during schema check.', $table_name ) );
            return;
        }

        $columns = $wpdb->get_col( "DESC {$table_name}", 0 );

        if ( ! in_array( 'cover_url', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN cover_url VARCHAR(255) NULL AFTER cover_attachment_id_user" );
            error_log( '[PRS_COVER] Added missing column: cover_url' );
        }

        if ( ! in_array( 'cover_source', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN cover_source VARCHAR(255) NULL AFTER cover_url" );
            error_log( '[PRS_COVER] Added missing column: cover_source' );
        }

        error_log( '[PRS_COVER] Database schema check completed successfully.' );
    }
}

register_activation_hook( __FILE__, 'politeia_bookshelf_check_cover_columns' );

/**
 * Register Politeia Bookshelf admin menu.
 */
function politeia_bookshelf_register_menu() {
    add_menu_page(
        __( 'Politeia Bookshelf', 'politeia-bookshelf' ),
        __( 'Politeia Bookshelf', 'politeia-bookshelf' ),
        'manage_options',
        'politeia-bookshelf',
        '',
        'dashicons-book-alt',
        6
    );

    add_submenu_page(
        'politeia-bookshelf',
        __( 'API Token', 'politeia-bookshelf' ),
        __( 'API Token', 'politeia-bookshelf' ),
        'manage_options',
        'politeia-bookshelf-api-token',
        'politeia_bookshelf_api_token_page'
    );
}
add_action( 'admin_menu', 'politeia_bookshelf_register_menu' );

/**
 * Register Politeia Bookshelf settings.
 */
function politeia_bookshelf_register_settings() {
    register_setting(
        'politeia_bookshelf_options_group',
        'politeia_bookshelf_google_api_key',
        array(
            'sanitize_callback' => 'politeia_bookshelf_validate_api_key',
        )
    );
}
add_action( 'admin_init', 'politeia_bookshelf_register_settings' );

/**
 * Render API token admin page.
 */
function politeia_bookshelf_api_token_page() {
    $api_key = esc_attr( get_option( 'politeia_bookshelf_google_api_key' ) );
    $status  = get_transient( 'politeia_bookshelf_api_status' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Politeia Bookshelf — API Token', 'politeia-bookshelf' ); ?></h1>
        <p><?php esc_html_e( 'Enter your Google Books API key below. The key will be verified before saving.', 'politeia-bookshelf' ); ?></p>

        <?php if ( $status ) : ?>
            <div class="<?php echo esc_attr( $status['class'] ); ?>" style="margin: 15px 0; padding: 10px;">
                <?php echo esc_html( $status['message'] ); ?>
            </div>
        <?php delete_transient( 'politeia_bookshelf_api_status' ); endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'politeia_bookshelf_options_group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Google Books API Key', 'politeia-bookshelf' ); ?></th>
                    <td>
                        <input type="text" name="politeia_bookshelf_google_api_key" value="<?php echo $api_key; ?>" size="60" placeholder="<?php echo esc_attr__( 'Paste your Google Books API key here', 'politeia-bookshelf' ); ?>" />
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Token', 'politeia-bookshelf' ) ); ?>
        </form>
    </div>
    <?php
}

/**
 * Validate Google Books API key during sanitization.
 *
 * @param string $input API key input.
 * @return string
 */
function politeia_bookshelf_validate_api_key( $input ) {
    $api_key = trim( sanitize_text_field( $input ) );

    if ( empty( $api_key ) ) {
        set_transient(
            'politeia_bookshelf_api_status',
            array(
                'class'   => 'notice notice-error',
                'message' => __( 'API key field cannot be empty.', 'politeia-bookshelf' ),
            ),
            30
        );
        return '';
    }

    $test_url = sprintf(
        'https://www.googleapis.com/books/v1/volumes?q=test&maxResults=1&key=%s',
        rawurlencode( $api_key )
    );

    $response = wp_remote_get( $test_url );

    if ( is_wp_error( $response ) ) {
        set_transient(
            'politeia_bookshelf_api_status',
            array(
                'class'   => 'notice notice-error',
                'message' => __( 'Connection to Google Books API failed. Please check your server connectivity.', 'politeia-bookshelf' ),
            ),
            30
        );
        return '';
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $body['error'] ) ) {
        $reason = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Invalid API key.', 'politeia-bookshelf' );

        set_transient(
            'politeia_bookshelf_api_status',
            array(
                'class'   => 'notice notice-error',
                'message' => sprintf(
                    /* translators: %s: Google API error reason */
                    __( 'Google API validation failed: %s', 'politeia-bookshelf' ),
                    $reason
                ),
            ),
            30
        );
        return '';
    }

    set_transient(
        'politeia_bookshelf_api_status',
        array(
            'class'   => 'notice notice-success',
            'message' => __( '✅ Google Books API key verified successfully!', 'politeia-bookshelf' ),
        ),
        30
    );

    return $api_key;
}
