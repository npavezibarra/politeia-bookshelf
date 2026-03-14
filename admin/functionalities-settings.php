<?php
if (!defined('ABSPATH')) {
    exit;
}

function politeia_bookshelf_sanitize_checkbox($input)
{
    return ($input === 'yes') ? 'yes' : 'no';
}

function politeia_bookshelf_functionalities_settings_init()
{
    register_setting('politeia_functionalities_settings_group', 'politeia_bookshelf_enable_post_reading', array(
        'type' => 'string',
        'sanitize_callback' => 'politeia_bookshelf_sanitize_checkbox',
        'default' => 'no' // The user wants this OFF right now
    ));
}
add_action('admin_init', 'politeia_bookshelf_functionalities_settings_init');

function politeia_bookshelf_render_functionalities_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['settings-updated'])) {
        add_settings_error('politeia_messages', 'politeia_message', __('Settings Saved', 'politeia-bookshelf'), 'updated');
    }

    $enable_post_reading = get_option('politeia_bookshelf_enable_post_reading', 'no'); // default no based on user request

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php settings_errors('politeia_messages'); ?>

        <form action="options.php" method="post">
            <?php
            settings_fields('politeia_functionalities_settings_group');
            do_settings_sections('politeia_functionalities_settings_group');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Reading Progress Tracking', 'politeia-bookshelf'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php esc_html_e('Reading Progress Tracking', 'politeia-bookshelf'); ?></span>
                            </legend>
                            <label for="politeia_bookshelf_enable_post_reading">
                                <input name="politeia_bookshelf_enable_post_reading" type="checkbox"
                                    id="politeia_bookshelf_enable_post_reading" value="yes" <?php checked('yes', $enable_post_reading); ?> />
                                <?php esc_html_e('Enable the "Empezar a leer" button and scroll progress bar on blog posts.', 'politeia-bookshelf'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Turn this on to display the reading tracking features on single posts. Uncheck to disable.', 'politeia-bookshelf'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Changes', 'politeia-bookshelf')); ?>
        </form>
    </div>
    <?php
}
