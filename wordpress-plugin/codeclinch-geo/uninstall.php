<?php
/**
 * Remove plugin options.
 *
 * @package CodeClinch_GEO
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('codeclinch_geo_options');
delete_option('codeclinch_geo_404_logs');
delete_option('codeclinch_geo_ai_crawler_logs');
delete_option('codeclinch_geo_version');
delete_option('interdeft_geo_crawler_ranges');
wp_clear_scheduled_hook('interdeft_geo_prune_activity');

global $wpdb;
$table = $wpdb->prefix . 'interdeft_geo_activity';
$wpdb->query("DROP TABLE IF EXISTS {$table}");
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like('interdeft_geo_ai_usage_') . '%'
));
