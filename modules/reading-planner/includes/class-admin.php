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
            'politeia_reading_planner_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            )
        );
    }

    /**
     * Sanitize all settings
     *
     * @param array $input Input array.
     * @return array Sanitized array.
     */
    public static function sanitize_settings($input)
    {
        $output = array();
        $defaults = Config::get_defaults_public(); // We need to access defaults, assume public getter or fallback

        // Pages per session options
        if (isset($input['pages_per_session_options'])) {
            $output['pages_per_session_options'] = self::sanitize_array_options($input['pages_per_session_options']);
        }

        // Sessions per week options
        if (isset($input['sessions_per_week_options'])) {
            $output['sessions_per_week_options'] = self::sanitize_limited_options($input['sessions_per_week_options'], 1, 7);
        }

        // Defaults
        $output['default_pages_per_session'] = isset($input['default_pages_per_session']) ? absint($input['default_pages_per_session']) : 30;
        $output['default_sessions_per_week'] = isset($input['default_sessions_per_week']) ? absint($input['default_sessions_per_week']) : 5;

        // Habit Settings
        $habit_keys = [
            'habit_light_start_pages',
            'habit_light_end_pages',
            'habit_intense_start_pages',
            'habit_intense_end_pages'
        ];

        foreach ($habit_keys as $key) {
            $output[$key] = isset($input[$key]) ? absint($input[$key]) : 0;
        }

        return $output;
    }

    private static function sanitize_array_options($input)
    {
        if (!is_array($input))
            return array(15, 30, 60);
        $options = array_map('absint', $input);
        $options = array_filter($options, function ($val) {
            return $val > 0;
        });
        $options = array_unique($options);
        sort($options);
        return !empty($options) ? $options : array(15, 30, 60);
    }

    private static function sanitize_limited_options($input, $min, $max)
    {
        if (!is_array($input))
            return array(3, 5, 7);
        $options = array_map('absint', $input);
        $options = array_filter($options, function ($val) use ($min, $max) {
            return $val >= $min && $val <= $max;
        });
        $options = array_unique($options);
        sort($options);
        return !empty($options) ? $options : array(3, 5, 7);
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

                <h2 class="title"><?php esc_html_e('COMPLETE BOOK', 'politeia-bookshelf'); ?></h2>
                <p class="description mb-4"><?php esc_html_e('Settings for the "Finish a Book" path.', 'politeia-bookshelf'); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Pages per Session Options', 'politeia-bookshelf'); ?></label>
                        </th>
                        <td>
                            <div style="display: flex; gap: 10px;">
                                <?php
                                $p_vals = array_values($pages_options);
                                for ($i = 0; $i < 3; $i++):
                                    $val = isset($p_vals[$i]) ? $p_vals[$i] : '';
                                    ?>
                                    <input type="number" name="politeia_reading_planner_settings[pages_per_session_options][]"
                                        value="<?php echo esc_attr($val); ?>" class="small-text"
                                        placeholder="<?php echo esc_attr(__('Option', 'politeia-bookshelf') . ' ' . ($i + 1)); ?>" />
                                <?php endfor; ?>
                            </div>
                            <p class="description">
                                <?php esc_html_e('Enter 3 options for pages per session (e.g., 15, 30, 60).', 'politeia-bookshelf'); ?>
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
                            <select id="default_pages_per_session"
                                name="politeia_reading_planner_settings[default_pages_per_session]">
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
                            <label><?php esc_html_e('Sessions per Week Options', 'politeia-bookshelf'); ?></label>
                        </th>
                        <td>
                            <div style="display: flex; gap: 10px;">
                                <?php
                                $s_vals = array_values($sessions_options);
                                for ($i = 0; $i < 3; $i++):
                                    $val = isset($s_vals[$i]) ? $s_vals[$i] : '';
                                    ?>
                                    <input type="number" name="politeia_reading_planner_settings[sessions_per_week_options][]"
                                        value="<?php echo esc_attr($val); ?>" class="small-text" min="1" max="7"
                                        placeholder="<?php echo esc_attr(__('Option', 'politeia-bookshelf') . ' ' . ($i + 1)); ?>" />
                                <?php endfor; ?>
                            </div>
                            <p class="description">
                                <?php esc_html_e('Enter 3 options for sessions per week (1-7).', 'politeia-bookshelf'); ?>
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
                            <select id="default_sessions_per_week"
                                name="politeia_reading_planner_settings[default_sessions_per_week]">
                                <?php foreach ($sessions_options as $option): ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($default_sessions, $option); ?>>
                                        <?php echo esc_html($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <hr>

                <h2 class="title"><?php esc_html_e('FORM HABIT', 'politeia-bookshelf'); ?></h2>
                <p class="description mb-4">
                    <?php esc_html_e('Settings for the "Build a Habit" path. Configure start/end values for progression.', 'politeia-bookshelf'); ?>
                </p>

                <table class="form-table" role="presentation" style="margin-bottom: 20px;">
                    <tr>
                        <th scope="row">
                            <label
                                for="habit_days_duration"><?php esc_html_e('Habit Duration (Days)', 'politeia-bookshelf'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="habit_days_duration"
                                name="politeia_reading_planner_settings[habit_days_duration]"
                                value="<?php echo esc_attr(Config::get_habit_config('habit_days_duration', 48)); ?>"
                                class="small-text" min="1" step="1" />
                            <p class="description">
                                <?php esc_html_e('Total duration of the habit challenge in days (e.g., 48).', 'politeia-bookshelf'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Light Intensity -->
                    <div style="flex: 1; min-width: 300px; border: 1px solid #ccc; padding: 20px; background: #fff;">
                        <h3 style="margin-top: 0;"><?php esc_html_e('Light Intensity', 'politeia-bookshelf'); ?></h3>
                        <table class="form-table" role="presentation" style="margin-top: 0;">
                            <tr>
                                <th scope="row"><label
                                        for="habit_light_start_pages"><?php esc_html_e('Start Pages', 'politeia-bookshelf'); ?></label>
                                </th>
                                <td><input type="number" id="habit_light_start_pages"
                                        name="politeia_reading_planner_settings[habit_light_start_pages]"
                                        value="<?php echo esc_attr(Config::get_habit_config('habit_light_start_pages', 3)); ?>"
                                        class="small-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label
                                        for="habit_light_end_pages"><?php esc_html_e('End Pages', 'politeia-bookshelf'); ?></label>
                                </th>
                                <td><input type="number" id="habit_light_end_pages"
                                        name="politeia_reading_planner_settings[habit_light_end_pages]"
                                        value="<?php echo esc_attr(Config::get_habit_config('habit_light_end_pages', 10)); ?>"
                                        class="small-text" /></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Intense Intensity -->
                    <div style="flex: 1; min-width: 300px; border: 1px solid #ccc; padding: 20px; background: #fff;">
                        <h3 style="margin-top: 0;"><?php esc_html_e('Intense Intensity', 'politeia-bookshelf'); ?></h3>
                        <table class="form-table" role="presentation" style="margin-top: 0;">
                            <tr>
                                <th scope="row"><label
                                        for="habit_intense_start_pages"><?php esc_html_e('Start Pages', 'politeia-bookshelf'); ?></label>
                                </th>
                                <td><input type="number" id="habit_intense_start_pages"
                                        name="politeia_reading_planner_settings[habit_intense_start_pages]"
                                        value="<?php echo esc_attr(Config::get_habit_config('habit_intense_start_pages', 15)); ?>"
                                        class="small-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label
                                        for="habit_intense_end_pages"><?php esc_html_e('End Pages', 'politeia-bookshelf'); ?></label>
                                </th>
                                <td><input type="number" id="habit_intense_end_pages"
                                        name="politeia_reading_planner_settings[habit_intense_end_pages]"
                                        value="<?php echo esc_attr(Config::get_habit_config('habit_intense_end_pages', 30)); ?>"
                                        class="small-text" /></td>
                            </tr>
                        </table>
                    </div>
                </div>

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
