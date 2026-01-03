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
if ( ! defined( 'POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION' ) ) {
    define( 'POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION', 'politeia_bookshelf_force_http_covers' );
}
if ( ! defined( 'POLITEIA_BOOKSHELF_TEMPLATES_OPTION' ) ) {
    define( 'POLITEIA_BOOKSHELF_TEMPLATES_OPTION', 'politeia_bookshelf_page_templates' );
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

if ( ! function_exists( 'politeia_bookshelf_force_http_covers' ) ) {
    /**
     * Check if single-book cover URLs should be forced to HTTP (test-only).
     *
     * @return bool
     */
    function politeia_bookshelf_force_http_covers() {
        $value = get_option( POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION, false );
        return in_array( $value, array( '1', 1, true, 'on' ), true );
    }
}

/**
 * Sanitize on/off toggles stored as options.
 *
 * @param mixed $value Submitted value.
 *
 * @return string
 */
function politeia_bookshelf_sanitize_toggle( $value ) {
    return ! empty( $value ) ? '1' : '0';
}

if ( ! function_exists( 'politeia_bookshelf_get_page_templates' ) ) {
    /**
     * Define available templates for plugin-managed pages.
     *
     * @return array
     */
    function politeia_bookshelf_get_page_templates() {
        $templates = array(
            'my-books' => array(
                'label'     => __( 'My Books', 'politeia-bookshelf' ),
                'templates' => array(
                    'archive-my-books' => array(
                        'label' => __( 'Default (My Books archive)', 'politeia-bookshelf' ),
                        'file'  => 'archive-my-books.php',
                    ),
                ),
            ),
            'single-book' => array(
                'label'     => __( 'Single Book', 'politeia-bookshelf' ),
                'templates' => array(
                    'my-book-single' => array(
                        'label' => __( 'Default (Single Book)', 'politeia-bookshelf' ),
                        'file'  => 'my-book-single.php',
                    ),
                    'my-book-single-ver-2' => array(
                        'label' => __( 'Minimal (ver-2)', 'politeia-bookshelf' ),
                        'file'  => 'my-book-single-ver-2.php',
                    ),
                ),
            ),
        );

        return apply_filters( 'politeia_bookshelf_page_templates', $templates );
    }
}

if ( ! function_exists( 'politeia_bookshelf_get_selected_templates' ) ) {
    /**
     * Return selected templates with defaults applied.
     *
     * @return array
     */
    function politeia_bookshelf_get_selected_templates() {
        $stored    = get_option( POLITEIA_BOOKSHELF_TEMPLATES_OPTION, array() );
        $stored    = is_array( $stored ) ? $stored : array();
        $pages     = politeia_bookshelf_get_page_templates();
        $selected  = array();

        foreach ( $pages as $page_key => $page ) {
            $template_keys = array_keys( $page['templates'] );
            $default_key   = $template_keys ? $template_keys[0] : '';
            $value         = isset( $stored[ $page_key ] ) ? sanitize_key( $stored[ $page_key ] ) : '';
            $selected[ $page_key ] = array_key_exists( $value, $page['templates'] ) ? $value : $default_key;
        }

        return $selected;
    }
}

if ( ! function_exists( 'politeia_bookshelf_get_selected_template_file' ) ) {
    /**
     * Resolve the selected template file for a given page key.
     *
     * @param string $page_key Page identifier.
     *
     * @return string|null
     */
    function politeia_bookshelf_get_selected_template_file( $page_key ) {
        $pages    = politeia_bookshelf_get_page_templates();
        $selected = politeia_bookshelf_get_selected_templates();

        if ( ! isset( $pages[ $page_key ] ) ) {
            return null;
        }

        $template_key = $selected[ $page_key ] ?? '';
        if ( ! isset( $pages[ $page_key ]['templates'][ $template_key ] ) ) {
            return null;
        }

        $template = $pages[ $page_key ]['templates'][ $template_key ];
        $file     = isset( $template['file'] ) ? $template['file'] : '';
        if ( '' === $file ) {
            return null;
        }

        $base_path = defined( 'POLITEIA_READING_PATH' ) ? POLITEIA_READING_PATH : plugin_dir_path( dirname( __FILE__, 2 ) ) . 'modules/reading/';
        $path      = trailingslashit( $base_path ) . 'templates/' . ltrim( $file, '/' );

        return file_exists( $path ) ? $path : null;
    }
}

/**
 * Sanitize template selections stored as an option.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function politeia_bookshelf_sanitize_templates( $value ) {
    $value    = is_array( $value ) ? $value : array();
    $pages    = politeia_bookshelf_get_page_templates();
    $cleaned  = array();

    foreach ( $pages as $page_key => $page ) {
        $template_keys = array_keys( $page['templates'] );
        $default_key   = $template_keys ? $template_keys[0] : '';
        $raw_value     = isset( $value[ $page_key ] ) ? $value[ $page_key ] : '';
        $raw_value     = sanitize_key( $raw_value );
        $cleaned[ $page_key ] = array_key_exists( $raw_value, $page['templates'] ) ? $raw_value : $default_key;
    }

    return $cleaned;
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

    register_setting(
        'politeia_bookshelf_test_settings',
        POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION,
        [
            'type'              => 'string',
            'sanitize_callback' => 'politeia_bookshelf_sanitize_toggle',
            'default'           => '0',
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

    add_settings_section(
        'politeia_bookshelf_test_section',
        __( 'Test Settings', 'politeia-bookshelf' ),
        'politeia_bookshelf_render_test_section_intro',
        'politeia_bookshelf_test_settings'
    );

    add_settings_field(
        'politeia_bookshelf_force_http_covers_field',
        __( 'Force HTTP covers on single book page', 'politeia-bookshelf' ),
        'politeia_bookshelf_render_force_http_covers_field',
        'politeia_bookshelf_test_settings',
        'politeia_bookshelf_test_section'
    );

    register_setting(
        'politeia_bookshelf_templates',
        POLITEIA_BOOKSHELF_TEMPLATES_OPTION,
        [
            'type'              => 'array',
            'sanitize_callback' => 'politeia_bookshelf_sanitize_templates',
            'default'           => array(),
        ]
    );

    add_settings_section(
        'politeia_bookshelf_templates_section',
        __( 'Page templates', 'politeia-bookshelf' ),
        'politeia_bookshelf_render_templates_section_intro',
        'politeia_bookshelf_templates'
    );

    add_settings_field(
        'politeia_bookshelf_templates_field',
        __( 'Template assignments', 'politeia-bookshelf' ),
        'politeia_bookshelf_render_templates_field',
        'politeia_bookshelf_templates',
        'politeia_bookshelf_templates_section'
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
 * Output the description for the Test settings section.
 */
function politeia_bookshelf_render_test_section_intro() {
    echo '<p>' . esc_html__( 'Use these options for local development only. Leave them disabled on production.', 'politeia-bookshelf' ) . '</p>';
}

/**
 * Render the force HTTP covers toggle.
 */
function politeia_bookshelf_render_force_http_covers_field() {
    $enabled = politeia_bookshelf_force_http_covers();
    printf(
        '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
        esc_attr( POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION ),
        checked( $enabled, true, false ),
        esc_html__( 'Force HTTP image URLs on single book template (local only)', 'politeia-bookshelf' )
    );
}

/**
 * Output the description for the Templates settings section.
 */
function politeia_bookshelf_render_templates_section_intro() {
    echo '<p>' . esc_html__( 'Choose which template each Politeia Bookshelf page should use.', 'politeia-bookshelf' ) . '</p>';
}

/**
 * Render the templates selection table.
 */
function politeia_bookshelf_render_templates_field() {
    $pages    = politeia_bookshelf_get_page_templates();
    $selected = politeia_bookshelf_get_selected_templates();

    if ( empty( $pages ) ) {
        echo '<p>' . esc_html__( 'No templates are registered yet.', 'politeia-bookshelf' ) . '</p>';
        return;
    }
    ?>
    <table class="widefat striped" style="max-width: 720px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Page', 'politeia-bookshelf' ); ?></th>
                <th><?php esc_html_e( 'Template', 'politeia-bookshelf' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $pages as $page_key => $page ) : ?>
                <tr>
                    <td><?php echo esc_html( $page['label'] ); ?></td>
                    <td>
                        <select name="<?php echo esc_attr( POLITEIA_BOOKSHELF_TEMPLATES_OPTION ); ?>[<?php echo esc_attr( $page_key ); ?>]">
                            <?php foreach ( $page['templates'] as $template_key => $template ) : ?>
                                <option value="<?php echo esc_attr( $template_key ); ?>" <?php selected( $selected[ $page_key ] ?? '', $template_key ); ?>>
                                    <?php echo esc_html( $template['label'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="description"><?php esc_html_e( 'Add new templates by extending the politeia_bookshelf_page_templates filter.', 'politeia-bookshelf' ); ?></p>
    <?php
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
        'test-settings' => __( 'Test Settings', 'politeia-bookshelf' ),
        'templates'    => __( 'Templates', 'politeia-bookshelf' ),
    );

    if ( ! array_key_exists( $current_tab, $tabs ) ) {
        $current_tab = 'overview';
    }

    $base_url      = admin_url( 'admin.php?page=politeia-bookshelf' );
    $overview_url  = $base_url;
    $google_url    = add_query_arg( 'tab', 'google-books', $base_url );
    $test_url      = add_query_arg( 'tab', 'test-settings', $base_url );
    $templates_url = add_query_arg( 'tab', 'templates', $base_url );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Politeia Bookshelf', 'politeia-bookshelf' ); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url( $overview_url ); ?>" class="nav-tab <?php echo 'overview' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tabs['overview'] ); ?></a>
            <a href="<?php echo esc_url( $google_url ); ?>" class="nav-tab <?php echo 'google-books' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tabs['google-books'] ); ?></a>
            <a href="<?php echo esc_url( $test_url ); ?>" class="nav-tab <?php echo 'test-settings' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tabs['test-settings'] ); ?></a>
            <a href="<?php echo esc_url( $templates_url ); ?>" class="nav-tab <?php echo 'templates' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tabs['templates'] ); ?></a>
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
        <?php elseif ( 'test-settings' === $current_tab ) : ?>
            <?php settings_errors( 'politeia_bookshelf_test_settings' ); ?>
            <form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
                <?php
                settings_fields( 'politeia_bookshelf_test_settings' );
                do_settings_sections( 'politeia_bookshelf_test_settings' );
                submit_button();
                ?>
            </form>
        <?php elseif ( 'templates' === $current_tab ) : ?>
            <?php settings_errors( 'politeia_bookshelf_templates' ); ?>
            <form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
                <?php
                settings_fields( 'politeia_bookshelf_templates' );
                do_settings_sections( 'politeia_bookshelf_templates' );
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
