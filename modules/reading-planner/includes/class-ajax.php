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
        add_action('wp_ajax_prs_user_book_search', array(__CLASS__, 'handle_user_book_search'));
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

    /**
     * Handle user book search for autocomplete.
     */
    public static function handle_user_book_search(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Login required.'), 403);
            return;
        }

        // Verify nonce (using checking specific search nonce)
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'prs_user_book_search')) {
            wp_send_json_error(array('message' => 'Invalid nonce.'), 403);
            return;
        }

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        if ('' === $query || strlen($query) < 3) {
            wp_send_json(array('items' => array()));
            return;
        }

        global $wpdb;
        $ub_table = $wpdb->prefix . 'politeia_user_books';
        $b_table = $wpdb->prefix . 'politeia_books';
        $user_id = get_current_user_id();

        // Normalized title search
        // We'll search by canonical title via join
        // Also fetch owning_status to differentiate copies?
        $like = '%' . $wpdb->esc_like($query) . '%';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ub.id as user_book_id, ub.book_id, ub.pages as user_pages, ub.cover_reference as user_cover,
                    b.title, b.year, b.pages as canonical_pages, b.cover_attachment_id
             FROM {$ub_table} ub
             INNER JOIN {$b_table} b ON b.id = ub.book_id
             WHERE ub.user_id = %d 
               AND ub.deleted_at IS NULL
               AND (b.title LIKE %s OR b.normalized_title LIKE %s)
             ORDER BY b.title ASC, ub.id ASC
             LIMIT 20",
            $user_id,
            $like,
            $like
        ));

        $items = array();
        if ($results) {
            // Group by book_id to detect duplicates
            $counts = array();
            foreach ($results as $row) {
                $bid = (int) $row->book_id;
                if (!isset($counts[$bid])) {
                    $counts[$bid] = 0;
                }
                $counts[$bid]++;
            }
            $indices = array(); // track index for each book_id

            foreach ($results as $row) {
                $bid = (int) $row->book_id;
                if (!isset($indices[$bid])) {
                    $indices[$bid] = 0;
                }
                $indices[$bid]++;

                $title = $row->title;
                // If multiple copies, append suffix
                if ($counts[$bid] > 1) {
                    // Try to differentiate by pages or just index
                    // Example: The Hobbit (Copy #1)
                    $title .= sprintf(' — Copy #%d', $indices[$bid]);
                }

                $pages = $row->user_pages ? (int) $row->user_pages : (int) $row->canonical_pages;

                // Solve cover
                $cover_url = '';
                if ($row->user_cover) {
                    if (is_numeric($row->user_cover)) {
                        $cover_url = wp_get_attachment_image_url((int) $row->user_cover, 'medium');
                    } else {
                        $cover_url = esc_url_raw($row->user_cover);
                    }
                } elseif ($row->cover_attachment_id) {
                    $cover_url = wp_get_attachment_image_url((int) $row->cover_attachment_id, 'medium');
                }

                // Get authors (could be expensive to do 1-by-1, but for 20 items likely okay, or could join)
                // For simplicity, let's skip authors join for now or do a separate query if needed.
                // The frontend expects 'author' string.
                // Let's do a quick fetch function effectively.
                $authors = self::get_authors_for_book($bid);

                $items[] = array(
                    'user_book_id' => (int) $row->user_book_id,
                    'book_id' => (int) $row->book_id,
                    'title' => $title,
                    'author' => $authors,
                    'pages' => $pages,
                    'cover' => $cover_url,
                    'source' => 'user_library'
                );
            }
        }

        wp_send_json(array('items' => $items));
    }

    private static function get_authors_for_book($book_id): string
    {
        global $wpdb;
        $ba_table = $wpdb->prefix . 'politeia_book_authors';
        $a_table = $wpdb->prefix . 'politeia_authors';

        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT a.display_name 
             FROM {$a_table} a 
             INNER JOIN {$ba_table} ba ON ba.author_id = a.id 
             WHERE ba.book_id = %d 
             ORDER BY a.display_name ASC",
            $book_id
        ));

        return $names ? implode(', ', $names) : '';
    }
}

Ajax::init();
