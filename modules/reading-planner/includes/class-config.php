<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration class for Reading Planner settings
 */
class Config
{
    const OPTION_NAME = 'politeia_reading_planner_settings';

    /**
     * Get default configuration
     *
     * @return array
     */
    private static function get_defaults(): array
    {
        return array(
            'pages_per_session_options' => array(15, 30, 60),
            'sessions_per_week_options' => array(3, 5, 7),
            'default_pages_per_session' => 30,
            'default_sessions_per_week' => 5,
        );
    }

    /**
     * Get configuration from database or defaults
     *
     * @return array
     */
    private static function get_config(): array
    {
        $config = get_option(self::OPTION_NAME);

        if (!is_array($config)) {
            $config = self::get_defaults();
            update_option(self::OPTION_NAME, $config);
        }

        return array_merge(self::get_defaults(), $config);
    }

    /**
     * Get allowed pages per session options
     *
     * @return array<int>
     */
    public static function get_pages_per_session_options(): array
    {
        $config = self::get_config();
        return isset($config['pages_per_session_options']) && is_array($config['pages_per_session_options'])
            ? $config['pages_per_session_options']
            : array(15, 30, 60);
    }

    /**
     * Get allowed sessions per week options
     *
     * @return array<int>
     */
    public static function get_sessions_per_week_options(): array
    {
        $config = self::get_config();
        return isset($config['sessions_per_week_options']) && is_array($config['sessions_per_week_options'])
            ? $config['sessions_per_week_options']
            : array(3, 5, 7);
    }

    /**
     * Get default pages per session
     *
     * @return int
     */
    public static function get_default_pages_per_session(): int
    {
        $config = self::get_config();
        return isset($config['default_pages_per_session']) && is_int($config['default_pages_per_session'])
            ? $config['default_pages_per_session']
            : 30;
    }

    /**
     * Get default sessions per week
     *
     * @return int
     */
    public static function get_default_sessions_per_week(): int
    {
        $config = self::get_config();
        return isset($config['default_sessions_per_week']) && is_int($config['default_sessions_per_week'])
            ? $config['default_sessions_per_week']
            : 5;
    }

    /**
     * Validate pages per session value
     *
     * @param int $value Value to validate
     * @return bool
     */
    public static function validate_pages_per_session(int $value): bool
    {
        $allowed = self::get_pages_per_session_options();
        return in_array($value, $allowed, true);
    }

    /**
     * Validate sessions per week value
     *
     * @param int $value Value to validate
     * @return bool
     */
    public static function validate_sessions_per_week(int $value): bool
    {
        $allowed = self::get_sessions_per_week_options();
        return in_array($value, $allowed, true);
    }

    /**
     * Update configuration
     *
     * @param array $config New configuration values
     * @return bool
     */
    public static function update_config(array $config): bool
    {
        $current = self::get_config();
        $updated = array_merge($current, $config);
        return update_option(self::OPTION_NAME, $updated);
    }
}
