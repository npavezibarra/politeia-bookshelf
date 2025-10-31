<?php
/**
 * Google Books API settings for Politeia Bookshelf.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register Google Books API settings section and field.
 */
function politeia_bookshelf_register_google_books_settings() {
    register_setting(
        'politeia_bookshelf_google_books',
        'politeia_bookshelf_google_books_api_key',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]
    );

    add_settings_section(
        'politeia_bookshelf_google_books_section',
        __( 'Google Books API configuration', 'politeia-bookshelf' ),
        function () {
            echo '<p>' . esc_html__( 'Provide the Google Books API key generated in Google Cloud to enable Google Books requests.', 'politeia-bookshelf' ) . '</p>';
        },
        'politeia_bookshelf_google_books'
    );

    add_settings_field(
        'politeia_bookshelf_google_books_api_key_field',
        __( 'API Key', 'politeia-bookshelf' ),
        'politeia_bookshelf_render_google_books_api_key_field',
        'politeia_bookshelf_google_books',
        'politeia_bookshelf_google_books_section'
    );
}
add_action( 'admin_init', 'politeia_bookshelf_register_google_books_settings' );

/**
 * Render the API key field.
 */
function politeia_bookshelf_render_google_books_api_key_field() {
    $api_key = get_option( 'politeia_bookshelf_google_books_api_key', '' );
    printf(
        '<input type="text" name="politeia_bookshelf_google_books_api_key" value="%s" class="regular-text" autocomplete="off" />',
        esc_attr( $api_key )
    );
    echo '<p class="description">' . esc_html__( 'Paste the token from the Google Cloud Console. Only administrators can view or modify this value.', 'politeia-bookshelf' ) . '</p>';
}

/**
 * Register the Google Books API submenu page under Politeia Bookshelf.
 */
function politeia_bookshelf_add_google_books_submenu() {
    add_submenu_page(
        'politeia-bookshelf',
        __( 'Google Books API', 'politeia-bookshelf' ),
        __( 'Google Books API', 'politeia-bookshelf' ),
        'manage_options',
        'politeia-bookshelf-google-books',
        'politeia_bookshelf_render_google_books_page'
    );
}
add_action( 'admin_menu', 'politeia_bookshelf_add_google_books_submenu' );

/**
 * Render the Google Books settings page markup.
 */
function politeia_bookshelf_render_google_books_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Google Books API', 'politeia-bookshelf' ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'politeia_bookshelf_google_books' );
            do_settings_sections( 'politeia_bookshelf_google_books' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
