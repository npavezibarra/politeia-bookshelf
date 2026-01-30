<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
    exit;
}

class Ajax
{
    /**
     * Initialize AJAX hooks.
     */
    public static function init(): void
    {
        add_action('wp_ajax_desist_reading_plan', array(__CLASS__, 'handle_desist_plan'));
    }

    /**
     * Handle request to desist a reading plan.
     */
    public static function handle_desist_plan(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'desist_plan_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Get plan ID
        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        if (!$plan_id) {
            wp_send_json_error('Invalid plan ID');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'politeia_plans';

        // Get the plan to verify ownership
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, status FROM {$table_name} WHERE id = %d",
            $plan_id
        ));

        if (!$plan) {
            wp_send_json_error('Plan not found');
            return;
        }

        // Verify user owns the plan
        $current_user_id = get_current_user_id();
        if ($plan->user_id != $current_user_id) {
            wp_send_json_error('You do not have permission to modify this plan');
            return;
        }

        // Update plan status to 'desisted'
        $updated = $wpdb->update(
            $table_name,
            array('status' => 'desisted'),
            array('id' => $plan_id),
            array('%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error('Failed to update plan status');
            return;
        }

        wp_send_json_success('Plan desisted successfully');
    }
}

Ajax::init();
