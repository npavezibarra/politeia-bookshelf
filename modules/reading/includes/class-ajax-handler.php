<?php
if (!defined('ABSPATH')) {
        exit;
}

class Politeia_Reading_Ajax_Handler
{

        public static function init()
        {
                add_action('wp_ajax_politeia_save_session_note', array(__CLASS__, 'save_session_note'));
                add_action('wp_ajax_politeia_get_session_note', array(__CLASS__, 'get_session_note'));
                add_action('wp_ajax_politeia_save_note_emotions', array(__CLASS__, 'save_note_emotions'));
                add_action('wp_ajax_politeia_delete_session_note', array(__CLASS__, 'delete_session_note'));
                add_action('wp_ajax_politeia_save_note_comment', array(__CLASS__, 'save_note_comment'));
                add_action('wp_ajax_politeia_load_more_feed', array(__CLASS__, 'load_more_feed'));
        }

        public static function load_more_feed()
        {
                if (!is_user_logged_in()) {
                        wp_send_json_error(__('Not allowed.', 'politeia-reading'), 401);
                }

                check_ajax_referer('prs_reading_nonce', 'nonce');

                $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
                $user_id = get_current_user_id();

                global $wpdb;
                $table_notes = $wpdb->prefix . 'politeia_read_ses_notes';
                $table_ub = $wpdb->prefix . 'politeia_user_books';
                $table_books = $wpdb->prefix . 'politeia_books';
                $table_book_authors = $wpdb->prefix . 'politeia_book_authors';
                $table_authors = $wpdb->prefix . 'politeia_authors';

                $sql = $wpdb->prepare("
                        SELECT 
                                n.id as note_id,
                                n.note, 
                                n.created_at, 
                                n.emotions,
                                b.title AS book_title, 
                                
                                (SELECT GROUP_CONCAT(a.display_name SEPARATOR ', ') 
                                 FROM {$table_book_authors} ba 
                                 JOIN {$table_authors} a ON ba.author_id = a.id 
                                 WHERE ba.book_id = b.id
                                ) AS book_author,

                                ub.cover_url AS user_cover,
                                b.cover_url AS book_cover
                        FROM {$table_notes} n
                        JOIN {$table_ub} ub ON n.user_book_id = ub.id
                        JOIN {$table_books} b ON ub.book_id = b.id
                        WHERE n.user_id = %d 
                          AND n.note != ''
                        ORDER BY n.created_at DESC
                        LIMIT 10 OFFSET %d
                ", $user_id, $offset);

                $notes = $wpdb->get_results($sql);

                if (empty($notes)) {
                        wp_send_json_success(array('html' => '', 'remaining' => false));
                }

                // Fetch comments
                $comments_by_note = [];
                $note_ids = wp_list_pluck($notes, 'note_id');
                $note_ids_str = implode(',', array_map('absint', $note_ids));

                $table_comments = $wpdb->prefix . 'politeia_notes_comments';
                $comments_sql = "SELECT * FROM {$table_comments} WHERE note_id IN ({$note_ids_str}) AND status = 'published' ORDER BY created_at ASC";
                $all_comments = $wpdb->get_results($comments_sql);

                foreach ($all_comments as $c) {
                        $comments_by_note[$c->note_id][] = $c;
                }

                ob_start();
                foreach ($notes as $note) {
                        $note_comments = isset($comments_by_note[$note->note_id]) ? $comments_by_note[$note->note_id] : [];
                        // Resolve path relative to this file: .../modules/reading/includes/class-ajax-handler.php
                        // We want .../modules/feed/templates/feed-item.php
                        $modules_path = dirname(dirname(dirname(__FILE__)));
                        $template_path = $modules_path . '/feed/templates/feed-item.php';
                        if (file_exists($template_path)) {
                                include $template_path;
                        }
                }
                $html = ob_get_clean();

                wp_send_json_success(array('html' => $html, 'remaining' => count($notes) === 10));
        }

        public static function save_session_note()
        {
                if (!is_user_logged_in()) {
                        wp_send_json_error(__('Not allowed.', 'politeia-reading'), 401);
                }

                $nonce_valid = check_ajax_referer('prs_reading_nonce', 'nonce', false);
                if (!$nonce_valid) {
                        wp_send_json_error(__('Invalid nonce.', 'politeia-reading'), 403);
                }

                global $wpdb;

                $table_notes = $wpdb->prefix . 'politeia_read_ses_notes';
                $table_sessions = $wpdb->prefix . 'politeia_reading_sessions';

                $user_id = get_current_user_id();
                $rs_id = isset($_POST['rs_id']) ? absint($_POST['rs_id']) : 0;
                $user_book_id = isset($_POST['user_book_id']) ? absint($_POST['user_book_id']) : 0;
                // Fallback for transition period if JS sends book_id but not user_book_id
                if (!$user_book_id && !empty($_POST['book_id'])) {
                        $book_id = absint($_POST['book_id']);
                        global $wpdb;
                        $user_book_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}politeia_user_books WHERE user_id=%d AND book_id=%d LIMIT 1", $user_id, $book_id));
                }

                $raw_note = isset($_POST['note']) ? wp_unslash($_POST['note']) : '';
                $note = wp_kses_post($raw_note);
                $note_txt = trim(preg_replace('/\xc2\xa0|\x{00A0}/u', ' ', wp_strip_all_tags($note)));

                if (!$rs_id || !$user_book_id || !$user_id || '' === $note_txt) {
                        wp_send_json_error(__('Missing required fields.', 'politeia-reading'), 400);
                }

                $session = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id FROM {$table_sessions} WHERE id = %d AND user_id = %d AND user_book_id = %d AND deleted_at IS NULL LIMIT 1",
                                $rs_id,
                                $user_id,
                                (int) $user_book_id
                        )
                );

                if (!$session) {
                        wp_send_json_error(__('Invalid session.', 'politeia-reading'), 404);
                }

                $existing = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id FROM {$table_notes} WHERE rs_id = %d AND user_book_id = %d AND user_id = %d LIMIT 1",
                                $rs_id,
                                (int) $user_book_id,
                                $user_id
                        )
                );

                $now = current_time('mysql');

                if ($existing) {
                        $updated = $wpdb->update(
                                $table_notes,
                                array(
                                        'note' => $note,
                                        'updated_at' => $now,
                                ),
                                array('id' => (int) $existing->id),
                                array('%s', '%s'),
                                array('%d')
                        );

                        if (false === $updated) {
                                $error = $wpdb->last_error ? $wpdb->last_error : __('DB update failed.', 'politeia-reading');
                                wp_send_json_error($error, 500);
                        }

                        wp_send_json_success(
                                array(
                                        'note_id' => (int) $existing->id,
                                        'updated' => true,
                                )
                        );
                }

                $inserted = $wpdb->insert(
                        $table_notes,
                        array(
                                'rs_id' => $rs_id,
                                'user_book_id' => $user_book_id,
                                'user_id' => $user_id,
                                'note' => $note,
                                'created_at' => $now,
                                'updated_at' => $now,
                        ),
                        array('%d', '%d', '%d', '%s', '%s', '%s')
                );

                if (false === $inserted) {
                        $error = $wpdb->last_error ? $wpdb->last_error : __('DB insert failed.', 'politeia-reading');
                        wp_send_json_error($error, 500);
                }

                wp_send_json_success(
                        array(
                                'note_id' => (int) $wpdb->insert_id,
                                'updated' => false,
                        )
                );
        }

        public static function get_session_note()
        {
                if (!is_user_logged_in()) {
                        wp_send_json_error(__('Not allowed.', 'politeia-reading'), 401);
                }

                $nonce_valid = check_ajax_referer('prs_reading_nonce', 'nonce', false);
                if (!$nonce_valid) {
                        wp_send_json_error(__('Invalid nonce.', 'politeia-reading'), 403);
                }

                global $wpdb;

                $table_notes = $wpdb->prefix . 'politeia_read_ses_notes';
                $table_sessions = $wpdb->prefix . 'politeia_reading_sessions';

                $user_id = get_current_user_id();
                $rs_id = isset($_POST['rs_id']) ? absint($_POST['rs_id']) : 0;
                $user_book_id = isset($_POST['user_book_id']) ? absint($_POST['user_book_id']) : 0;
                // Fallback
                if (!$user_book_id && !empty($_POST['book_id'])) {
                        $book_id = absint($_POST['book_id']);
                        $user_book_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}politeia_user_books WHERE user_id=%d AND book_id=%d LIMIT 1", $user_id, $book_id));
                }

                if (!$rs_id || !$user_book_id || !$user_id) {
                        wp_send_json_error(__('Missing required fields.', 'politeia-reading'), 400);
                }

                $session = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id FROM {$table_sessions} WHERE id = %d AND user_id = %d AND user_book_id = %d AND deleted_at IS NULL LIMIT 1",
                                $rs_id,
                                $user_id,
                                (int) $user_book_id
                        )
                );

                if (!$session) {
                        wp_send_json_error(__('Invalid session.', 'politeia-reading'), 404);
                }

                $note_row = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT note, updated_at FROM {$table_notes} WHERE rs_id = %d AND user_book_id = %d AND user_id = %d ORDER BY updated_at DESC LIMIT 1",
                                $rs_id,
                                (int) $user_book_id,
                                $user_id
                        )
                );

                $note = $note_row && isset($note_row->note) ? $note_row->note : '';
                $updated_at = $note_row && isset($note_row->updated_at) ? $note_row->updated_at : '';

                wp_send_json_success(
                        array(
                                'note' => $note,
                                'updated_at' => $updated_at,
                                'has_note' => (bool) $note_row,
                        )
                );
        }

        public static function save_note_emotions()
        {
                if (!is_user_logged_in()) {
                        wp_send_json_error(__('Not allowed.', 'politeia-reading'), 401);
                }

                $nonce_valid = check_ajax_referer('prs_reading_nonce', 'nonce', false);
                if (!$nonce_valid) {
                        wp_send_json_error(__('Invalid nonce.', 'politeia-reading'), 403);
                }

                global $wpdb;

                $table_notes = $wpdb->prefix . 'politeia_read_ses_notes';
                $user_id = get_current_user_id();
                $note_id = isset($_POST['note_id']) ? absint($_POST['note_id']) : 0;
                $raw_emotions = isset($_POST['emotions']) ? wp_unslash($_POST['emotions']) : '';

                if (!$note_id || !$user_id) {
                        wp_send_json_error(__('Missing required fields.', 'politeia-reading'), 400);
                }

                $note_row = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id FROM {$table_notes} WHERE id = %d AND user_id = %d LIMIT 1",
                                $note_id,
                                $user_id
                        )
                );

                if (!$note_row) {
                        wp_send_json_error(__('Invalid note.', 'politeia-reading'), 404);
                }

                $decoded = json_decode((string) $raw_emotions, true);
                if (!is_array($decoded)) {
                        wp_send_json_error(__('Invalid emotions payload.', 'politeia-reading'), 400);
                }

                $allowed_keys = array('joy', 'sorrow', 'fear', 'fascination', 'anger', 'serenity', 'enlightenment');
                $sanitized = array();
                foreach ($allowed_keys as $key) {
                        if (!array_key_exists($key, $decoded)) {
                                $sanitized[$key] = 0;
                                continue;
                        }
                        $value = (int) $decoded[$key];
                        if ($value < 0) {
                                $value = 0;
                        } elseif ($value > 5) {
                                $value = 5;
                        }
                        $sanitized[$key] = $value;
                }

                $emotions_json = wp_json_encode($sanitized);
                if (false === $emotions_json) {
                        wp_send_json_error(__('Failed to encode emotions.', 'politeia-reading'), 500);
                }

                $updated = $wpdb->update(
                        $table_notes,
                        array(
                                'emotions' => $emotions_json,
                                'updated_at' => current_time('mysql'),
                        ),
                        array('id' => (int) $note_id),
                        array('%s', '%s'),
                        array('%d')
                );

                if (false === $updated) {
                        $error = $wpdb->last_error ? $wpdb->last_error : __('DB update failed.', 'politeia-reading');
                        wp_send_json_error($error, 500);
                }

                wp_send_json_success(
                        array(
                                'note_id' => (int) $note_id,
                                'emotions' => $sanitized,
                        )
                );
        }

        public static function delete_session_note()
        {
                if (!is_user_logged_in()) {
                        wp_send_json_error(__('Not allowed.', 'politeia-reading'), 401);
                }

                $nonce_valid = check_ajax_referer('prs_reading_nonce', 'nonce', false);
                if (!$nonce_valid) {
                        wp_send_json_error(__('Invalid nonce.', 'politeia-reading'), 403);
                }

                global $wpdb;

                $table_notes = $wpdb->prefix . 'politeia_read_ses_notes';
                $user_id = get_current_user_id();
                $note_id = isset($_POST['note_id']) ? absint($_POST['note_id']) : 0;

                if (!$note_id || !$user_id) {
                        wp_send_json_error(__('Missing required fields.', 'politeia-reading'), 400);
                }

                $note_row = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id, user_id FROM {$table_notes} WHERE id = %d LIMIT 1",
                                $note_id
                        )
                );

                if (!$note_row) {
                        wp_send_json_error(__('Note not found.', 'politeia-reading'), 404);
                }

                if ((int) $note_row->user_id !== $user_id) {
                        wp_send_json_error(__('You do not have permission to delete this note.', 'politeia-reading'), 403);
                }

                $deleted = $wpdb->delete(
                        $table_notes,
                        array('id' => (int) $note_id),
                        array('%d')
                );

                if (false === $deleted) {
                        $error = $wpdb->last_error ? $wpdb->last_error : __('Failed to delete note.', 'politeia-reading');
                        wp_send_json_error($error, 500);
                }

                wp_send_json_success(
                        array(
                                'note_id' => (int) $note_id,
                                'deleted' => true,
                        )
                );
        }

        public static function save_note_comment()
        {
                if (!is_user_logged_in()) {
                        wp_send_json_error(__('Not allowed.', 'politeia-reading'), 401);
                }

                $nonce_valid = check_ajax_referer('prs_reading_nonce', 'nonce', false);
                if (!$nonce_valid) {
                        wp_send_json_error(__('Invalid nonce.', 'politeia-reading'), 403);
                }

                $note_id = isset($_POST['note_id']) ? absint($_POST['note_id']) : 0;
                $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';

                if (!$note_id || empty($content)) {
                        wp_send_json_error(__('Missing note ID or content.', 'politeia-reading'), 400);
                }

                global $wpdb;
                $table_notes = $wpdb->prefix . 'politeia_read_ses_notes';
                $table_comments = $wpdb->prefix . 'politeia_notes_comments';

                // Verify note exists
                $note_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_notes} WHERE id = %d", $note_id));
                if (!$note_exists) {
                        wp_send_json_error(__('Invalid note.', 'politeia-reading'), 404);
                }

                $user_id = get_current_user_id();
                $now = current_time('mysql');

                $inserted = $wpdb->insert(
                        $table_comments,
                        array(
                                'note_id' => $note_id,
                                'user_id' => $user_id,
                                'content' => $content,
                                'created_at' => $now,
                                'updated_at' => $now,
                                'status' => 'published'
                        ),
                        array('%d', '%d', '%s', '%s', '%s', '%s')
                );

                if ($inserted === false) {
                        wp_send_json_error(__('Database error.', 'politeia-reading'), 500);
                }

                $comment_id = $wpdb->insert_id;

                // Fetch user display name
                $user_info = get_userdata($user_id);
                $author_name = $user_info ? $user_info->display_name : __('Anonymous', 'politeia-reading');

                wp_send_json_success(array(
                        'id' => $comment_id,
                        'content' => $content,
                        'author' => $author_name,
                        'date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($now))
                ));
        }
}

Politeia_Reading_Ajax_Handler::init();
