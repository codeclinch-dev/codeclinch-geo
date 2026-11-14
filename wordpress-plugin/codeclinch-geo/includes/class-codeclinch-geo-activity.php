<?php
/**
 * Bounded, aggregated request activity.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_Activity {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'interdeft_geo_activity';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $collation = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            entry_hash char(64) NOT NULL,
            event_type varchar(20) NOT NULL,
            path varchar(1024) NOT NULL,
            crawler varchar(50) NOT NULL DEFAULT '',
            verified tinyint(1) NOT NULL DEFAULT 0,
            hits bigint unsigned NOT NULL DEFAULT 1,
            last_seen datetime NOT NULL,
            PRIMARY KEY  (entry_hash),
            KEY event_time (event_type,last_seen),
            KEY last_seen (last_seen)
        ) {$collation};");

        if ($table !== $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)))) {
            return false;
        }

        // Old records used full query strings and unverified agent names.
        delete_option('codeclinch_geo_404_logs');
        delete_option('codeclinch_geo_ai_crawler_logs');
        return true;
    }

    public static function register() {
        add_action('interdeft_geo_prune_activity', array(__CLASS__, 'prune'));
        if (!wp_next_scheduled('interdeft_geo_prune_activity')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'interdeft_geo_prune_activity');
        }
    }

    public static function request_path($request_uri) {
        $path = is_string($request_uri) ? wp_parse_url($request_uri, PHP_URL_PATH) : '';
        if (!is_string($path) || '/' !== substr($path, 0, 1)) {
            return '/';
        }
        return wp_check_invalid_utf8(preg_replace('/[\x00-\x1f\x7f]/', '', $path));
    }

    public static function log($event_type, $crawler = '', $verified = false) {
        if (CODECLINCH_GEO_VERSION !== get_option('codeclinch_geo_version')) {
            return;
        }
        global $wpdb;
        $table = self::table();
        $path = self::request_path(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        $path = wp_check_invalid_utf8(substr($path, 0, 1024)) ?: '/';
        $hash = hash('sha256', $event_type . '|' . $crawler . '|' . (int) $verified . '|' . $path);
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (entry_hash,event_type,path,crawler,verified,last_seen)
             VALUES (%s,%s,%s,%s,%d,%s)
             ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = GREATEST(last_seen, %s)",
            $hash, $event_type, $path, $crawler, $verified, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')
        ));
    }

    public static function get($event_type, $limit) {
        if (CODECLINCH_GEO_VERSION !== get_option('codeclinch_geo_version')) {
            return array();
        }
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT path,crawler,verified,hits,last_seen FROM {$table}
             WHERE event_type = %s AND last_seen >= %s ORDER BY last_seen DESC LIMIT %d",
            $event_type, gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS), max(1, min(200, (int) $limit))
        ), ARRAY_A);

        $rows = is_array($rows) ? $rows : array();
        foreach ($rows as &$row) {
            $row['url'] = $row['path'];
            $row['last_seen'] = get_date_from_gmt($row['last_seen']);
        }
        return $rows;
    }

    public static function prune() {
        global $wpdb;
        if (CODECLINCH_GEO_VERSION === get_option('codeclinch_geo_version')) {
            $table = self::table();
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE last_seen < %s",
                gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
            ));
        }
        $prefix = 'interdeft_geo_ai_usage_';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s",
            $wpdb->esc_like($prefix) . '%', $prefix . gmdate('Ym')
        ));

        $options = (new CodeClinch_GEO_Generator())->get_options();
        if (!empty($options['ai_crawler_logs_enabled'])) {
            CodeClinch_GEO_AI_Visibility::refresh_verification_ranges();
        }
    }
}
