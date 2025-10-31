<?php
/**
 * Google Books API settings for Politeia Bookshelf.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION' ) ) {
    define( 'POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION', 'politeia_bookshelf_google_api_key' );
}

if ( ! function_exists( 'politeia_bookshelf_get_google_books_api_key' ) ) {
    /**
     * Retrieve the stored Google Books API key, falling back to the legacy option name.
     *
     * @return string
     */
    function politeia_bookshelf_get_google_books_api_key() {
        $api_key = get_option( POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION, '' );

        if ( '' === $api_key ) {
            $legacy = get_option( 'politeia_google_books_api_key', '' );
            if ( is_string( $legacy ) && '' !== $legacy ) {
                $api_key = $legacy;
            }
        }

        return is_string( $api_key ) ? $api_key : '';
    }
}

/**
 * Register Google Books API settings section and field.
 */
function politeia_bookshelf_register_google_books_settings() {
    register_setting(
        'politeia_bookshelf_google_books',
        POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION,
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]
    );

    add_settings_section(
        'politeia_bookshelf_google_books_section',
        __( 'Google Books API configuration', 'politeia-bookshelf' ),
        'politeia_bookshelf_render_google_books_section_intro',
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
 * Keep the legacy option name in sync to avoid breaking existing consumers.
 *
 * @param string $old_value Previous option value.
 * @param string $value     New option value.
 *
 * @return void
 */
function politeia_bookshelf_sync_legacy_google_books_option( $old_value, $value ) {
    update_option( 'politeia_google_books_api_key', $value );
}
add_action( 'update_option_' . POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION, 'politeia_bookshelf_sync_legacy_google_books_option', 10, 2 );

/**
 * Mirror the stored value to the legacy option when it is added for the first time.
 *
 * @param string $option Option name (ignored).
 * @param string $value  Saved value.
 */
function politeia_bookshelf_sync_legacy_google_books_option_on_add( $option, $value ) {
    update_option( 'politeia_google_books_api_key', $value );
}
add_action( 'add_option_' . POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION, 'politeia_bookshelf_sync_legacy_google_books_option_on_add', 10, 2 );

/**
 * Output the description for the Google Books settings section.
 */
function politeia_bookshelf_render_google_books_section_intro() {
    echo '<p>' . esc_html__( 'Provide the Google Books API key generated in Google Cloud to enable Google Books requests.', 'politeia-bookshelf' ) . '</p>';
}

/**
 * Render the API key field.
 */
function politeia_bookshelf_render_google_books_api_key_field() {
    $api_key = politeia_bookshelf_get_google_books_api_key();
    printf(
        '<input type="text" name="%1$s" value="%2$s" class="regular-text" autocomplete="off" />',
        esc_attr( POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION ),
        esc_attr( $api_key )
    );
    echo '<p class="description">' . esc_html__( 'Paste the token from the Google Cloud Console. Only administrators can view or modify this value.', 'politeia-bookshelf' ) . '</p>';
}

/**
 * Render the Politeia Bookshelf admin page with navigation tabs.
 */
function politeia_bookshelf_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'politeia-bookshelf';
    $current_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';

    if ( 'politeia-bookshelf-google-books' === $current_page ) {
        $current_tab = 'google-books';
    }

    $tabs = array(
        'overview'     => __( 'Overview', 'politeia-bookshelf' ),
        'google-books' => __( 'Google Books API', 'politeia-bookshelf' ),
    );

    if ( ! array_key_exists( $current_tab, $tabs ) ) {
        $current_tab = 'overview';
    }

    $base_url      = admin_url( 'admin.php?page=politeia-bookshelf' );
    $overview_url  = $base_url;
    $google_url    = add_query_arg( 'tab', 'google-books', $base_url );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Politeia Bookshelf', 'politeia-bookshelf' ); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url( $overview_url ); ?>" class="nav-tab <?php echo 'overview' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tabs['overview'] ); ?></a>
            <a href="<?php echo esc_url( $google_url ); ?>" class="nav-tab <?php echo 'google-books' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tabs['google-books'] ); ?></a>
        </h2>

        <?php if ( 'google-books' === $current_tab ) : ?>
            <?php settings_errors( 'politeia_bookshelf_google_books' ); ?>
            <form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
                <?php
                settings_fields( 'politeia_bookshelf_google_books' );
                do_settings_sections( 'politeia_bookshelf_google_books' );
                submit_button();
                ?>
            </form>
        <?php else : ?>
            <p><?php esc_html_e( 'Use the tabs above to configure the Politeia Bookshelf features.', 'politeia-bookshelf' ); ?></p>
            <p><?php esc_html_e( 'The Google Books API tab lets you store the API token that powers cover lookups across the plugin.', 'politeia-bookshelf' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
