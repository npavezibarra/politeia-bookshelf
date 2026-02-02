<?php
/**
 * Google Books API settings for Politeia Bookshelf.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION')) {
    define('POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION', 'politeia_bookshelf_google_api_key');
}
if (!defined('POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION')) {
    define('POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION', 'politeia_bookshelf_force_http_covers');
}
if (!defined('POLITEIA_BOOKSHELF_TEMPLATES_OPTION')) {
    define('POLITEIA_BOOKSHELF_TEMPLATES_OPTION', 'politeia_bookshelf_page_templates');
}
if (!defined('POLITEIA_BOOKSHELF_MY_STATS_SECTIONS_OPTION')) {
    define('POLITEIA_BOOKSHELF_MY_STATS_SECTIONS_OPTION', 'politeia_bookshelf_my_stats_sections');
}
if (!defined('POLITEIA_BOOKSHELF_MY_PLANS_SECTIONS_OPTION')) {
    define('POLITEIA_BOOKSHELF_MY_PLANS_SECTIONS_OPTION', 'politeia_bookshelf_my_plans_sections');
}
if (!defined('POLITEIA_BOOKSHELF_READING_PLANNER_INTENSITY_OPTION')) {
    define('POLITEIA_BOOKSHELF_READING_PLANNER_INTENSITY_OPTION', 'politeia_bookshelf_reading_planner_intensity');
}

if (!function_exists('politeia_bookshelf_get_google_books_api_key')) {
    /**
     * Retrieve the stored Google Books API key, falling back to the legacy option name.
     *
     * @return string
     */
    function politeia_bookshelf_get_google_books_api_key()
    {
        $api_key = get_option(POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION, '');

        if ('' === $api_key) {
            $legacy = get_option('politeia_google_books_api_key', '');
            if (is_string($legacy) && '' !== $legacy) {
                $api_key = $legacy;
            }
        }

        return is_string($api_key) ? $api_key : '';
    }
}

if (!function_exists('politeia_bookshelf_force_http_covers')) {
    /**
     * Check if single-book cover URLs should be forced to HTTP (test-only).
     *
     * @return bool
     */
    function politeia_bookshelf_force_http_covers()
    {
        $value = get_option(POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION, false);
        return in_array($value, array('1', 1, true, 'on'), true);
    }
}

/**
 * Sanitize on/off toggles stored as options.
 *
 * @param mixed $value Submitted value.
 *
 * @return string
 */
function politeia_bookshelf_sanitize_toggle($value)
{
    return !empty($value) ? '1' : '0';
}

if (!function_exists('politeia_bookshelf_get_page_templates')) {
    /**
     * Define available templates for plugin-managed pages.
     *
     * @return array
     */
    function politeia_bookshelf_get_page_templates()
    {
        $templates = array(
            'my-books' => array(
                'label' => __('My Books', 'politeia-bookshelf'),
                'templates' => array(
                    'archive-my-books' => array(
                        'label' => __('Default (My Books archive)', 'politeia-bookshelf'),
                        'file' => 'archive-my-books.php',
                    ),
                ),
            ),
            'single-book' => array(
                'label' => __('Single Book', 'politeia-bookshelf'),
                'templates' => array(
                    'my-book-single' => array(
                        'label' => __('Default (Single Book)', 'politeia-bookshelf'),
                        'file' => 'my-book-single.php',
                    ),
                    'my-book-single-ver-2' => array(
                        'label' => __('Minimal (ver-2)', 'politeia-bookshelf'),
                        'file' => 'my-book-single-ver-2.php',
                    ),
                ),
            ),
        );

        return apply_filters('politeia_bookshelf_page_templates', $templates);
    }
}

if (!function_exists('politeia_bookshelf_get_selected_templates')) {
    /**
     * Return selected templates with defaults applied.
     *
     * @return array
     */
    function politeia_bookshelf_get_selected_templates()
    {
        $stored = get_option(POLITEIA_BOOKSHELF_TEMPLATES_OPTION, array());
        $stored = is_array($stored) ? $stored : array();
        $pages = politeia_bookshelf_get_page_templates();
        $selected = array();

        foreach ($pages as $page_key => $page) {
            $template_keys = array_keys($page['templates']);
            $default_key = $template_keys ? $template_keys[0] : '';
            $value = isset($stored[$page_key]) ? sanitize_key($stored[$page_key]) : '';
            $selected[$page_key] = array_key_exists($value, $page['templates']) ? $value : $default_key;
        }

        return $selected;
    }
}

if (!function_exists('politeia_bookshelf_get_selected_template_file')) {
    /**
     * Resolve the selected template file for a given page key.
     *
     * @param string $page_key Page identifier.
     *
     * @return string|null
     */
    function politeia_bookshelf_get_selected_template_file($page_key)
    {
        $pages = politeia_bookshelf_get_page_templates();
        $selected = politeia_bookshelf_get_selected_templates();

        if (!isset($pages[$page_key])) {
            return null;
        }

        $template_key = $selected[$page_key] ?? '';
        if (!isset($pages[$page_key]['templates'][$template_key])) {
            return null;
        }

        $template = $pages[$page_key]['templates'][$template_key];
        $file = isset($template['file']) ? $template['file'] : '';
        if ('' === $file) {
            return null;
        }

        $base_path = defined('POLITEIA_READING_PATH') ? POLITEIA_READING_PATH : plugin_dir_path(dirname(__FILE__, 2)) . 'modules/reading/';
        $path = trailingslashit($base_path) . 'templates/' . ltrim($file, '/');

        return file_exists($path) ? $path : null;
    }
}

/**
 * Sanitize template selections stored as an option.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function politeia_bookshelf_sanitize_templates($value)
{
    $value = is_array($value) ? $value : array();
    $pages = politeia_bookshelf_get_page_templates();
    $cleaned = array();

    foreach ($pages as $page_key => $page) {
        $template_keys = array_keys($page['templates']);
        $default_key = $template_keys ? $template_keys[0] : '';
        $raw_value = isset($value[$page_key]) ? $value[$page_key] : '';
        $raw_value = sanitize_key($raw_value);
        $cleaned[$page_key] = array_key_exists($raw_value, $page['templates']) ? $raw_value : $default_key;
    }

    return $cleaned;
}

function politeia_bookshelf_sanitize_my_stats_sections($value)
{
    $allowed = array('performance', 'consistency', 'library');
    $clean = array();

    foreach ($allowed as $key) {
        $clean[$key] = (isset($value[$key]) && '1' === (string) $value[$key]) ? 1 : 0;
    }

    return $clean;
}

function politeia_bookshelf_get_my_stats_sections()
{
    $defaults = array(
        'performance' => 1,
        'consistency' => 1,
        'library' => 1,
    );
    $stored = get_option(POLITEIA_BOOKSHELF_MY_STATS_SECTIONS_OPTION, array());

    return wp_parse_args($stored, $defaults);
}

function politeia_bookshelf_sanitize_my_plans_sections($value)
{
    $allowed = array('view_1', 'view_2');
    $clean = array();

    foreach ($allowed as $key) {
        $clean[$key] = (isset($value[$key]) && '1' === (string) $value[$key]) ? 1 : 0;
    }

    return $clean;
}

function politeia_bookshelf_get_my_plans_sections()
{
    $defaults = array(
        'view_1' => 1,
        'view_2' => 1,
    );
    $stored = get_option(POLITEIA_BOOKSHELF_MY_PLANS_SECTIONS_OPTION, array());

    return wp_parse_args($stored, $defaults);
}

/**
 * Get Reading Planner intensity configuration with defaults.
 *
 * @return array
 */
function politeia_bookshelf_get_reading_planner_intensity()
{
    $defaults = array(
        'light' => array(
            'start_minutes' => 15,
            'end_minutes' => 30,
            'start_pages' => 3,
            'end_pages' => 10,
        ),
        'intense' => array(
            'start_minutes' => 30,
            'end_minutes' => 60,
            'start_pages' => 15,
            'end_pages' => 30,
        ),
    );
    $stored = get_option(POLITEIA_BOOKSHELF_READING_PLANNER_INTENSITY_OPTION, array());
    $stored = is_array($stored) ? $stored : array();

    return wp_parse_args($stored, $defaults);
}

/**
 * Sanitize Reading Planner intensity settings.
 *
 * @param mixed $value Submitted value.
 * @return array
 */
function politeia_bookshelf_sanitize_reading_planner_intensity($value)
{
    $value = is_array($value) ? $value : array();
    $clean = array();

    $modes = array('light', 'intense');
    $fields = array('start_minutes', 'end_minutes', 'start_pages', 'end_pages');

    foreach ($modes as $mode) {
        $clean[$mode] = array();
        foreach ($fields as $field) {
            $raw = isset($value[$mode][$field]) ? $value[$mode][$field] : '';
            $num = absint($raw);
            $clean[$mode][$field] = max(0, $num);
        }
    }

    return $clean;
}

/**
 * Register Google Books API settings section and field.
 */
function politeia_bookshelf_register_google_books_settings()
{
    register_setting(
        'politeia_bookshelf_google_books',
        POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION,
        [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]
    );

    register_setting(
        'politeia_bookshelf_test_settings',
        POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION,
        [
            'type' => 'string',
            'sanitize_callback' => 'politeia_bookshelf_sanitize_toggle',
            'default' => '0',
        ]
    );

    add_settings_section(
        'politeia_bookshelf_google_books_section',
        __('Google Books API configuration', 'politeia-bookshelf'),
        'politeia_bookshelf_render_google_books_section_intro',
        'politeia_bookshelf_google_books'
    );

    add_settings_field(
        'politeia_bookshelf_google_books_api_key_field',
        __('API Key', 'politeia-bookshelf'),
        'politeia_bookshelf_render_google_books_api_key_field',
        'politeia_bookshelf_google_books',
        'politeia_bookshelf_google_books_section'
    );

    add_settings_section(
        'politeia_bookshelf_test_section',
        __('Test Settings', 'politeia-bookshelf'),
        'politeia_bookshelf_render_test_section_intro',
        'politeia_bookshelf_test_settings'
    );

    add_settings_field(
        'politeia_bookshelf_force_http_covers_field',
        __('Force HTTP covers on single book page', 'politeia-bookshelf'),
        'politeia_bookshelf_render_force_http_covers_field',
        'politeia_bookshelf_test_settings',
        'politeia_bookshelf_test_section'
    );

    register_setting(
        'politeia_bookshelf_templates',
        POLITEIA_BOOKSHELF_TEMPLATES_OPTION,
        [
            'type' => 'array',
            'sanitize_callback' => 'politeia_bookshelf_sanitize_templates',
            'default' => array(),
        ]
    );
    register_setting(
        'politeia_bookshelf_my_stats',
        POLITEIA_BOOKSHELF_MY_STATS_SECTIONS_OPTION,
        [
            'type' => 'array',
            'sanitize_callback' => 'politeia_bookshelf_sanitize_my_stats_sections',
            'default' => array(),
        ]
    );

    register_setting(
        'politeia_bookshelf_my_plans',
        POLITEIA_BOOKSHELF_MY_PLANS_SECTIONS_OPTION,
        [
            'type' => 'array',
            'sanitize_callback' => 'politeia_bookshelf_sanitize_my_plans_sections',
            'default' => array(),
        ]
    );

    add_settings_section(
        'politeia_bookshelf_templates_section',
        __('Page templates', 'politeia-bookshelf'),
        'politeia_bookshelf_render_templates_section_intro',
        'politeia_bookshelf_templates'
    );

    add_settings_field(
        'politeia_bookshelf_templates_field',
        __('Template assignments', 'politeia-bookshelf'),
        'politeia_bookshelf_render_templates_field',
        'politeia_bookshelf_templates',
        'politeia_bookshelf_templates_section'
    );

    add_settings_section(
        'politeia_bookshelf_my_stats_section',
        __('My Stats Page', 'politeia-bookshelf'),
        'politeia_bookshelf_render_my_stats_section_intro',
        'politeia_bookshelf_my_stats'
    );

    add_settings_field(
        'politeia_bookshelf_my_stats_sections_field',
        __('Section visibility', 'politeia-bookshelf'),
        'politeia_bookshelf_render_my_stats_sections_field',
        'politeia_bookshelf_my_stats',
        'politeia_bookshelf_my_stats_section'
    );

    add_settings_section(
        'politeia_bookshelf_my_plans_section',
        __('My Plans Page', 'politeia-bookshelf'),
        'politeia_bookshelf_render_my_plans_section_intro',
        'politeia_bookshelf_my_plans'
    );

    add_settings_field(
        'politeia_bookshelf_my_plans_sections_field',
        __('Section visibility', 'politeia-bookshelf'),
        'politeia_bookshelf_render_my_plans_sections_field',
        'politeia_bookshelf_my_plans',
        'politeia_bookshelf_my_plans_section'
    );

    register_setting(
        'politeia_bookshelf_reading_planner',
        POLITEIA_BOOKSHELF_READING_PLANNER_INTENSITY_OPTION,
        [
            'type' => 'array',
            'sanitize_callback' => 'politeia_bookshelf_sanitize_reading_planner_intensity',
            'default' => array(),
        ]
    );

    add_settings_section(
        'politeia_bookshelf_reading_planner_section',
        __('Reading Planner Intensity', 'politeia-bookshelf'),
        'politeia_bookshelf_render_reading_planner_section_intro',
        'politeia_bookshelf_reading_planner'
    );

    add_settings_field(
        'politeia_bookshelf_reading_planner_intensity_field',
        __('Intensity Configuration', 'politeia-bookshelf'),
        'politeia_bookshelf_render_reading_planner_intensity_field',
        'politeia_bookshelf_reading_planner',
        'politeia_bookshelf_reading_planner_section'
    );
}
add_action('admin_init', 'politeia_bookshelf_register_google_books_settings');

/**
 * Keep the legacy option name in sync to avoid breaking existing consumers.
 *
 * @param string $old_value Previous option value.
 * @param string $value     New option value.
 *
 * @return void
 */
function politeia_bookshelf_sync_legacy_google_books_option($old_value, $value)
{
    update_option('politeia_google_books_api_key', $value);
}
add_action('update_option_' . POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION, 'politeia_bookshelf_sync_legacy_google_books_option', 10, 2);

/**
 * Mirror the stored value to the legacy option when it is added for the first time.
 *
 * @param string $option Option name (ignored).
 * @param string $value  Saved value.
 */
function politeia_bookshelf_sync_legacy_google_books_option_on_add($option, $value)
{
    update_option('politeia_google_books_api_key', $value);
}
add_action('add_option_' . POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION, 'politeia_bookshelf_sync_legacy_google_books_option_on_add', 10, 2);

/**
 * Output the description for the Google Books settings section.
 */
function politeia_bookshelf_render_google_books_section_intro()
{
    echo '<p>' . esc_html__('Provide the Google Books API key generated in Google Cloud to enable Google Books requests.', 'politeia-bookshelf') . '</p>';
}

/**
 * Render the API key field.
 */
function politeia_bookshelf_render_google_books_api_key_field()
{
    $api_key = politeia_bookshelf_get_google_books_api_key();
    printf(
        '<input type="text" name="%1$s" value="%2$s" class="regular-text" autocomplete="off" />',
        esc_attr(POLITEIA_BOOKSHELF_GOOGLE_BOOKS_OPTION),
        esc_attr($api_key)
    );
    echo '<p class="description">' . esc_html__('Paste the token from the Google Cloud Console. Only administrators can view or modify this value.', 'politeia-bookshelf') . '</p>';
}

/**
 * Output the description for the Test settings section.
 */
function politeia_bookshelf_render_test_section_intro()
{
    echo '<p>' . esc_html__('Use these options for local development only. Leave them disabled on production.', 'politeia-bookshelf') . '</p>';
}

/**
 * Render the force HTTP covers toggle.
 */
function politeia_bookshelf_render_force_http_covers_field()
{
    $enabled = politeia_bookshelf_force_http_covers();
    printf(
        '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
        esc_attr(POLITEIA_BOOKSHELF_FORCE_HTTP_COVERS_OPTION),
        checked($enabled, true, false),
        esc_html__('Force HTTP image URLs on single book template (local only)', 'politeia-bookshelf')
    );
}

/**
 * Output the description for the Templates settings section.
 */
function politeia_bookshelf_render_templates_section_intro()
{
    echo '<p>' . esc_html__('Choose which template each Politeia Bookshelf page should use.', 'politeia-bookshelf') . '</p>';
}

/**
 * Render the templates selection table.
 */
function politeia_bookshelf_render_templates_field()
{
    $pages = politeia_bookshelf_get_page_templates();
    $selected = politeia_bookshelf_get_selected_templates();

    if (empty($pages)) {
        echo '<p>' . esc_html__('No templates are registered yet.', 'politeia-bookshelf') . '</p>';
        return;
    }
    ?>
    <table class="widefat striped" style="max-width: 720px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Page', 'politeia-bookshelf'); ?></th>
                <th><?php esc_html_e('Template', 'politeia-bookshelf'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $page_key => $page): ?>
                <tr>
                    <td><?php echo esc_html($page['label']); ?></td>
                    <td>
                        <select
                            name="<?php echo esc_attr(POLITEIA_BOOKSHELF_TEMPLATES_OPTION); ?>[<?php echo esc_attr($page_key); ?>]">
                            <?php foreach ($page['templates'] as $template_key => $template): ?>
                                <option value="<?php echo esc_attr($template_key); ?>" <?php selected($selected[$page_key] ?? '', $template_key); ?>>
                                    <?php echo esc_html($template['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="description">
        <?php esc_html_e('Add new templates by extending the politeia_bookshelf_page_templates filter.', 'politeia-bookshelf'); ?>
    </p>
    <?php
}

function politeia_bookshelf_render_my_stats_section_intro()
{
    echo '<p>' . esc_html__('Toggle which sections appear on the My Reading Stats template.', 'politeia-bookshelf') . '</p>';
}

function politeia_bookshelf_render_my_stats_sections_field()
{
    $sections = politeia_bookshelf_get_my_stats_sections();
    $labels = array(
        'performance' => __('Master Performance', 'politeia-bookshelf'),
        'consistency' => __('Habit Consistency', 'politeia-bookshelf'),
        'library' => __('Library Status', 'politeia-bookshelf'),
    );

    foreach ($labels as $key => $label):
        $checked = !empty($sections[$key]);
        ?>
        <label class="prs-admin-toggle">
            <input type="checkbox"
                name="<?php echo esc_attr(POLITEIA_BOOKSHELF_MY_STATS_SECTIONS_OPTION); ?>[<?php echo esc_attr($key); ?>]"
                value="1" <?php checked($checked); ?> />
            <span class="prs-admin-toggle__control" aria-hidden="true"></span>
            <span class="prs-admin-toggle__label"><?php echo esc_html($label); ?></span>
        </label>
    <?php endforeach;
}

function politeia_bookshelf_render_my_plans_section_intro()
{
    echo '<p>' . esc_html__('Toggle which habit plan views appear on the My Plans page.', 'politeia-bookshelf') . '</p>';
}

function politeia_bookshelf_render_my_plans_sections_field()
{
    $sections = politeia_bookshelf_get_my_plans_sections();
    $labels = array(
        'view_1' => __('Habit Plan View 1 (Old)', 'politeia-bookshelf'),
        'view_2' => __('Habit Plan View 2 (New)', 'politeia-bookshelf'),
    );

    foreach ($labels as $key => $label):
        $checked = !empty($sections[$key]);
        ?>
        <label class="prs-admin-toggle">
            <input type="checkbox"
                name="<?php echo esc_attr(POLITEIA_BOOKSHELF_MY_PLANS_SECTIONS_OPTION); ?>[<?php echo esc_attr($key); ?>]"
                value="1" <?php checked($checked); ?> />
            <span class="prs-admin-toggle__control" aria-hidden="true"></span>
            <span class="prs-admin-toggle__label"><?php echo esc_html($label); ?></span>
        </label>
    <?php endforeach;
}

/**
 * Output the description for the Reading Planner settings section.
 */
function politeia_bookshelf_render_reading_planner_section_intro()
{
    echo '<p>' . esc_html__('Configure the intensity settings for the Form Habit reading plan. These values determine the minimum session requirements for LIGHT and INTENSE modes.', 'politeia-bookshelf') . '</p>';
}

/**
 * Render the Reading Planner intensity configuration fields.
 */
function politeia_bookshelf_render_reading_planner_intensity_field()
{
    $config = politeia_bookshelf_get_reading_planner_intensity();
    $option_name = POLITEIA_BOOKSHELF_READING_PLANNER_INTENSITY_OPTION;
    ?>
    <table class="widefat striped" style="max-width: 800px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Mode', 'politeia-bookshelf'); ?></th>
                <th><?php esc_html_e('Start Minutes', 'politeia-bookshelf'); ?></th>
                <th><?php esc_html_e('End Minutes', 'politeia-bookshelf'); ?></th>
                <th><?php esc_html_e('Start Pages', 'politeia-bookshelf'); ?></th>
                <th><?php esc_html_e('End Pages', 'politeia-bookshelf'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong><?php esc_html_e('LIGHT', 'politeia-bookshelf'); ?></strong></td>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[light][start_minutes]"
                        value="<?php echo esc_attr($config['light']['start_minutes']); ?>" min="0" class="small-text" />
                </td>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[light][end_minutes]"
                        value="<?php echo esc_attr($config['light']['end_minutes']); ?>" min="0" class="small-text" />
                </td>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[light][start_pages]"
                        value="<?php echo esc_attr($config['light']['start_pages']); ?>" min="0" class="small-text" />
                </td>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[light][end_pages]"
                        value="<?php echo esc_attr($config['light']['end_pages']); ?>" min="0" class="small-text" />
                </td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('INTENSE', 'politeia-bookshelf'); ?></strong></td>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[intense][start_minutes]"
                        value="<?php echo esc_attr($config['intense']['start_minutes']); ?>" min="0" class="small-text" />
                </td>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[intense][end_minutes]"
                        value="<?php echo esc_attr($config['intense']['end_minutes']); ?>" min="0" class="small-text" />
                </td>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[intense][start_pages]"
                        value="<?php echo esc_attr($config['intense']['start_pages']); ?>" min="0" class="small-text" />
                </td>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[intense][end_pages]"
                        value="<?php echo esc_attr($config['intense']['end_pages']); ?>" min="0" class="small-text" />
                </td>
            </tr>
        </tbody>
    </table>
    <p class="description">
        <?php esc_html_e('Start values are used at the beginning of the 48-day habit plan. End values are the targets by the end of the plan. The system will gradually increase requirements between these values.', 'politeia-bookshelf'); ?>
    </p>

    <div
        style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; max-width: 800px;">
        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 14px; color: #333;">
            <?php esc_html_e('Intensity Progression Visualization', 'politeia-bookshelf'); ?>
        </h3>
        <svg viewBox="0 0 600 400" style="width: 100%; max-width: 600px; height: auto;">
            <!-- Grid lines -->
            <line x1="60" y1="340" x2="560" y2="340" stroke="#ddd" stroke-width="1" />
            <line x1="60" y1="280" x2="560" y2="280" stroke="#ddd" stroke-width="1" />
            <line x1="60" y1="220" x2="560" y2="220" stroke="#ddd" stroke-width="1" />
            <line x1="60" y1="160" x2="560" y2="160" stroke="#ddd" stroke-width="1" />
            <line x1="60" y1="100" x2="560" y2="100" stroke="#ddd" stroke-width="1" />
            <line x1="60" y1="40" x2="560" y2="40" stroke="#ddd" stroke-width="1" />

            <!-- Axes -->
            <line x1="60" y1="40" x2="60" y2="340" stroke="#333" stroke-width="2" />
            <line x1="60" y1="340" x2="560" y2="340" stroke="#333" stroke-width="2" />

            <!-- Y-axis label -->
            <text x="20" y="190" font-size="12" fill="#666" text-anchor="middle" transform="rotate(-90 20 190)">
                <?php esc_html_e('Minutes', 'politeia-bookshelf'); ?>
            </text>

            <!-- X-axis label -->
            <text x="310" y="375" font-size="12" fill="#666" text-anchor="middle">
                <?php esc_html_e('Pages', 'politeia-bookshelf'); ?>
            </text>

            <?php
            // Calculate max values for dynamic scaling
            $max_minutes = max(
                $config['light']['start_minutes'],
                $config['light']['end_minutes'],
                $config['intense']['start_minutes'],
                $config['intense']['end_minutes']
            );
            $max_pages = max(
                $config['light']['start_pages'],
                $config['light']['end_pages'],
                $config['intense']['start_pages'],
                $config['intense']['end_pages']
            );

            // Round up to nice numbers
            $max_minutes_scale = ceil($max_minutes / 10) * 10 + 10; // Add 10 for padding
            $max_pages_scale = ceil($max_pages / 5) * 5 + 5; // Add 5 for padding
        
            // Calculate scaling factors
            $minutes_per_pixel = $max_minutes_scale / 300; // 300px height
            $pages_per_pixel = $max_pages_scale / 500; // 500px width
            ?>

            <!-- Y-axis values (dynamic based on max) -->
            <text x="50" y="345" font-size="10" fill="#666" text-anchor="end">0</text>
            <text x="50" y="285" font-size="10" fill="#666"
                text-anchor="end"><?php echo esc_html(round($max_minutes_scale / 5)); ?></text>
            <text x="50" y="225" font-size="10" fill="#666"
                text-anchor="end"><?php echo esc_html(round($max_minutes_scale * 2 / 5)); ?></text>
            <text x="50" y="165" font-size="10" fill="#666"
                text-anchor="end"><?php echo esc_html(round($max_minutes_scale * 3 / 5)); ?></text>
            <text x="50" y="105" font-size="10" fill="#666"
                text-anchor="end"><?php echo esc_html(round($max_minutes_scale * 4 / 5)); ?></text>
            <text x="50" y="45" font-size="10" fill="#666"
                text-anchor="end"><?php echo esc_html($max_minutes_scale); ?></text>

            <!-- X-axis values (dynamic based on max) -->
            <text x="60" y="360" font-size="10" fill="#666" text-anchor="middle">0</text>
            <text x="160" y="360" font-size="10" fill="#666"
                text-anchor="middle"><?php echo esc_html(round($max_pages_scale / 5)); ?></text>
            <text x="260" y="360" font-size="10" fill="#666"
                text-anchor="middle"><?php echo esc_html(round($max_pages_scale * 2 / 5)); ?></text>
            <text x="360" y="360" font-size="10" fill="#666"
                text-anchor="middle"><?php echo esc_html(round($max_pages_scale * 3 / 5)); ?></text>
            <text x="460" y="360" font-size="10" fill="#666"
                text-anchor="middle"><?php echo esc_html(round($max_pages_scale * 4 / 5)); ?></text>
            <text x="560" y="360" font-size="10" fill="#666"
                text-anchor="middle"><?php echo esc_html($max_pages_scale); ?></text>


            <?php
            // Calculate positions for LIGHT mode
            $light_start_x = 60 + ($config['light']['start_pages'] / $pages_per_pixel);
            $light_start_y = 340 - ($config['light']['start_minutes'] / $minutes_per_pixel);
            $light_end_x = 60 + ($config['light']['end_pages'] / $pages_per_pixel);
            $light_end_y = 340 - ($config['light']['end_minutes'] / $minutes_per_pixel);

            // Calculate positions for INTENSE mode
            $intense_start_x = 60 + ($config['intense']['start_pages'] / $pages_per_pixel);
            $intense_start_y = 340 - ($config['intense']['start_minutes'] / $minutes_per_pixel);
            $intense_end_x = 60 + ($config['intense']['end_pages'] / $pages_per_pixel);
            $intense_end_y = 340 - ($config['intense']['end_minutes'] / $minutes_per_pixel);
            ?>

            <!-- LIGHT mode line -->
            <line x1="<?php echo esc_attr($light_start_x); ?>" y1="<?php echo esc_attr($light_start_y); ?>"
                x2="<?php echo esc_attr($light_end_x); ?>" y2="<?php echo esc_attr($light_end_y); ?>" stroke="#4CAF50"
                stroke-width="3" stroke-linecap="round" />

            <!-- LIGHT mode start point -->
            <circle cx="<?php echo esc_attr($light_start_x); ?>" cy="<?php echo esc_attr($light_start_y); ?>" r="5"
                fill="#4CAF50" />

            <!-- LIGHT mode end point -->
            <circle cx="<?php echo esc_attr($light_end_x); ?>" cy="<?php echo esc_attr($light_end_y); ?>" r="5"
                fill="#4CAF50" />

            <!-- INTENSE mode line -->
            <line x1="<?php echo esc_attr($intense_start_x); ?>" y1="<?php echo esc_attr($intense_start_y); ?>"
                x2="<?php echo esc_attr($intense_end_x); ?>" y2="<?php echo esc_attr($intense_end_y); ?>" stroke="#FF5722"
                stroke-width="3" stroke-linecap="round" />

            <!-- INTENSE mode start point -->
            <circle cx="<?php echo esc_attr($intense_start_x); ?>" cy="<?php echo esc_attr($intense_start_y); ?>" r="5"
                fill="#FF5722" />

            <!-- INTENSE mode end point -->
            <circle cx="<?php echo esc_attr($intense_end_x); ?>" cy="<?php echo esc_attr($intense_end_y); ?>" r="5"
                fill="#FF5722" />

        </svg>

        <?php
        // Calculate slopes (minutes per page)
        $light_slope = ($config['light']['end_minutes'] - $config['light']['start_minutes']) /
            max(1, $config['light']['end_pages'] - $config['light']['start_pages']);
        $intense_slope = ($config['intense']['end_minutes'] - $config['intense']['start_minutes']) /
            max(1, $config['intense']['end_pages'] - $config['intense']['start_pages']);
        ?>

        <!-- Legend outside the chart -->
        <div
            style="display: flex; justify-content: center; gap: 30px; margin-top: 15px; padding: 15px; background: white; border: 1px solid #ddd; border-radius: 4px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 40px; height: 3px; background: #4CAF50; border-radius: 2px; position: relative;">
                    <div
                        style="width: 8px; height: 8px; background: #4CAF50; border-radius: 50%; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);">
                    </div>
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 600; color: #333;">LIGHT</div>
                    <div style="font-size: 10px; color: #666;">Slope:
                        <?php echo esc_html(number_format($light_slope, 2)); ?> min/page</div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 40px; height: 3px; background: #FF5722; border-radius: 2px; position: relative;">
                    <div
                        style="width: 8px; height: 8px; background: #FF5722; border-radius: 50%; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);">
                    </div>
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 600; color: #333;">INTENSE</div>
                    <div style="font-size: 10px; color: #666;">Slope:
                        <?php echo esc_html(number_format($intense_slope, 2)); ?> min/page</div>
                </div>
            </div>
        </div>

        <p style="margin-top: 15px; font-size: 12px; color: #666; text-align: center;">
            <?php esc_html_e('This chart shows how reading requirements progress from start to end values over the 48-day habit plan.', 'politeia-bookshelf'); ?>
        </p>
    </div>
    <?php
}

/**
 * Render the Politeia Bookshelf admin page with navigation tabs.
 */
function politeia_bookshelf_render_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'politeia-bookshelf';
    $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';

    if ('politeia-bookshelf-google-books' === $current_page) {
        $current_tab = 'google-books';
    }

    $tabs = array(
        'overview' => __('Overview', 'politeia-bookshelf'),
        'google-books' => __('Google Books API', 'politeia-bookshelf'),
        'test-settings' => __('Test Settings', 'politeia-bookshelf'),
        'templates' => __('Templates', 'politeia-bookshelf'),
        'forms' => __('Forms', 'politeia-bookshelf'),
    );

    if (!array_key_exists($current_tab, $tabs)) {
        $current_tab = 'overview';
    }

    $base_url = admin_url('admin.php?page=politeia-bookshelf');
    $overview_url = $base_url;
    $google_url = add_query_arg('tab', 'google-books', $base_url);
    $test_url = add_query_arg('tab', 'test-settings', $base_url);
    $templates_url = add_query_arg('tab', 'templates', $base_url);
    $forms_url = add_query_arg('tab', 'forms', $base_url);
    $templates_subtab = isset($_GET['templates_tab']) ? sanitize_key(wp_unslash($_GET['templates_tab'])) : 'my-stats';
    $templates_subtabs = array(
        'my-stats' => __('My Stats Page', 'politeia-bookshelf'),
        'my-plans' => __('My Plans', 'politeia-bookshelf'),
        'assignments' => __('Template assignments', 'politeia-bookshelf'),
    );
    if (!array_key_exists($templates_subtab, $templates_subtabs)) {
        $templates_subtab = 'my-stats';
    }
    $forms_subtab = isset($_GET['forms_tab']) ? sanitize_key(wp_unslash($_GET['forms_tab'])) : 'reading-planner';
    $forms_subtabs = array(
        'reading-planner' => __('Reading Planner', 'politeia-bookshelf'),
    );
    if (!array_key_exists($forms_subtab, $forms_subtabs)) {
        $forms_subtab = 'reading-planner';
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Politeia Bookshelf', 'politeia-bookshelf'); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url($overview_url); ?>"
                class="nav-tab <?php echo 'overview' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tabs['overview']); ?></a>
            <a href="<?php echo esc_url($google_url); ?>"
                class="nav-tab <?php echo 'google-books' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tabs['google-books']); ?></a>
            <a href="<?php echo esc_url($test_url); ?>"
                class="nav-tab <?php echo 'test-settings' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tabs['test-settings']); ?></a>
            <a href="<?php echo esc_url($templates_url); ?>"
                class="nav-tab <?php echo 'templates' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tabs['templates']); ?></a>
            <a href="<?php echo esc_url($forms_url); ?>"
                class="nav-tab <?php echo 'forms' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tabs['forms']); ?></a>
        </h2>

        <?php if ('google-books' === $current_tab): ?>
            <?php settings_errors('politeia_bookshelf_google_books'); ?>
            <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
                <?php
                settings_fields('politeia_bookshelf_google_books');
                do_settings_sections('politeia_bookshelf_google_books');
                submit_button();
                ?>
            </form>
        <?php elseif ('test-settings' === $current_tab): ?>
            <?php settings_errors('politeia_bookshelf_test_settings'); ?>
            <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
                <?php
                settings_fields('politeia_bookshelf_test_settings');
                do_settings_sections('politeia_bookshelf_test_settings');
                submit_button();
                ?>
            </form>
        <?php elseif ('templates' === $current_tab): ?>
            <style>
                .prs-admin-subtabs {
                    margin: 16px 0 24px;
                }

                .prs-admin-toggle {
                    display: inline-flex;
                    align-items: center;
                    gap: 12px;
                    margin: 0 24px 12px 0;
                }

                .prs-admin-toggle input {
                    display: none;
                }

                .prs-admin-toggle__control {
                    width: 42px;
                    height: 22px;
                    border-radius: 999px;
                    background: #ccd0d4;
                    position: relative;
                    transition: background 0.2s ease;
                }

                .prs-admin-toggle__control::after {
                    content: '';
                    position: absolute;
                    top: 2px;
                    left: 2px;
                    width: 18px;
                    height: 18px;
                    border-radius: 50%;
                    background: #ffffff;
                    transition: transform 0.2s ease;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
                }

                .prs-admin-toggle input:checked+.prs-admin-toggle__control {
                    background: #2271b1;
                }

                .prs-admin-toggle input:checked+.prs-admin-toggle__control::after {
                    transform: translateX(20px);
                }

                .prs-admin-toggle__label {
                    font-weight: 600;
                }
            </style>

            <h2 class="nav-tab-wrapper prs-admin-subtabs">
                <?php foreach ($templates_subtabs as $subtab_key => $subtab_label): ?>
                    <a href="<?php echo esc_url(add_query_arg(array('tab' => 'templates', 'templates_tab' => $subtab_key), $base_url)); ?>"
                        class="nav-tab <?php echo $templates_subtab === $subtab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($subtab_label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php
            $settings_group = 'politeia_bookshelf_my_stats';
            $sections_page = 'politeia_bookshelf_my_stats';

            if ('assignments' === $templates_subtab) {
                $settings_group = 'politeia_bookshelf_templates';
                $sections_page = 'politeia_bookshelf_templates';
            } elseif ('my-plans' === $templates_subtab) {
                $settings_group = 'politeia_bookshelf_my_plans';
                $sections_page = 'politeia_bookshelf_my_plans';
            }

            settings_errors($settings_group);
            ?>
            <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
                <?php
                settings_fields($settings_group);
                do_settings_sections($sections_page);
                submit_button();
                ?>
            </form>
        <?php elseif ('forms' === $current_tab): ?>
            <h2 class="nav-tab-wrapper prs-admin-subtabs">
                <?php foreach ($forms_subtabs as $subtab_key => $subtab_label): ?>
                    <a href="<?php echo esc_url(add_query_arg(array('tab' => 'forms', 'forms_tab' => $subtab_key), $base_url)); ?>"
                        class="nav-tab <?php echo $forms_subtab === $subtab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($subtab_label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php settings_errors('politeia_bookshelf_reading_planner'); ?>
            <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
                <?php
                settings_fields('politeia_bookshelf_reading_planner');
                do_settings_sections('politeia_bookshelf_reading_planner');
                submit_button();
                ?>
            </form>
        <?php else: ?>
            <p><?php esc_html_e('Use the tabs above to configure the Politeia Bookshelf features.', 'politeia-bookshelf'); ?>
            </p>
            <p><?php esc_html_e('The Google Books API tab lets you store the API token that powers cover lookups across the plugin.', 'politeia-bookshelf'); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
