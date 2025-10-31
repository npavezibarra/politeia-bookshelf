<?php
/**
 * User Books AJAX handlers (Loans, estados y metadatos)
 * - "In Shelf" es estado DERIVADO: owning_status NULL/'' => In Shelf
 * - owning_status válido (persistido): borrowed, borrowing, sold, lost
 * - Loans idempotentes (a lo más 1 abierto por (user, book))
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_User_Books {

        public static function init() {
                add_action( 'wp_ajax_prs_update_user_book', array( __CLASS__, 'ajax_update_user_book' ) );
                add_action( 'wp_ajax_prs_update_user_book_meta', array( __CLASS__, 'ajax_update_user_book_meta' ) );
                add_action( 'wp_ajax_prs_update_pages', array( __CLASS__, 'ajax_update_pages' ) );
                add_action( 'wp_ajax_save_owning_contact', array( __CLASS__, 'ajax_save_owning_contact' ) );
                add_action( 'wp_ajax_mark_as_returned', array( __CLASS__, 'ajax_mark_as_returned' ) );
                add_action( 'wp_ajax_politeia_bookshelf_search_cover', array( __CLASS__, 'ajax_search_cover' ) );
                add_action( 'wp_ajax_politeia_bookshelf_save_cover', array( __CLASS__, 'ajax_save_cover' ) );
        }

	/*
	============================================================
	 * AJAX: update simple (reading_status / owning_status derivado)
	 * ============================================================ */
	public static function ajax_update_user_book() {
		if ( ! is_user_logged_in() ) {
			self::json_error( 'auth', 401 );
		}
		if ( ! self::verify_nonce( 'prs_update_user_book', array( 'prs_update_user_book_nonce', 'nonce' ) ) ) {
			self::json_error( 'bad_nonce', 403 );
		}

		$user_id      = get_current_user_id();
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		if ( ! $user_book_id ) {
			self::json_error( 'invalid_id', 400 );
		}

		$row = self::get_user_book_row( $user_book_id, $user_id );
		if ( ! $row ) {
			self::json_error( 'forbidden', 403 );
		}

		$update = array();

		// reading_status (opcional)
		if ( isset( $_POST['reading_status'] ) ) {
			$rs = sanitize_key( wp_unslash( $_POST['reading_status'] ) );
			if ( in_array( $rs, self::allowed_reading_status(), true ) ) {
				$update['reading_status'] = $rs;
			}
		}

                // owning_status (DERIVADO: vacío => volver a In Shelf)
                if ( array_key_exists( 'owning_status', $_POST ) ) {
                        if ( 'd' === (string) $row->type_book ) {
                                self::json_error( __( 'Owning status is available only for printed copies.', 'politeia-reading' ), 400 );
                        }

                        $raw        = wp_unslash( $_POST['owning_status'] );
                        $sanitized  = is_string( $raw ) ? sanitize_key( $raw ) : '';
                        $now        = current_time( 'mysql', true );
                        $current    = Politeia_Loan_Manager::normalize_state( $row->owning_status );
                        $requested  = '';

                        if ( $raw === '' || null === $raw || 'in_shelf' === $sanitized ) {
                                $requested = '';
                        } else {
                                $requested = $sanitized;
                        }

                        $next_state = Politeia_Loan_Manager::normalize_state( $requested );
                        $validation = Politeia_Loan_Manager::validate_transition( $current, $next_state );

                        if ( is_wp_error( $validation ) ) {
                                self::json_error( $validation->get_error_message(), 400 );
                        }

                        $state_changed = ( $current !== $next_state );

                        if ( Politeia_Loan_Manager::DEFAULT_STATE === $next_state ) {
                                $update['owning_status']      = null;
                                $update['counterparty_name']  = null;
                                $update['counterparty_email'] = null;
                                self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now );
                                if ( $state_changed ) {
                                        Politeia_Loan_Manager::record_transition(
                                                (int) $row->user_id,
                                                (int) $row->book_id,
                                                $current,
                                                $next_state,
                                                array(
                                                        'counterparty_name'  => null,
                                                        'counterparty_email' => null,
                                                )
                                        );
                                }
                        } elseif ( in_array( $requested, self::allowed_owning_status(), true ) ) {
                                $update['owning_status'] = $requested;

                                if ( in_array( $requested, array( 'borrowed', 'borrowing' ), true ) ) {
                                        self::ensure_open_loan(
                                                (int) $row->user_id,
                                                (int) $row->book_id,
                                                array(
                                                        'owning_status' => $next_state,
                                                ),
                                                $now
                                        );
                                } else {
                                        self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now );
                                }

                                if ( 'lost' === $requested ) {
                                        $update['counterparty_name']  = null;
                                        $update['counterparty_email'] = null;
                                }

                                if ( $state_changed ) {
                                        Politeia_Loan_Manager::record_transition(
                                                (int) $row->user_id,
                                                (int) $row->book_id,
                                                $current,
                                                $next_state,
                                                array(
                                                        'counterparty_name'  => $update['counterparty_name'] ?? $row->counterparty_name,
                                                        'counterparty_email' => $update['counterparty_email'] ?? $row->counterparty_email,
                                                )
                                        );
                                }
                        } else {
                                self::json_error( __( 'Invalid owning status.', 'politeia-reading' ), 400 );
                        }
                }

		if ( empty( $update ) ) {
			self::json_error( 'no_fields', 400 );
		}

		$updated = self::update_user_book( $user_book_id, $update );
		self::json_success( $updated );
	}

	/*
	==================================================================================
	 * AJAX: update meta granular (pages, purchase_*, contact, reading_status, rating)
	 * ================================================================================== */
        public static function ajax_update_user_book_meta() {
                if ( ! is_user_logged_in() ) {
                        self::json_error( 'auth', 401 );
                }

		// Acepta cualquiera de los dos nonces
		if ( ! self::verify_nonce_multi(
			array(
				array(
					'action' => 'prs_update_user_book_meta',
					'keys'   => array( 'nonce' ),
				),
				array(
					'action' => 'prs_update_user_book',
					'keys'   => array( 'prs_update_user_book_nonce' ),
				),
			)
		) ) {
			self::json_error( 'bad_nonce', 403 );
		}

		$user_id      = get_current_user_id();
		$user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
		if ( ! $user_book_id ) {
			self::json_error( 'invalid_id', 400 );
		}

		$row = self::get_user_book_row( $user_book_id, $user_id );
		if ( ! $row ) {
			self::json_error( 'forbidden', 403 );
		}
		if ( empty( $row->book_id ) ) {
			self::json_error( 'missing_book_id', 500 );
		}

		$update = array();

		// ====== METADATOS ======
		if ( array_key_exists( 'pages', $_POST ) ) {
			$p               = absint( $_POST['pages'] );
			$update['pages'] = $p > 0 ? $p : null;
		}
		if ( array_key_exists( 'purchase_date', $_POST ) ) {
			$d                       = sanitize_text_field( wp_unslash( $_POST['purchase_date'] ) );
			$update['purchase_date'] = ( $d && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) ? $d : null;
		}
                if ( array_key_exists( 'purchase_channel', $_POST ) ) {
                        $pc                         = sanitize_key( $_POST['purchase_channel'] );
                        $update['purchase_channel'] = in_array( $pc, array( 'online', 'store' ), true ) ? $pc : null;
                }
                if ( array_key_exists( 'purchase_place', $_POST ) ) {
                        $update['purchase_place'] = sanitize_text_field( wp_unslash( $_POST['purchase_place'] ) );
                }
                if ( array_key_exists( 'type_book', $_POST ) ) {
                        $raw = wp_unslash( $_POST['type_book'] );
                        $tb  = sanitize_key( $raw );

                        if ( in_array( $tb, array( 'p', 'd' ), true ) ) {
                                $update['type_book'] = $tb;
                        } elseif ( '' === $raw || null === $raw ) {
                                $update['type_book'] = null;
                        }
                }
                if ( array_key_exists( 'reading_status', $_POST ) ) {
                        $rs = sanitize_key( wp_unslash( $_POST['reading_status'] ) );
                        if ( in_array( $rs, self::allowed_reading_status(), true ) ) {
                                $update['reading_status'] = $rs;
                        }
		}

		// ====== RATING ======
		if ( array_key_exists( 'rating', $_POST ) ) {
			$r = is_numeric( $_POST['rating'] ) ? (int) $_POST['rating'] : null;
			if ( is_int( $r ) ) {
				if ( $r < 0 ) {
					$r = 0;
				}
				if ( $r > 5 ) {
					$r = 5;
				}
				$update['rating'] = $r;
			} else {
				$update['rating'] = null; // permitir limpiar
			}
		}

		// ====== CONTACTO ======
		$cp_name_raw  = array_key_exists( 'counterparty_name', $_POST ) ? wp_unslash( $_POST['counterparty_name'] ) : null;
		$cp_email_raw = array_key_exists( 'counterparty_email', $_POST ) ? wp_unslash( $_POST['counterparty_email'] ) : null;
		$cp_name      = isset( $cp_name_raw ) ? sanitize_text_field( $cp_name_raw ) : null;
		$cp_email     = isset( $cp_email_raw ) ? sanitize_email( $cp_email_raw ) : null;

		$both_empty           = ( '' === trim( (string) $cp_name ) ) && ( '' === trim( (string) $cp_email ) );
		$requires_contact_now = in_array( $row->owning_status, array( 'borrowed', 'borrowing', 'sold' ), true );

		if ( ( $both_empty ) && ( $requires_contact_now )
			&& ( array_key_exists( 'counterparty_name', $_POST ) || array_key_exists( 'counterparty_email', $_POST ) ) ) {
			self::json_error( 'contact_required', 400 );
		}

		if ( array_key_exists( 'counterparty_name', $_POST ) ) {
			$update['counterparty_name'] = $cp_name;
		}
		if ( array_key_exists( 'counterparty_email', $_POST ) ) {
			$update['counterparty_email'] = ( $cp_email && is_email( $cp_email ) ) ? $cp_email : null;
		}

		// ====== FECHA EFECTIVA (UTC) ======
		$effective_at = null;
		if ( ! empty( $_POST['owning_effective_date'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_POST['owning_effective_date'] ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				$effective_at = $raw . ' ' . gmdate( 'H:i:s' );
			}
		}
		if ( ! $effective_at ) {
			$effective_at = current_time( 'mysql', true );
		}

                // ====== OWNING STATUS (DERIVADO) ======
                if ( array_key_exists( 'owning_status', $_POST ) ) {
                        if ( 'd' === (string) $row->type_book ) {
                                self::json_error( __( 'Owning status is available only for printed copies.', 'politeia-reading' ), 400 );
                        }

                        $raw             = wp_unslash( $_POST['owning_status'] );
                        $sanitized_state = is_string( $raw ) ? sanitize_key( $raw ) : '';
                        $current_state   = Politeia_Loan_Manager::normalize_state( $row->owning_status );
                        $requested_state = '';

                        if ( '' === $raw || null === $raw || 'in_shelf' === $sanitized_state ) {
                                $requested_state = '';
                        } else {
                                $requested_state = $sanitized_state;
                        }

                        $next_state = Politeia_Loan_Manager::normalize_state( $requested_state );
                        if ( '' !== $requested_state && ! in_array( $requested_state, self::allowed_owning_status(), true ) && 'in_shelf' !== $requested_state ) {
                                self::json_error( __( 'Invalid owning status.', 'politeia-reading' ), 400 );
                        }

                        $validation = Politeia_Loan_Manager::validate_transition( $current_state, $next_state );
                        if ( is_wp_error( $validation ) ) {
                                self::json_error( $validation->get_error_message(), 400 );
                        }

                        $state_changed = ( $current_state !== $next_state );

                        if ( Politeia_Loan_Manager::DEFAULT_STATE === $next_state ) {
                                $update['owning_status']      = null;
                                $update['counterparty_name']  = null;
                                $update['counterparty_email'] = null;
                                self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $effective_at );

                                if ( $state_changed ) {
                                        Politeia_Loan_Manager::record_transition(
                                                (int) $row->user_id,
                                                (int) $row->book_id,
                                                $current_state,
                                                $next_state,
                                                array(
                                                        'counterparty_name'  => null,
                                                        'counterparty_email' => null,
                                                )
                                        );
                                }
                        } else {
                                $update['owning_status'] = $requested_state;

                                if ( in_array( $next_state, array( 'borrowed', 'borrowing' ), true ) ) {
                                        self::ensure_open_loan(
                                                (int) $row->user_id,
                                                (int) $row->book_id,
                                                array(
                                                        'counterparty_name'  => $cp_name,
                                                        'counterparty_email' => ( $cp_email && is_email( $cp_email ) ) ? $cp_email : null,
                                                        'owning_status'      => $next_state,
                                                ),
                                                $effective_at
                                        );
                                } else {
                                        self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $effective_at );
                                }

                                if ( 'lost' === $next_state ) {
                                        $update['counterparty_name']  = null;
                                        $update['counterparty_email'] = null;
                                }

                                if ( $state_changed ) {
                                        Politeia_Loan_Manager::record_transition(
                                                (int) $row->user_id,
                                                (int) $row->book_id,
                                                $current_state,
                                                $next_state,
                                                array(
                                                        'counterparty_name'  => $update['counterparty_name'] ?? ( $cp_name ?: null ),
                                                        'counterparty_email' => $update['counterparty_email'] ?? ( ( $cp_email && is_email( $cp_email ) ) ? $cp_email : null ),
                                                )
                                        );
                                }
                        }
                } else {
                        // No cambió owning_status: si llega contacto y el estado actual requiere,
                        // actualiza el loan abierto (no crear uno nuevo si no corresponde)
                        if ( ( $cp_name || $cp_email ) && in_array( $row->owning_status, array( 'borrowed', 'borrowing' ), true ) ) {
                                self::ensure_open_loan(
                                        (int) $row->user_id,
                                        (int) $row->book_id,
                                        array(
                                                'counterparty_name'  => $cp_name,
                                                'counterparty_email' => ( $cp_email && is_email( $cp_email ) ) ? $cp_email : null,
                                                'owning_status'      => Politeia_Loan_Manager::normalize_state( $row->owning_status ),
                                        ),
                                        $effective_at
                                );
                        }
                }

		if ( empty( $update ) ) {
			self::json_error( 'no_fields', 400 );
		}

                $updated = self::update_user_book( $user_book_id, $update );
                self::json_success( $updated );
        }

        /**
         * AJAX: guarda contacto + owning_status desde overlay.
         */
        public static function ajax_save_owning_contact() {
                if ( ! is_user_logged_in() ) {
                        self::json_error( __( 'You must be logged in.', 'politeia-reading' ), 401 );
                }

                if ( ! self::verify_nonce( 'save_owning_contact', array( 'nonce' ) ) ) {
                        self::json_error( __( 'Security check failed.', 'politeia-reading' ), 403 );
                }

                $user_id      = get_current_user_id();
                $book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
                $user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;

                if ( ! $book_id || ! $user_book_id ) {
                        self::json_error( __( 'Invalid book.', 'politeia-reading' ), 400 );
                }

                $row = self::get_user_book_row( $user_book_id, $user_id );
                if ( ! $row || (int) $row->book_id !== $book_id ) {
                        self::json_error( __( 'Book not found in your library.', 'politeia-reading' ), 403 );
                }

                if ( 'd' === (string) $row->type_book ) {
                        self::json_error( __( 'Owning status is available only for printed copies.', 'politeia-reading' ), 400 );
                }

                $status_raw = isset( $_POST['owning_status'] ) ? wp_unslash( $_POST['owning_status'] ) : '';
                $status_key = is_string( $status_raw ) ? sanitize_key( $status_raw ) : '';
                $is_reacquire = ( 'bought' === $status_key );
                $transaction_raw = isset( $_POST['transaction_type'] ) ? wp_unslash( $_POST['transaction_type'] ) : '';
                $transaction_type = $transaction_raw ? sanitize_key( $transaction_raw ) : '';

                $current_state = Politeia_Loan_Manager::normalize_state( $row->owning_status );
                $requested_status = '';
                if ( '' === $status_raw || null === $status_raw || 'in_shelf' === $status_key || $is_reacquire ) {
                        $requested_status = '';
                } else {
                        $requested_status = $status_key;
                }

                $next_state = $is_reacquire
                        ? Politeia_Loan_Manager::DEFAULT_STATE
                        : Politeia_Loan_Manager::normalize_state( $requested_status );
                $validation = Politeia_Loan_Manager::validate_transition(
                        $current_state,
                        $next_state,
                        array(
                                'transaction_type' => $transaction_type,
                                'requested_state'  => $status_key,
                        )
                );

                if ( is_wp_error( $validation ) ) {
                        self::json_error( $validation->get_error_message(), 400 );
                }

                if ( '' !== $requested_status && ! in_array( $requested_status, self::allowed_owning_status(), true ) && 'in_shelf' !== $requested_status ) {
                        self::json_error( __( 'Invalid owning status.', 'politeia-reading' ), 400 );
                }

                $name_raw        = isset( $_POST['contact_name'] ) ? wp_unslash( $_POST['contact_name'] ) : '';
                $email_raw       = isset( $_POST['contact_email'] ) ? wp_unslash( $_POST['contact_email'] ) : '';
                $name_sanitized  = sanitize_text_field( $name_raw );
                $name_trimmed    = trim( $name_sanitized );
                $email_sanitized = sanitize_email( $email_raw );
                $email_trimmed   = $email_sanitized ? $email_sanitized : '';

                $amount_raw = isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : '';
                $amount_value = null;
                if ( '' !== $amount_raw && null !== $amount_raw ) {
                        if ( is_string( $amount_raw ) ) {
                                $normalized_amount = str_replace( ',', '.', trim( $amount_raw ) );
                        } else {
                                $normalized_amount = $amount_raw;
                        }

                        if ( is_numeric( $normalized_amount ) ) {
                                $amount_value = round( (float) $normalized_amount, 2 );
                        }
                }

                $requires_contact = in_array( $next_state, array( 'borrowed', 'borrowing', 'sold' ), true );

                if ( $requires_contact && ( '' === $name_trimmed || '' === $email_trimmed ) ) {
                        self::json_error( __( 'Please enter both name and email.', 'politeia-reading' ), 400 );
                }

                $update        = array();
                $now           = current_time( 'mysql', true );
                $safe_name     = '' === $name_trimmed ? null : $name_trimmed;
                $safe_email    = '' === $email_trimmed ? null : $email_trimmed;
                $state_changed = ( $current_state !== $next_state );

                if ( Politeia_Loan_Manager::DEFAULT_STATE === $next_state ) {
                        $update['owning_status']      = null;
                        $update['counterparty_name']  = null;
                        $update['counterparty_email'] = null;
                        self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now );
                } else {
                        $update['owning_status']      = $requested_status;
                        $update['counterparty_name']  = $safe_name;
                        $update['counterparty_email'] = $safe_email;

                        if ( in_array( $next_state, array( 'borrowed', 'borrowing' ), true ) ) {
                                self::ensure_open_loan(
                                        (int) $row->user_id,
                                        (int) $row->book_id,
                                        array(
                                                'counterparty_name'  => $safe_name,
                                                'counterparty_email' => $safe_email,
                                                'owning_status'      => $next_state,
                                                'transaction_type'   => $transaction_type,
                                        ),
                                        $now
                                );
                        } else {
                                self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now );
                        }

                        if ( 'lost' === $next_state ) {
                                $update['counterparty_name']  = null;
                                $update['counterparty_email'] = null;
                        }
                }

                self::update_user_book( (int) $row->id, $update );

                if ( $state_changed ) {
                        Politeia_Loan_Manager::record_transition(
                                (int) $row->user_id,
                                (int) $row->book_id,
                                $current_state,
                                $next_state,
                                array(
                                        'counterparty_name'  => $update['counterparty_name'] ?? $safe_name,
                                        'counterparty_email' => $update['counterparty_email'] ?? $safe_email,
                                        'transaction_type'   => $transaction_type,
                                        'amount'             => ( 'sold' === $next_state ) ? $amount_value : null,
                                )
                        );
                }

                self::json_success(
                        array(
                                'message'            => __( 'Contact saved', 'politeia-reading' ),
                                'owning_status'      => Politeia_Loan_Manager::DEFAULT_STATE === $next_state ? '' : $requested_status,
                                'counterparty_name'  => $name_trimmed,
                                'counterparty_email' => $email_trimmed,
                        )
                );
        }

        /**
         * AJAX: marca un libro prestado como devuelto.
         */
        public static function ajax_mark_as_returned() {
                if ( ! is_user_logged_in() ) {
                        self::json_error( __( 'You must be logged in.', 'politeia-reading' ), 401 );
                }

                if ( ! self::verify_nonce( 'save_owning_contact', array( 'nonce' ) ) ) {
                        self::json_error( __( 'Security check failed.', 'politeia-reading' ), 403 );
                }

                $user_id      = get_current_user_id();
                $book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
                $user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;

                if ( ! $book_id ) {
                        self::json_error( __( 'Invalid book.', 'politeia-reading' ), 400 );
                }

                if ( $user_book_id ) {
                        $row = self::get_user_book_row( $user_book_id, $user_id );
                } else {
                        $row = self::get_user_book_by_book( $user_id, $book_id );
                }

                if ( ! $row || (int) $row->book_id !== $book_id ) {
                        self::json_error( __( 'Book not found in your library.', 'politeia-reading' ), 403 );
                }

                if ( 'd' === (string) $row->type_book ) {
                        self::json_error( __( 'Owning status is available only for printed copies.', 'politeia-reading' ), 400 );
                }

                $current_state = Politeia_Loan_Manager::normalize_state( $row->owning_status );
                $validation    = Politeia_Loan_Manager::validate_transition( $current_state, Politeia_Loan_Manager::DEFAULT_STATE );
                if ( is_wp_error( $validation ) ) {
                        self::json_error( $validation->get_error_message(), 400 );
                }

                global $wpdb;
                $table = self::loans_table();
                $loan  = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT counterparty_name, counterparty_email FROM {$table} WHERE user_id=%d AND book_id=%d AND notes LIKE %s ORDER BY id DESC LIMIT 1",
                                (int) $row->user_id,
                                $book_id,
                                '%"state":"borrowing"%'
                        )
                );

                $counterparty_name  = $loan && ! empty( $loan->counterparty_name ) ? $loan->counterparty_name : $row->counterparty_name;
                $counterparty_email = $loan && ! empty( $loan->counterparty_email ) ? $loan->counterparty_email : $row->counterparty_email;

                $now_gmt = current_time( 'mysql', true );

                self::update_user_book(
                        (int) $row->id,
                        array(
                                'owning_status'      => null,
                                'counterparty_name'  => null,
                                'counterparty_email' => null,
                        )
                );

                self::close_open_loan( (int) $row->user_id, (int) $row->book_id, $now_gmt, 'returned' );

                Politeia_Loan_Manager::record_transition(
                        (int) $row->user_id,
                        (int) $row->book_id,
                        $current_state,
                        Politeia_Loan_Manager::DEFAULT_STATE,
                        array(
                                'counterparty_name'  => $counterparty_name ? $counterparty_name : null,
                                'counterparty_email' => $counterparty_email ? $counterparty_email : null,
                        )
                );

                self::json_success(
                        array(
                                'message'       => __( 'Book marked as returned.', 'politeia-reading' ),
                                'owning_status' => '',
                                'loan_closed'   => get_date_from_gmt( $now_gmt, 'Y-m-d' ),
                        )
                );
        }

        /**
         * AJAX: inline update for pages field.
         */
        public static function ajax_update_pages() {
                if ( ! is_user_logged_in() ) {
                        self::json_error( 'auth', 401 );
                }

                if ( ! self::verify_nonce_multi(
                        array(
                                array(
                                        'action' => 'prs_update_user_book_meta',
                                        'keys'   => array( 'nonce' ),
                                ),
                                array(
                                        'action' => 'prs_update_user_book',
                                        'keys'   => array( 'prs_update_user_book_nonce' ),
                                ),
                        )
                ) ) {
                        self::json_error( 'bad_nonce', 403 );
                }

                $user_book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
                $pages        = isset( $_POST['pages'] ) ? absint( $_POST['pages'] ) : 0;

                if ( ! $user_book_id || $pages < 1 ) {
                        self::json_error( __( 'Invalid data', 'politeia-reading' ), 400 );
                }

                $row = self::get_user_book_row( $user_book_id, get_current_user_id() );
                if ( ! $row ) {
                        self::json_error( 'forbidden', 403 );
                }

                self::update_user_book(
                        $user_book_id,
                        array(
                                'pages' => $pages,
                        )
                );

                self::json_success(
                        array(
                                'pages' => $pages,
                        )
                );
        }

        /**
         * AJAX: busca cubiertas en Google Books para el libro actual.
         */
        public static function ajax_search_cover() {
                if ( ! is_user_logged_in() ) {
                        self::json_error( 'auth', 401 );
                }

                $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
                if ( ! wp_verify_nonce( $nonce, 'politeia_bookshelf_cover_actions' ) ) {
                        self::json_error( 'bad_nonce', 403 );
                }

                $book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
                $user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;

                if ( ! $book_id ) {
                        self::json_error( 'invalid_book', 400 );
                }

                $user_id = get_current_user_id();
                if ( $user_book_id ) {
                        $row = self::get_user_book_row( $user_book_id, $user_id );
                } else {
                        $row = self::get_user_book_by_book( $user_id, $book_id );
                }

                if ( ! $row ) {
                        self::json_error( 'forbidden', 403 );
                }

                global $wpdb;
                $books_table = $wpdb->prefix . 'politeia_books';
                $book        = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id, title, author FROM {$books_table} WHERE id=%d LIMIT 1",
                                $book_id
                        )
                );

                if ( ! $book ) {
                        self::json_error( 'not_found', 404 );
                }

                $title_raw  = isset( $book->title ) ? (string) $book->title : '';
                $author_raw = isset( $book->author ) ? (string) $book->author : '';

                $title  = $title_raw ? wp_strip_all_tags( $title_raw ) : '';
                $author = $author_raw ? wp_strip_all_tags( $author_raw ) : '';

                $title  = trim( str_replace( "\"", '', $title ) );
                $author = trim( str_replace( "\"", '', $author ) );

                // --- Normalize metadata for Google Books ---
                $title  = preg_replace( '/:.*/', '', $title );
                $title  = preg_replace( '/\s+/', ' ', $title );
                $author = preg_replace( '/\([^)]*\)/', '', $author );
                $author = preg_replace( '/II|III|IV|V/', '', $author );
                $author = preg_replace( '/\s+/', ' ', $author );
                $author = trim( $author );

                if ( '' === $title && '' === $author ) {
                        self::json_error( 'missing_metadata', 400 );
                }

                $api_token = function_exists( 'politeia_bookshelf_get_google_books_api_key' )
                        ? politeia_bookshelf_get_google_books_api_key()
                        : '';
                $api_token = is_string( $api_token ) ? trim( $api_token ) : '';

                if ( '' === $api_token ) {
                        self::json_error( 'missing_api_key', 400 );
                }

                $segments = array();
                if ( $title ) {
                        $segments[] = sprintf( 'intitle:"%s"', $title );
                }
                if ( $author ) {
                        $segments[] = sprintf( 'inauthor:"%s"', $author );
                }

                $query = trim( implode( ' ', $segments ) );
                if ( '' === $query ) {
                        self::json_error( 'missing_metadata', 400 );
                }

                $url = add_query_arg(
                        array(
                                'q'          => $query,
                                'key'        => $api_token,
                                'maxResults' => 5,
                                'orderBy'    => 'relevance',
                        ),
                        'https://www.googleapis.com/books/v1/volumes'
                );

                $response = wp_remote_get(
                        $url,
                        array(
                                'timeout' => 10,
                        )
                );

                if ( is_wp_error( $response ) ) {
                        self::json_error( 'api_error', 500 );
                }

                $code = (int) wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body, true );

                if ( $code >= 400 ) {
                        self::json_error( 'api_error', $code );
                }

                if ( null === $data || ! is_array( $data ) ) {
                        self::json_error( 'api_error', 500 );
                }

               // --- Fallback #1: retry with intitle only ---
               if ( isset( $data['totalItems'] ) && (int) $data['totalItems'] === 0 && $title ) {
                       $fallback_url = add_query_arg(
                               array(
                                        'q'          => sprintf( 'intitle:"%s"', $title ),
                                        'key'        => $api_token,
                                        'maxResults' => 5,
                                        'orderBy'    => 'relevance',
                                        'printType'  => 'books',
                                ),
                                'https://www.googleapis.com/books/v1/volumes'
                        );
                        $fallback_response = wp_remote_get( $fallback_url, array( 'timeout' => 10 ) );
                        $fallback_data     = json_decode( wp_remote_retrieve_body( $fallback_response ), true );
                        if ( isset( $fallback_data['totalItems'] ) && (int) $fallback_data['totalItems'] > 0 ) {
                                $data = $fallback_data;
                        }
                }

               // --- Fallback #2: relax query if still few or irrelevant results ---
               if ( isset( $data['totalItems'] ) && (int) $data['totalItems'] <= 1 && $title ) {
                       $relaxed_query = sprintf( '"%s"', $title );
                       $relaxed_url   = add_query_arg(
                               array(
                                       'q'            => $relaxed_query,
                                       'key'          => $api_token,
                                       'maxResults'   => 5,
                                       'orderBy'      => 'relevance',
                                       'printType'    => 'books',
                                       'langRestrict' => 'es',
                               ),
                               'https://www.googleapis.com/books/v1/volumes'
                       );
                       $relaxed_response = wp_remote_get( $relaxed_url, array( 'timeout' => 10 ) );
                       $relaxed_data     = json_decode( wp_remote_retrieve_body( $relaxed_response ), true );
                       if ( isset( $relaxed_data['totalItems'] ) && (int) $relaxed_data['totalItems'] > 0 ) {
                               $data = $relaxed_data;
                       }
               }

                self::json_success( $data );
        }

        /**
         * AJAX: guarda la URL de la cubierta seleccionada.
         */
        public static function ajax_save_cover() {
                if ( ! is_user_logged_in() ) {
                        self::json_error( 'auth', 401 );
                }

                $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
                if ( ! wp_verify_nonce( $nonce, 'politeia_bookshelf_cover_actions' ) ) {
                        self::json_error( 'bad_nonce', 403 );
                }

                $book_id      = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
                $user_book_id = isset( $_POST['user_book_id'] ) ? absint( $_POST['user_book_id'] ) : 0;
                $cover_raw    = isset( $_POST['cover_url'] ) ? wp_unslash( $_POST['cover_url'] ) : '';

                $cover_raw = is_string( $cover_raw ) ? trim( $cover_raw ) : '';

                if ( '' === $cover_raw ) {
                        self::json_error( 'invalid_cover', 400 );
                }

                $cover_url = self::normalize_cover_url( $cover_raw );
                $cover_url = esc_url_raw( $cover_url );

                if ( ! $cover_url ) {
                        self::json_error( 'invalid_cover', 400 );
                }

                if ( ! $user_book_id && ! $book_id ) {
                        self::json_error( 'invalid_book', 400 );
                }

                $user_id = get_current_user_id();
                if ( $user_book_id ) {
                        $row = self::get_user_book_row( $user_book_id, $user_id );
                } else {
                        $row = self::get_user_book_by_book( $user_id, $book_id );
                }

                if ( ! $row ) {
                        self::json_error( 'forbidden', 403 );
                }

                if ( $book_id && (int) $row->book_id !== $book_id ) {
                        self::json_error( 'forbidden', 403 );
                }

                $reference = maybe_serialize(
                        array(
                                'external_cover' => $cover_url,
                        )
                );

                self::update_user_book(
                        (int) $row->id,
                        array(
                                'cover_attachment_id_user' => 0,
                                'cover_reference'          => $reference,
                                'cover_url'                => $cover_url,
                                'cover_source'             => '',
                        )
                );

                self::json_success(
                        array(
                                'cover_url'       => $cover_url,
                                'cover_reference' => $reference,
                                'user_book_id'    => (int) $row->id,
                        )
                );
        }

        private static function normalize_cover_url( $url ) {
                if ( ! is_string( $url ) ) {
                        return '';
                }

                $trimmed = trim( $url );
                if ( '' === $trimmed ) {
                        return '';
                }

                $normalized = preg_replace( '#^http://#i', 'https://', $trimmed );
                if ( ! $normalized ) {
                        return '';
                }

                $parts = wp_parse_url( $normalized );
                $host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';

                if ( $host && false !== strpos( $host, 'books.google' ) && false !== stripos( $normalized, '/books/content' ) ) {
                        if ( preg_match( '/([?&])zoom=\d+/i', $normalized ) ) {
                                $normalized = preg_replace( '/([?&]zoom=)(\d+)/i', '$13', $normalized, 1 );
                        } else {
                                $normalized .= ( false === strpos( $normalized, '?' ) ? '?' : '&' ) . 'zoom=3';
                        }
                }

                return $normalized;
        }

        /*
        =========================
         * Validaciones permitidas
	 * ========================= */
	private static function allowed_reading_status() {
		return array( 'not_started', 'started', 'finished' );
	}
	private static function allowed_owning_status() {
		// In Shelf se representa con NULL/'' (derivado)
		return array( 'borrowed', 'borrowing', 'sold', 'lost' );
	}

	/*
	=========================
	 * DB helpers
	 * ========================= */
        private static function get_user_book_row( $user_book_id, $user_id ) {
                global $wpdb;
                $t = $wpdb->prefix . 'politeia_user_books';
                return $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT * FROM {$t} WHERE id=%d AND user_id=%d AND deleted_at IS NULL LIMIT 1",
                                $user_book_id,
                                $user_id
                        )
                );
        }

        private static function get_user_book_by_book( $user_id, $book_id ) {
                global $wpdb;
                $t = $wpdb->prefix . 'politeia_user_books';
                return $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT * FROM {$t} WHERE user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
                                $user_id,
                                $book_id
                        )
                );
        }

        private static function update_user_book( $user_book_id, $update ) {
                global $wpdb;
                $t                    = $wpdb->prefix . 'politeia_user_books';
                $update['updated_at'] = current_time( 'mysql', true ); // UTC
		$wpdb->update( $t, $update, array( 'id' => $user_book_id ) );
		return $update;
	}

	/*
	==============================================
	 * LOANS: idempotentes (evitan duplicados)
	 * ============================================== */

	private static function loans_table() {
		global $wpdb;
		return $wpdb->prefix . 'politeia_loans';
	}

	private static function get_active_loan_id( $user_id, $book_id ) {
		global $wpdb;
		$t = self::loans_table();
                return (int) $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT id FROM {$t}
             WHERE user_id=%d AND book_id=%d AND end_date IS NULL AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 1",
                                $user_id,
                                $book_id
                        )
                );
	}

	/**
	 * Asegura un único loan abierto por (user, book):
	 * - Si existe, actualiza (contacto/updated_at).
	 * - Si no existe y hay contacto, inserta con start_date = $start_gmt.
	 *   (Si NO hay contacto, no crea nada).
	 */
        private static function ensure_open_loan( $user_id, $book_id, $data = array(), $start_gmt = null ) {
                global $wpdb;
                $t   = self::loans_table();
                $now = current_time( 'mysql', true );

                $state            = isset( $data['owning_status'] ) ? Politeia_Loan_Manager::normalize_state( $data['owning_status'] ) : '';
                $transaction_type = isset( $data['transaction_type'] ) ? sanitize_key( $data['transaction_type'] ) : '';
                unset( $data['owning_status'], $data['transaction_type'] );

                $notes = null;
                if ( $state && Politeia_Loan_Manager::DEFAULT_STATE !== $state ) {
                        $payload = array( 'state' => $state );
                        if ( $transaction_type ) {
                                $payload['transaction_type'] = $transaction_type;
                        }
                        $notes = wp_json_encode( $payload );
                }

                $open_id = self::get_active_loan_id( $user_id, $book_id );
                if ( $open_id ) {
                        $row = array( 'updated_at' => $now );
                        if ( array_key_exists( 'counterparty_name', $data ) ) {
                                $row['counterparty_name'] = $data['counterparty_name'];
                        }
                        if ( array_key_exists( 'counterparty_email', $data ) ) {
                                $row['counterparty_email'] = $data['counterparty_email'];
                        }
                        if ( null !== $notes ) {
                                $row['notes'] = $notes;
                        }
                        $wpdb->update( $t, $row, array( 'id' => $open_id ) );
                        return $open_id;
                }

                // Si NO hay contacto, NO insertes un loan vacío
                $has_contact = ! empty( $data['counterparty_name'] ) || ! empty( $data['counterparty_email'] );
                if ( ! $has_contact ) {
                        return 0;
                }

                // Insertar nuevo
                $start = $start_gmt ?: $now;
                $wpdb->insert(
                        $t,
                        array(
                                'user_id'            => (int) $user_id,
                                'book_id'            => (int) $book_id,
                                'counterparty_name'  => $data['counterparty_name'] ?? null,
                                'counterparty_email' => $data['counterparty_email'] ?? null,
                                'start_date'         => $start,
                                'end_date'           => null,
                                'notes'              => $notes,
                                'created_at'         => $now,
                                'updated_at'         => $now,
                        ),
                        array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                );
                return (int) $wpdb->insert_id;
        }

	/** Cierra cualquier loan abierto del par (user, book). */
        private static function close_open_loan( $user_id, $book_id, $end_gmt, $status = null ) {
                global $wpdb;
                $t   = self::loans_table();
                $now = current_time( 'mysql', true );
                if ( $status ) {
                        $wpdb->query(
                                $wpdb->prepare(
                                        "UPDATE {$t}
             SET status=%s, end_date=%s, updated_at=%s
             WHERE user_id=%d AND book_id=%d AND end_date IS NULL AND deleted_at IS NULL",
                                        $status,
                                        $end_gmt,
                                        $now,
                                        $user_id,
                                        $book_id
                                )
                        );
                } else {
                        $wpdb->query(
                                $wpdb->prepare(
                                        "UPDATE {$t}
             SET end_date=%s, updated_at=%s
             WHERE user_id=%d AND book_id=%d AND end_date IS NULL AND deleted_at IS NULL",
                                        $end_gmt,
                                        $now,
                                        $user_id,
                                        $book_id
                                )
                        );
                }
        }

	/*
	=========================
	 * Nonces & JSON helpers
	 * ========================= */
	private static function verify_nonce( $action, $keys = array( '_ajax_nonce', 'security', 'nonce' ) ) {
		foreach ( (array) $keys as $k ) {
			if ( isset( $_REQUEST[ $k ] ) ) {
				$nonce = $_REQUEST[ $k ];
				return (bool) wp_verify_nonce( $nonce, $action );
			}
		}
		return false;
	}

	private static function verify_nonce_multi( $pairs ) {
		foreach ( (array) $pairs as $p ) {
			$action = isset( $p['action'] ) ? $p['action'] : '';
			$keys   = isset( $p['keys'] ) ? (array) $p['keys'] : array();
			if ( $action && $keys && self::verify_nonce( $action, $keys ) ) {
				return true;
			}
		}
		return false;
	}

	private static function json_error( $message, $code = 400 ) {
		wp_send_json_error( array( 'message' => $message ), $code );
	}
	private static function json_success( $data ) {
		wp_send_json_success( $data );
	}

        /* ===== End of class ===== */
}

Politeia_Reading_User_Books::init();
