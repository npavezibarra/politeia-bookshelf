<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Politeia_Loan_Manager {
        const DEFAULT_STATE = 'in_shelf';

        /**
         * Return map of allowed transitions.
         *
         * @return array<string, string[]>
         */
        public static function allowed_transitions() {
                return array(
                        'in_shelf'  => array( 'borrowing', 'sold', 'lost' ),
                        'borrowing' => array( 'in_shelf', 'sold', 'lost' ),
                        'borrowed'  => array( 'in_shelf' ),
                        'sold'      => array(),
                        'lost'      => array( 'in_shelf' ),
                );
        }

        /**
         * Normalize stored values into canonical states.
         *
         * @param string|null $state Raw state value.
         * @return string Normalized state.
         */
        public static function normalize_state( $state ) {
                $value = is_string( $state ) ? strtolower( trim( $state ) ) : '';
                if ( '' === $value || 'in-shelf' === $value ) {
                        return self::DEFAULT_STATE;
                }

                $map = self::allowed_transitions();
                if ( array_key_exists( $value, $map ) ) {
                        return $value;
                }

                return self::DEFAULT_STATE;
        }

        /**
         * Return a human-readable label for a state.
         *
         * @param string $state Normalized state.
         * @return string
         */
        private static function format_state_label( $state ) {
                $labels = array(
                        'in_shelf'  => __( 'In Shelf', 'politeia-reading' ),
                        'borrowing' => __( 'Borrowing', 'politeia-reading' ),
                        'borrowed'  => __( 'Borrowed', 'politeia-reading' ),
                        'sold'      => __( 'Sold', 'politeia-reading' ),
                        'lost'      => __( 'Lost', 'politeia-reading' ),
                );

                return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
        }

        /**
         * Validate transition between two states.
         *
         * @param string $from Current state.
         * @param string $to   Requested state.
         * @param array  $context Additional context data.
         * @return true|WP_Error
         */
        public static function validate_transition( $from, $to, $context = array() ) {
                $current = self::normalize_state( $from );
                $next    = self::normalize_state( $to );

                if ( $current === $next ) {
                        return true;
                }

                $allowed = self::allowed_transitions();
                if ( ! isset( $allowed[ $current ] ) || ! in_array( $next, $allowed[ $current ], true ) ) {
                        return new WP_Error(
                                'invalid_transition',
                                sprintf(
                                        /* translators: 1: current status, 2: new status */
                                        __( 'Cannot change from %1$s to %2$s.', 'politeia-reading' ),
                                        self::format_state_label( $current ),
                                        self::format_state_label( $next )
                                )
                        );
                }

                if ( 'borrowing' === $current && 'sold' === $next ) {
                        $type = isset( $context['transaction_type'] ) ? sanitize_key( $context['transaction_type'] ) : '';
                        if ( 'bought_by_borrower' !== $type ) {
                                return new WP_Error(
                                        'invalid_transition',
                                        __( 'Only the borrower can buy or compensate for the book you lent.', 'politeia-reading' )
                                );
                        }
                }

                return true;
        }

        /**
         * Record transition to loans table.
         *
         * @param int    $user_id User ID.
         * @param int    $book_id Book ID.
         * @param string $from    Previous state.
         * @param string $to      Next state.
         * @param array  $args    Extra data (counterparty_name, counterparty_email, transaction_type).
         * @return void
         */
        public static function record_transition( $user_id, $book_id, $from, $to, $args = array() ) {
                $current = self::normalize_state( $from );
                $next    = self::normalize_state( $to );

                if ( $current === $next ) {
                        return;
                }

                global $wpdb;
                $table = $wpdb->prefix . 'politeia_loans';

                $now_gmt = current_time( 'mysql', true );

                $payload = array(
                        'state' => $next,
                        'from'  => $current,
                );

                if ( ! empty( $args['transaction_type'] ) ) {
                        $payload['transaction_type'] = sanitize_key( $args['transaction_type'] );
                }

                $notes = wp_json_encode( $payload );

                $counterparty_name  = isset( $args['counterparty_name'] ) ? $args['counterparty_name'] : null;
                $counterparty_email = isset( $args['counterparty_email'] ) ? $args['counterparty_email'] : null;

                $end_date = $now_gmt;

                $wpdb->insert(
                        $table,
                        array(
                                'user_id'            => (int) $user_id,
                                'book_id'            => (int) $book_id,
                                'counterparty_name'  => $counterparty_name ?: null,
                                'counterparty_email' => $counterparty_email ?: null,
                                'start_date'         => $now_gmt,
                                'end_date'           => $end_date,
                                'notes'              => $notes,
                                'created_at'         => $now_gmt,
                                'updated_at'         => $now_gmt,
                        ),
                        array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                );
        }

        /**
         * Retrieve the latest known state for a user/book pair.
         *
         * @param int $user_id User ID.
         * @param int $book_id Book ID.
         * @return string
         */
        public static function get_current_state( $user_id, $book_id ) {
                global $wpdb;

                $user_id = (int) $user_id;
                $book_id = (int) $book_id;

                if ( $user_id <= 0 || $book_id <= 0 ) {
                        return self::DEFAULT_STATE;
                }

                $user_books_table = $wpdb->prefix . 'politeia_user_books';
                $state            = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT owning_status FROM {$user_books_table} WHERE user_id=%d AND book_id=%d LIMIT 1",
                                $user_id,
                                $book_id
                        )
                );

                if ( ! empty( $state ) ) {
                        return self::normalize_state( $state );
                }

                $loans_table = $wpdb->prefix . 'politeia_loans';
                $notes       = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT notes FROM {$loans_table} WHERE user_id=%d AND book_id=%d ORDER BY id DESC LIMIT 1",
                                $user_id,
                                $book_id
                        )
                );

                if ( $notes ) {
                        $decoded = json_decode( $notes, true );
                        if ( is_array( $decoded ) && isset( $decoded['state'] ) ) {
                                return self::normalize_state( $decoded['state'] );
                        }
                        if ( is_string( $notes ) ) {
                                return self::normalize_state( $notes );
                        }
                }

                return self::DEFAULT_STATE;
        }
}
