<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

function politeia_book_dedup_table_shortcode() {
        if ( ! current_user_can( 'manage_politeia_books' ) ) {
                return '<p>' . esc_html__( 'You do not have permission to view this table.', 'politeia-reading' ) . '</p>';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'politeia_book_candidates';

        $candidates = $wpdb->get_results(
                "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY total_score DESC, id ASC"
        );

        if ( empty( $candidates ) ) {
                return '<p>' . esc_html__( 'No pending deduplication candidates found.', 'politeia-reading' ) . '</p>';
        }

        wp_enqueue_style( 'politeia-dedup' );
        wp_enqueue_script( 'politeia-dedup' );

        wp_localize_script(
                'politeia-dedup',
                'PoliteiaDedup',
                array(
                        'ajax_url'       => admin_url( 'admin-ajax.php' ),
                        'nonce'          => wp_create_nonce( 'politeia_dedup_action' ),
                        'error_message'  => __( 'Something went wrong. Please refresh and try again.', 'politeia-reading' ),
                )
        );

        ob_start();
        ?>
        <table class="politeia-dedup-table">
                <thead>
                        <tr>
                                <th><?php esc_html_e( 'Original', 'politeia-reading' ); ?></th>
                                <th><?php esc_html_e( 'Candidate', 'politeia-reading' ); ?></th>
                                <th><?php esc_html_e( 'Scores', 'politeia-reading' ); ?></th>
                                <th><?php esc_html_e( 'Total %', 'politeia-reading' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'politeia-reading' ); ?></th>
                        </tr>
                </thead>
                <tbody>
                        <?php foreach ( $candidates as $candidate ) : ?>
                                <tr data-candidate-id="<?php echo esc_attr( $candidate->id ); ?>">
                                        <td>
                                                <strong><?php echo esc_html( $candidate->original_title ); ?></strong><br />
                                                <span><?php echo esc_html( politeia_book_dedup_format_authors( $candidate->original_authors ) ); ?></span>
                                        </td>
                                        <td>
                                                <strong><?php echo esc_html( $candidate->candidate_title ?: __( 'Unknown title', 'politeia-reading' ) ); ?></strong><br />
                                                <span><?php echo esc_html( politeia_book_dedup_format_authors( $candidate->candidate_authors ) ); ?></span>
                                        </td>
                                        <td class="dedup-scores">
                                                <strong><?php esc_html_e( 'Title', 'politeia-reading' ); ?>:</strong> <?php echo esc_html( (string) (int) $candidate->title_score ); ?><br />
                                                <strong><?php esc_html_e( 'Author', 'politeia-reading' ); ?>:</strong> <?php echo esc_html( (string) (int) $candidate->author_score ); ?><br />
                                                <strong><?php esc_html_e( 'Year', 'politeia-reading' ); ?>:</strong> <?php echo esc_html( (string) (int) $candidate->year_score ); ?>
                                        </td>
                                        <td>
                                                <?php echo esc_html( max( 0, (int) $candidate->total_score ) ); ?>%
                                        </td>
                                        <td class="dedup-actions">
                                                <button type="button" class="button button-primary dedup-confirm" data-candidate-id="<?php echo esc_attr( $candidate->id ); ?>" data-action="confirm">
                                                        <?php esc_html_e( 'Confirm', 'politeia-reading' ); ?>
                                                </button>
                                                <button type="button" class="button button-secondary dedup-reject" data-candidate-id="<?php echo esc_attr( $candidate->id ); ?>" data-action="reject">
                                                        <?php esc_html_e( 'Reject', 'politeia-reading' ); ?>
                                                </button>
                                        </td>
                                </tr>
                        <?php endforeach; ?>
                </tbody>
        </table>
        <?php

        return ob_get_clean();
}
add_shortcode( 'politeia_book_dedup_table', 'politeia_book_dedup_table_shortcode' );

function politeia_book_dedup_format_authors( $raw_authors ) {
        if ( empty( $raw_authors ) ) {
                return '';
        }

        if ( is_array( $raw_authors ) ) {
                $authors = $raw_authors;
        } else {
                $authors = preg_split( '/[;,\|]+/', (string) $raw_authors );
        }

        if ( ! is_array( $authors ) ) {
                $authors = array( $authors );
        }

        $authors = array_map( 'trim', $authors );
        $authors = array_filter( $authors, static function ( $author ) {
                return '' !== $author;
        } );

        if ( empty( $authors ) ) {
                return '';
        }

        $authors = array_map( 'sanitize_text_field', $authors );

        return implode( ', ', $authors );
}
