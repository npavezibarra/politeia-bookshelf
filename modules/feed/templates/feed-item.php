<?php
/**
 * Template part for displaying a single feed item.
 *
 * Variables expected:
 * $note (object)
 * $note_comments (array)
 */

if (!defined('ABSPATH')) {
    exit;
}

$date_format = get_option('date_format') . ' ' . get_option('time_format');
$date_str = date_i18n($date_format, strtotime($note->created_at));

$final_cover = !empty($note->user_cover) ? $note->user_cover : $note->book_cover;
$cover = !empty($final_cover) ? esc_url($final_cover) : 'https://via.placeholder.com/60x90?text=No+Cover';
?>
<div class="prs-feed-card"
    style="display: flex; gap: 1rem; background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #32375529;">
    <div class="prs-feed-cover" style="flex-shrink: 0;">
        <img src="<?php echo $cover; ?>" alt="<?php echo esc_attr($note->book_title); ?>"
            style="width: 60px; height: auto; border-radius: 4px; object-fit: cover;">
    </div>
    <div class="prs-feed-content" style="flex-grow: 1;">
        <div class="prs-feed-header"
            style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.5rem; border-bottom: 1px solid #888888; padding-bottom: 0.5rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #333;">
                    <?php echo esc_html($note->book_title); ?>
                </h3>
                <div style="font-size: 0.85rem; color: #666; margin-top: 0.2rem;">
                    <?php echo esc_html($note->book_author); ?>
                </div>
            </div>
            <span style="font-size: 0.85rem; color: #888;">
                <?php echo esc_html($date_str); ?>
            </span>
        </div>
        <div class="prs-feed-body"
            style="font-size: 1rem; line-height: 1.6; color: #444; margin-bottom: 1.5rem; border-bottom: 1px solid #888888; padding-bottom: 1.5rem;">
            <?php echo wp_kses_post($note->note); ?>
        </div>

        <!-- Comments Section -->
        <div class="prs-comments-section" id="comments-<?php echo (int) $note->note_id; ?>" style="margin-top: 1rem;">
            <div class="prs-comments-list" style="margin-bottom: 1rem;">
                <?php foreach ($note_comments as $comment):
                    $c_date = date_i18n($date_format, strtotime($comment->created_at));
                    $author_info = get_userdata($comment->user_id);
                    $author_name = $author_info ? $author_info->display_name : __('User', 'politeia-bookshelf');
                    ?>
                    <div class="prs-comment-item"
                        style="margin-bottom: 0.8rem; border-bottom: 1px solid #eee; padding-bottom: 0.8rem;">
                        <div style="font-size: 0.85rem; font-weight: 600; color: #555; margin-bottom: 0.2rem;">
                            <?php echo esc_html($author_name); ?> <span style="font-weight: normal; color: #999;">•
                                <?php echo esc_html($c_date); ?>
                            </span>
                        </div>
                        <div style="font-size: 0.95rem; line-height: 1.4; color: #333;">
                            <?php echo wp_kses_post($comment->content); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="prs-comment-form">
                <form class="prs-submit-comment-form" data-note-id="<?php echo (int) $note->note_id; ?>">
                    <textarea name="comment_content" rows="2"
                        placeholder="<?php esc_attr_e('Write a comment...', 'politeia-bookshelf'); ?>"
                        style="width: 100%; border: 1px solid #ddd; border-radius: 4px; padding: 0.5rem; font-size: 0.95rem; resize: vertical; margin-bottom: 0.5rem;"></textarea>
                    <div style="text-align: right;">
                        <button type="submit" class="button button-small"
                            style="background: #333; color: #fff; border: none; padding: 0.4rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">
                            <?php esc_html_e('Publish', 'politeia-bookshelf'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>