<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{

    /**
     * Initialize admin hooks
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    /**
     * Add admin menu page
     */
    public static function add_admin_menu()
    {
        add_submenu_page(
            'politeia-bookshelf',
            __('Reading Planner Settings', 'politeia-bookshelf'),
            __('Reading Planner', 'politeia-bookshelf'),
            'manage_options',
            'politeia-reading-planner-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public static function register_settings()
    {
        register_setting(
            'politeia_reading_planner_settings',
            'politeia_reading_plan_pages_per_session_options',
            array(
                'type' => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_pages_options'),
            )
        );

        register_setting(
            'politeia_reading_planner_settings',
            'politeia_reading_plan_sessions_per_week_options',
            array(
                'type' => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_sessions_options'),
            )
        );

        register_setting(
            'politeia_reading_planner_settings',
            'politeia_reading_plan_default_pages_per_session',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            )
        );

        register_setting(
            'politeia_reading_planner_settings',
            'politeia_reading_plan_default_sessions_per_week',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            )
        );
    }

    /**
     * Sanitize pages per session options
     *
     * @param mixed $input Input value.
     * @return array Sanitized array.
     */
    public static function sanitize_pages_options($input)
    {
        if (is_string($input)) {
            $input = explode(',', $input);
        }

        if (!is_array($input)) {
            add_settings_error(
                'politeia_reading_planner_settings',
                'invalid_pages_options',
                __('Invalid pages per session options.', 'politeia-bookshelf'),
                'error'
            );
            return Config::get_pages_per_session_options();
        }

        $options = array_map('trim', $input);
        $options = array_map('intval', $options);
        $options = array_filter(
            $options,
            function ($val) {
                return $val > 0;
            }
        );
        $options = array_unique($options);
        sort($options);

        if (empty($options)) {
            add_settings_error(
                'politeia_reading_planner_settings',
                'empty_pages_options',
                __('Pages per session options cannot be empty.', 'politeia-bookshelf'),
                'error'
            );
            return Config::get_pages_per_session_options();
        }

        return $options;
    }

    /**
     * Sanitize sessions per week options
     *
     * @param mixed $input Input value.
     * @return array Sanitized array.
     */
    public static function sanitize_sessions_options($input)
    {
        if (is_string($input)) {
            $input = explode(',', $input);
        }

        if (!is_array($input)) {
            add_settings_error(
                'politeia_reading_planner_settings',
                'invalid_sessions_options',
                __('Invalid sessions per week options.', 'politeia-bookshelf'),
                'error'
            );
            return Config::get_sessions_per_week_options();
        }

        $options = array_map('trim', $input);
        $options = array_map('intval', $options);
        $options = array_filter(
            $options,
            function ($val) {
                return $val > 0 && $val <= 7;
            }
        );
        $options = array_unique($options);
        sort($options);

        if (empty($options)) {
            add_settings_error(
                'politeia_reading_planner_settings',
                'empty_sessions_options',
                __('Sessions per week options cannot be empty.', 'politeia-bookshelf'),
                'error'
            );
            return Config::get_sessions_per_week_options();
        }

        return $options;
    }

    /**
     * Render settings page
     */
    public static function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current values.
        $pages_options = Config::get_pages_per_session_options();
        $sessions_options = Config::get_sessions_per_week_options();
        $default_pages = Config::get_default_pages_per_session();
        $default_sessions = Config::get_default_sessions_per_week();

        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>

            <?php settings_errors('politeia_reading_planner_settings'); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('politeia_reading_planner_settings');
                ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="pages_per_session_options">
                                <?php esc_html_e('Pages per Session Options', 'politeia-bookshelf'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="pages_per_session_options"
                                name="politeia_reading_plan_pages_per_session_options"
                                value="<?php echo esc_attr(implode(', ', $pages_options)); ?>" class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Comma-separated list of positive integers (e.g., 15, 30, 60)', 'politeia-bookshelf'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="default_pages_per_session">
                                <?php esc_html_e('Default Pages per Session', 'politeia-bookshelf'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="default_pages_per_session" name="politeia_reading_plan_default_pages_per_session">
                                <?php foreach ($pages_options as $option): ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($default_pages, $option); ?>>
                                        <?php echo esc_html($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sessions_per_week_options">
                                <?php esc_html_e('Sessions per Week Options', 'politeia-bookshelf'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="sessions_per_week_options"
                                name="politeia_reading_plan_sessions_per_week_options"
                                value="<?php echo esc_attr(implode(', ', $sessions_options)); ?>" class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Comma-separated list of integers 1-7 (e.g., 3, 5, 7)', 'politeia-bookshelf'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="default_sessions_per_week">
                                <?php esc_html_e('Default Sessions per Week', 'politeia-bookshelf'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="default_sessions_per_week" name="politeia_reading_plan_default_sessions_per_week">
                                <?php foreach ($sessions_options as $option): ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($default_sessions, $option); ?>>
                                        <?php echo esc_html($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'politeia-bookshelf')); ?>

                <hr>

                <h2>
                    <?php esc_html_e('Important Notes', 'politeia-bookshelf'); ?>
                </h2>
                <ul>
                    <li>
                        <?php esc_html_e('Changing these settings only affects future plans.', 'politeia-bookshelf'); ?>
                    </li>
                    <li>
                        <?php esc_html_e('Existing plans will keep their stored values.', 'politeia-bookshelf'); ?>
                    </li>
                    <li>
                        <?php esc_html_e('All values must be positive integers.', 'politeia-bookshelf'); ?>
                    </li>
                    <li>
                        <?php esc_html_e('Sessions per week must be between 1 and 7.', 'politeia-bookshelf'); ?>
                    </li>
                </ul>
            </form>
        </div>
        <?php
    }
}
