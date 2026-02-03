<?php
/**
 * Template Name: Feed
 *
 * @package Politeia_Bookshelf
 */

if (!is_user_logged_in()) {
    auth_redirect();
}

get_header();

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
    LIMIT 10
", $user_id);

$notes = $wpdb->get_results($sql);

// Fetch comments for these notes
$comments_by_note = [];
if (!empty($notes)) {
    $note_ids = wp_list_pluck($notes, 'note_id');
    $note_ids_str = implode(',', array_map('absint', $note_ids));
    
    $table_comments = $wpdb->prefix . 'politeia_notes_comments';
    $comments_sql = "SELECT * FROM {$table_comments} WHERE note_id IN ({$note_ids_str}) AND status = 'published' ORDER BY created_at ASC";
    $all_comments = $wpdb->get_results($comments_sql);

    foreach ($all_comments as $c) {
        $comments_by_note[$c->note_id][] = $c;
    }
}
?>

<div class="politeia-container" style="max-width: 800px; margin: 0 auto; padding: 2rem 1rem;">
    <h1 style="margin-bottom: 2rem;"><?php esc_html_e('My Reading Feed', 'politeia-bookshelf'); ?></h1>

    <?php if (empty($notes)): ?>
        <p><?php esc_html_e('You haven\'t written any notes yet.', 'politeia-bookshelf'); ?></p>
    <?php else: ?>
        <div class="prs-feed-list" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <?php foreach ($notes as $note):
                $note_comments = isset($comments_by_note[$note->note_id]) ? $comments_by_note[$note->note_id] : [];
                include plugin_dir_path(__FILE__) . 'feed-item.php';
            endforeach; ?>
        </div>
        <div id="prs-feed-sentinel" style="height: 20px; margin-top: 20px;"></div>
        <div id="prs-feed-loader" style="display: none; text-align: center; color: #888; margin: 20px 0;">
            <?php esc_html_e('Loading more...', 'politeia-bookshelf'); ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
    // Infinite Scroll
    const sentinel = document.getElementById('prs-feed-sentinel');
    const loader = document.getElementById('prs-feed-loader');
    const feedList = document.querySelector('.prs-feed-list');
    let offset = 10;
    let isLoading = false;
    let finished = false;

    if (sentinel && feedList) {
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !isLoading && !finished) {
                loadMore();
            }
        }, {
            root: null,
            rootMargin: '100px',
            threshold: 0.1
        });

        observer.observe(sentinel);

        function loadMore() {
            isLoading = true;
            loader.style.display = 'block';

            const formData = new FormData();
            formData.append('action', 'politeia_load_more_feed');
            formData.append('nonce', '<?php echo wp_create_nonce("prs_reading_nonce"); ?>');
            formData.append('offset', offset);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.data.html) {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = data.data.html;
                        
                        // Move children to feedList
                        while (tempDiv.firstChild) {
                            feedList.appendChild(tempDiv.firstChild);
                        }
                        
                        offset += 10;
                        
                        // Re-attach comment listeners for new items
                        attachCommentListeners();
                    }
                    
                    if (!data.data.remaining) {
                        finished = true;
                        sentinel.style.display = 'none';
                    }
                } else {
                    console.error(data.data);
                    finished = true; 
                }
            })
            .catch(err => {
                console.error(err);
            })
            .finally(() => {
                isLoading = false;
                loader.style.display = 'none';
            });
        }
    }

    function attachCommentListeners() {
        // We need to re-select because new forms were added
        const forms = document.querySelectorAll('.prs-submit-comment-form');
        
        forms.forEach(form => {
            // Avoid double binding
            if (form.dataset.bound) return;
            form.dataset.bound = true;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const noteId = this.dataset.noteId;
                const textarea = this.querySelector('textarea');
                const button = this.querySelector('button');
                const content = textarea.value.trim();
                const listContainer = this.closest('.prs-comments-section').querySelector('.prs-comments-list');
                
                if (!content) return;
                
                // Disable UI
                textarea.disabled = true;
                button.disabled = true;
                button.innerText = '<?php esc_js(__('Publishing...', 'politeia-bookshelf')); ?>';
                
                const formData = new FormData();
                formData.append('action', 'politeia_save_note_comment');
                formData.append('nonce', '<?php echo wp_create_nonce("prs_reading_nonce"); ?>');
                formData.append('note_id', noteId);
                formData.append('content', content);
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Append new comment
                        const newComment = document.createElement('div');
                        newComment.className = 'prs-comment-item';
                        newComment.style.cssText = 'margin-bottom: 0.8rem; border-bottom: 1px solid #eee; padding-bottom: 0.8rem; animation: highlight 1s ease;';
                        newComment.innerHTML = `
                            <div style="font-size: 0.85rem; font-weight: 600; color: #555; margin-bottom: 0.2rem;">
                                ${data.data.author} <span style="font-weight: normal; color: #999;">• ${data.data.date}</span>
                            </div>
                            <div style="font-size: 0.95rem; line-height: 1.4; color: #333;">
                                ${data.data.content}
                            </div>
                        `;
                        listContainer.appendChild(newComment);
                        
                        // Reset form
                        textarea.value = '';
                    } else {
                        alert(data.data || 'Error saving comment');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Network error');
                })
                .finally(() => {
                    textarea.disabled = false;
                    button.disabled = false;
                    button.innerText = '<?php esc_js(__('Publish', 'politeia-bookshelf')); ?>';
                });
            });
        });
    }

    // Initial listener attachment
    attachCommentListeners();
});
</script>

<?php
get_footer();
