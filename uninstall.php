<?php

defined('WP_UNINSTALL_PLUGIN') || die;

function glsr_uninstall() {
    $settings = get_option('site-reviews-v5');
    $uninstall = isset($settings['general']['delete_data_on_uninstall'])
        ? $settings['general']['delete_data_on_uninstall']
        : '';
    if ('all' === $uninstall) {
        glsr_uninstall_all();
    }
    if ('minimal' === $uninstall) {
        glsr_uninstall_minimal();
    }
}

function glsr_uninstall_all() {
    glsr_uninstall_minimal();
    glsr_uninstall_all_delete_reviews();
    glsr_uninstall_all_delete_tables();
    glsr_uninstall_all_delete_logs();
    glsr_uninstall_all_cleanup();
}

function glsr_uninstall_all_cleanup() {
    global $wpdb;
    // delete any remaining options
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%glsr_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'site-review-category%'");
    // optimise affected database tables
    $wpdb->query("OPTIMIZE TABLE {$wpdb->options}");
    $wpdb->query("OPTIMIZE TABLE {$wpdb->postmeta}");
    $wpdb->query("OPTIMIZE TABLE {$wpdb->posts}");
    $wpdb->query("OPTIMIZE TABLE {$wpdb->termmeta}");
    $wpdb->query("OPTIMIZE TABLE {$wpdb->terms}");
    $wpdb->query("OPTIMIZE TABLE {$wpdb->usermeta}");
    // finally, flush the entire cache
    wp_cache_flush();
}

function glsr_uninstall_all_delete_logs() {
    require_once ABSPATH.'/wp-admin/includes/file.php';
    global $wp_filesystem;
    // delete the Site Reviews logs directory
    if (WP_Filesystem()) {
        $uploads = wp_upload_dir();
        $dirname = trailingslashit($uploads['basedir'].'/site-reviews/logs');
        $wp_filesystem->rmdir(wp_normalize_path($dirname), true);
    }
}

function glsr_uninstall_all_delete_reviews() {
    global $wpdb;
    // delete all reviews and revisions
    $wpdb->query("
        DELETE p, pr, tr, pm
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->posts} pr ON (p.ID = pr.post_parent AND pr.post_type = 'revision')
        LEFT JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
        LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id)
        WHERE p.post_type = 'site-review'
    ");
    // delete all review categories
    $wpdb->query("
        DELETE tt, t, tm
        FROM {$wpdb->term_taxonomy} tt
        LEFT JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
        LEFT JOIN {$wpdb->termmeta} tm ON (tt.term_id = tm.term_id)
        WHERE tt.taxonomy = 'site-review-category'
    ");
    // delete all assigned_posts meta
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '%glsr_%'");
    // delete all assigned_users meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '%glsr_%'");
}

function glsr_uninstall_all_delete_tables() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}glsr_assigned_posts");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}glsr_assigned_terms");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}glsr_assigned_users");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}glsr_ratings");
    // delete the saved database version
    delete_option('glsr_db_version');
}

function glsr_uninstall_minimal() {
    global $wpdb;
    $options = array(
        'geminilabs_site_reviews-v2', // v2 settings
        'geminilabs_site_reviews_settings', // v1 settings
        'glsr_activated',
        'site-reviews-v3', // v3 settings
        'site-reviews-v4', // v4 settings
        'site-reviews-v5', // v5 settings
        'theme_mods_site-reviews',
        'widget_glsr_site-reviews',
        'widget_glsr_site-reviews-form',
        'widget_glsr_site-reviews-summary',
    );
    foreach ($options as $option) {
        delete_option($option);
    }
    delete_transient('glsr_cloudflare_ips');
    delete_transient('glsr_migrations');
    delete_transient('glsr_remote_post_test');
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_glsr_notices'");
}

if (!is_multisite()) {
    glsr_uninstall();
    return;
}
if (!function_exists('get_sites')) {
    global $wpdb;
    $siteIds = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
} else {
    $siteIds = get_sites(array('fields' => 'ids'));
}
foreach ($siteIds as $siteId) {
    switch_to_blog($siteId);
    glsr_uninstall();
    restore_current_blog();
}
