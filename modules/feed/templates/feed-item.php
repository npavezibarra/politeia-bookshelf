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

$note_user_id = isset($note->note_user_id) ? (int) $note->note_user_id : 0;
$note_user_name = isset($note->note_user_name) ? $note->note_user_name : '';

$note_author = !$note_user_name && $note_user_id ? get_userdata($note_user_id) : null;
$note_author_name = $note_user_name ?: ($note_author ? $note_author->display_name : __('User', 'politeia-reading'));
$note_author_avatar = $note_user_id ? get_avatar_url($note_user_id, array('size' => 64)) : '';
$posted_text = sprintf(__('%s posted...', 'politeia-reading'), $note_author_name);
$note_id = (int) $note->note_id;
?>
<div class="prs-feed-card"
    style="background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #32375529;">
    <div class="prs-feed-content" id="prs-feed-content-<?php echo esc_attr($note_id); ?>">
        <div class="prs-feed-user-header" id="prs-feed-user-header-<?php echo esc_attr($note_id); ?>"
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #dfdfdf; padding-bottom: 0.75rem;">
            <div id="prs-feed-user-meta-<?php echo esc_attr($note_id); ?>"
                style="display: flex; align-items: center; gap: 12px;">
                <?php if ($note_author_avatar): ?>
                    <img class="prs-feed-user-avatar" src="<?php echo esc_url($note_author_avatar); ?>"
                        alt="<?php echo esc_attr($note_author_name); ?>"
                        style="width: 44px; height: 44px; border-radius: 25px; object-fit: cover;">
                <?php endif; ?>
                <div id="prs-feed-user-name-<?php echo esc_attr($note_id); ?>"
                    style="font-size: 1.05rem; font-weight: 600; color: #111;">
                    <?php echo esc_html($posted_text); ?>
                </div>
            </div>
            <span id="prs-feed-user-date-<?php echo esc_attr($note_id); ?>" style="font-size: 0.9rem; color: #111;">
                <?php echo esc_html($date_str); ?>
            </span>
        </div>
        <div class="prs-feed-body" id="prs-feed-body-<?php echo esc_attr($note_id); ?>"
            style="font-size: 1rem; line-height: 1.6; color: #444; margin-bottom: 1.5rem; border-bottom: 1px solid #dfdfdf; padding-bottom: 1.5rem;">
            <?php echo wp_kses_post($note->note); ?>
        </div>
        <div class="prs-feed-header" id="prs-feed-header-<?php echo esc_attr($note_id); ?>"
            style="display: flex; gap: 20px; align-items: flex-start; margin-bottom: 0.5rem; border-bottom: 1px solid #dfdfdf; padding-bottom: 0.5rem;">
            <div id="prs-feed-book-meta-<?php echo esc_attr($note_id); ?>"
                style="display: flex; gap: 1rem; align-items: flex-start; flex: 1 1 0;">
                <div class="prs-feed-cover" id="prs-feed-cover-<?php echo esc_attr($note_id); ?>"
                    style="flex-shrink: 0;">
                    <img id="prs-feed-cover-img-<?php echo esc_attr($note_id); ?>" src="<?php echo $cover; ?>"
                        alt="<?php echo esc_attr($note->book_title); ?>"
                        style="width: 60px; height: auto; border-radius: 4px; object-fit: cover;">
                </div>
                <div id="prs-feed-book-text-<?php echo esc_attr($note_id); ?>">
                    <h3 id="prs-feed-book-title-<?php echo esc_attr($note_id); ?>"
                        style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #333;">
                        <?php echo esc_html($note->book_title); ?>
                    </h3>
                    <div id="prs-feed-book-author-<?php echo esc_attr($note_id); ?>"
                        style="font-size: 0.85rem; color: #666; margin-top: 0.2rem;">
                        <?php echo esc_html($note->book_author); ?>
                    </div>
                </div>
            </div>
            <div class="prs-comment-form" id="prs-comment-form-<?php echo esc_attr($note_id); ?>" style="flex: 1 1 0;">
                <form class="prs-submit-comment-form" id="prs-comment-form-inner-<?php echo esc_attr($note_id); ?>"
                    data-note-id="<?php echo (int) $note->note_id; ?>">
                    <textarea id="prs-comment-textarea-<?php echo esc_attr($note_id); ?>" name="comment_content"
                        rows="2" placeholder="<?php esc_attr_e('Write a comment...', 'politeia-bookshelf'); ?>"
                        style="width: 100%; border: 1px solid #ddd; border-radius: 4px; padding: 0.5rem; font-size: 0.95rem; resize: vertical; margin-bottom: 0.5rem;"></textarea>
                    <div id="prs-comment-actions-<?php echo esc_attr($note_id); ?>" style="text-align: right;">
                        <button id="prs-comment-submit-<?php echo esc_attr($note_id); ?>" type="submit"
                            class="button button-small"
                            style="background: #333; color: #fff; border: none; padding: 0.4rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">
                            <?php esc_html_e('Publish', 'politeia-bookshelf'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="prs-comments-section" id="prs-comments-section-<?php echo esc_attr($note_id); ?>"
            style="margin-top: 1rem;">
            <div class="prs-comments-list" id="prs-comments-list-<?php echo esc_attr($note_id); ?>"
                style="margin-bottom: 1rem;">
                <?php foreach ($note_comments as $comment):
                    $c_date = date_i18n($date_format, strtotime($comment->created_at));
                    $author_info = get_userdata($comment->user_id);
                    $author_name = $author_info ? $author_info->display_name : __('User', 'politeia-bookshelf');
                    ?>
                    <div class="prs-comment-item" id="prs-comment-item-<?php echo esc_attr((int) $comment->id); ?>"
                        style="margin-bottom: 0.8rem; border-bottom: 1px solid #dfdfdf; padding-bottom: 0.8rem;">
                        <div id="prs-comment-meta-<?php echo esc_attr((int) $comment->id); ?>"
                            style="font-size: 0.85rem; font-weight: 600; color: #555; margin-bottom: 0.2rem;">
                            <span
                                id="prs-comment-author-<?php echo esc_attr((int) $comment->id); ?>"><?php echo esc_html($author_name); ?></span>
                            <span id="prs-comment-date-<?php echo esc_attr((int) $comment->id); ?>"
                                style="font-weight: normal; color: #999;">•
                                <?php echo esc_html($c_date); ?>
                            </span>
                        </div>
                        <div id="prs-comment-content-<?php echo esc_attr((int) $comment->id); ?>"
                            style="font-size: 0.95rem; line-height: 1.4; color: #333;">
                            <?php echo wp_kses_post($comment->content); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</div>