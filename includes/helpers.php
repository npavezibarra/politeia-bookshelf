<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('admin_init', function () {
    if ( ! current_user_can('manage_options') ) {
        return;
    }

    if ( ! isset($_GET['prs_phase6_test']) ) {
        return;
    }

    echo '<pre>';

    echo "=== Phase 6: Canonical Integrity (Before Backfill) ===\n";
    $integrity_before = prs_diagnose_canonical_integrity();
    print_r($integrity_before['counts']);

    echo "\n=== Phase 6.2: Running Author Pivot Backfill ===\n";
    $backfill = prs_backfill_author_pivots_from_legacy();
    print_r($backfill);

    echo "\n=== Phase 6: Canonical Integrity (After Backfill) ===\n";
    $integrity_after = prs_diagnose_canonical_integrity();
    print_r($integrity_after['counts']);

    echo "\n=== Phase 6.3: Identity Collision Check ===\n";
    $collisions = prs_diagnose_canonical_identity_collisions();
    print_r($collisions);

    echo "\n=== Phase 6.5: Can Remove title_author_hash? ===\n";
    $can_remove = prs_can_remove_title_author_hash();
    var_dump($can_remove);

    echo '</pre>';
    exit;
});
