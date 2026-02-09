<?php
/**
 * Template Name: Feed
 *
 * @package Politeia_Bookshelf
 */

if (!is_user_logged_in()) {
    auth_redirect();
}

set_query_var('prs_feed', true);

get_header();

$user_id = get_current_user_id();
?>

<style>
    :root {
        --prs-black: #000000;
        --prs-deep-gray: #333333;
        --prs-gold: #c79f32;
        --prs-subtle-gray: #f5f5f5;
        --prs-radius: 6px;
    }

    .prs-feed-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
        font-family: sans-serif;
    }

    .prs-feed-wrap {
        display: flex;
        gap: 20px;
        align-items: flex-start;
    }

    /* Sidebar Column */
    #prs-feed-sidebar {
        width: 30%;
        flex-shrink: 0;
        background: #fff;
        padding: 20px;
        border-radius: var(--prs-radius);
        border: 1px solid #e2e2e2;
    }

    /* Feed Card Column */
    #prs-feed-card {
        width: 70%;
        min-width: 0;
        /* Prevent flex overflow */
    }

    @media (max-width: 768px) {
        .prs-feed-wrap {
            flex-direction: column;
        }

        #prs-feed-sidebar,
        #prs-feed-card {
            width: 100%;
        }
    }

    /* Simple Filter Styles */
    .prs-sidebar-group {
        margin-bottom: 24px;
    }

    .prs-sidebar-title {
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 12px;
        color: var(--prs-deep-gray);
        border-bottom: 2px solid var(--prs-gold);
        padding-bottom: 8px;
        display: inline-block;
    }

    .prs-filter-item {
        margin-bottom: 8px;
        display: block;
    }

    .prs-filter-label {
        font-size: 13px;
        color: #555;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .prs-filter-label:hover {
        color: var(--prs-gold);
    }

    form.prs-submit-comment-form {
        margin-bottom: 0px;
    }
</style>







<div class="prs-feed-container">
    <div class="prs-feed-wrap">

        <!-- Sidebar Column (30%) -->
        <aside id="prs-feed-sidebar">
            <div class="prs-sidebar-group">
                <h2 class="prs-sidebar-title"><?php esc_html_e('Daily stats', 'politeia-bookshelf'); ?></h2>
                <div class="prs-filter-item">
                    <span
                        class="prs-filter-label"><?php esc_html_e('Total pages read today', 'politeia-bookshelf'); ?></span>
                    <strong>128</strong>
                </div>
                <div class="prs-filter-item">
                    <span class="prs-filter-label"><?php esc_html_e('Total sessions', 'politeia-bookshelf'); ?></span>
                    <strong>7</strong>
                </div>
                <div class="prs-filter-item">
                    <span
                        class="prs-filter-label"><?php esc_html_e('Most popular genre', 'politeia-bookshelf'); ?></span>
                    <strong><?php esc_html_e('Literary Fiction', 'politeia-bookshelf'); ?></strong>
                </div>
                <div class="prs-filter-item">
                    <span class="prs-filter-label"><?php esc_html_e('Longest session', 'politeia-bookshelf'); ?></span>
                    <strong>52 min</strong>
                </div>
            </div>
        </aside>

        <!-- Main Content Column (70%) -->
        <main id="prs-feed-card">
            <?php
            global $wpdb;
            $table_notes = $wpdb->prefix . 'politeia_read_ses_notes';
            $table_ub = $wpdb->prefix . 'politeia_user_books';
            $table_books = $wpdb->prefix . 'politeia_books';
            $table_book_authors = $wpdb->prefix . 'politeia_book_authors';
            $table_authors = $wpdb->prefix . 'politeia_authors';

            $sql = $wpdb->prepare("
            SELECT 
                n.id as note_id,
                n.user_id as note_user_id,
                u.display_name as note_user_name,
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
            JOIN {$wpdb->users} u ON n.user_id = u.ID
            WHERE n.visibility = 'public'
              AND n.note != ''
            ORDER BY n.created_at DESC
            LIMIT 10
        ");

            $notes = $wpdb->get_results($sql);

            // Fetch comments
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

            <?php if (empty($notes)): ?>
                <p><?php esc_html_e('You haven\'t written any notes yet.', 'politeia-bookshelf'); ?></p>
            <?php else: ?>
                <div id="prs-feed-list" class="prs-feed-list" style="display: flex; flex-direction: column; gap: 1.5rem;">
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
        </main>
    </div> <!-- .prs-feed-wrap -->
</div> <!-- .prs-feed-container -->

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Dynamic Sticky Offset
        function adjustStickyOffset() {
            const nav = document.querySelector('.prs-metallic-nav');
            if (!nav) return;

            // Try to find the main sticky header
            const header = document.querySelector('header') || document.querySelector('.site-header') || document.querySelector('#masthead');
            let topOffset = 0;

            if (header) {
                // Check if header is fixed/sticky
                const styles = window.getComputedStyle(header);
                if (styles.position === 'fixed' || styles.position === 'sticky') {
                    topOffset = header.offsetHeight;
                }
            }

            // WP Admin Bar
            if (document.body.classList.contains('admin-bar')) {
                const adminBar = document.getElementById('wpadminbar');
                if (adminBar) {
                    topOffset += adminBar.offsetHeight;
                }
            }

            nav.style.top = topOffset + 'px';
        }

        adjustStickyOffset();
        window.addEventListener('resize', adjustStickyOffset);
        window.addEventListener('scroll', adjustStickyOffset); // In case header changes size on scroll

        // Dropdown Toggle
        const dropdownWrappers = document.querySelectorAll('.js-prs-dropdown-toggle');

        dropdownWrappers.forEach(wrapper => {
            wrapper.addEventListener('click', function (e) {
                // Prevent closing if clicking inside the menu
                if (e.target.closest('.prs-dropdown-menu')) {
                    return;
                }

                e.stopPropagation();

                // Close other dropdowns
                dropdownWrappers.forEach(w => {
                    if (w !== wrapper) {
                        w.querySelector('.prs-dropdown-menu').classList.remove('active');
                    }
                });

                const menu = this.querySelector('.prs-dropdown-menu');
                menu.classList.toggle('active');
            });
        });

        // Close when clicking outside
        document.addEventListener('click', function () {
            dropdownWrappers.forEach(wrapper => {
                wrapper.querySelector('.prs-dropdown-menu').classList.remove('active');
            });
        });

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

        // Comments
        function attachCommentListeners() {
            const forms = document.querySelectorAll('.prs-submit-comment-form');

            forms.forEach(form => {
                if (form.dataset.bound) return;
                form.dataset.bound = true;

                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const noteId = this.dataset.noteId;
                    const textarea = this.querySelector('textarea');
                    const button = this.querySelector('button');
                    const content = textarea.value.trim();
                    const closestSection = this.closest('.prs-comments-section');
                    const listContainer = closestSection
                        ? closestSection.querySelector('.prs-comments-list')
                        : document.getElementById(`prs-comments-list-${noteId}`);

                    if (!content) return;

                    textarea.disabled = true;
                    button.disabled = true;
                    button.innerText = '<?php echo esc_js(__('Publishing...', 'politeia-bookshelf')); ?>';

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
                                const newComment = document.createElement('div');
                                newComment.className = 'prs-comment-item';
                                newComment.style.cssText = 'margin-bottom: 0.8rem; border-bottom: 1px solid #dfdfdf; padding-bottom: 0.8rem; animation: highlight 1s ease;';
                                newComment.innerHTML = `
                                <div style="font-size: 0.85rem; font-weight: 600; color: #555; margin-bottom: 0.2rem;">
                                    ${data.data.author} <span style="font-weight: normal; color: #999;">• ${data.data.date}</span>
                                </div>
                                <div style="font-size: 0.95rem; line-height: 1.4; color: #333;">
                                    ${data.data.content}
                                </div>
                            `;
                                listContainer.appendChild(newComment);
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
                            button.innerText = '<?php echo esc_js(__('Publish', 'politeia-bookshelf')); ?>';
                        });
                });
            });
        }

        attachCommentListeners();
    });
</script>

<?php
get_footer();
