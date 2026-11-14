<?php
/**
 * Plugin Name: Interdeft SEO & AI Search
 * Plugin URI: https://interdeft.com
 * Description: AI search visibility, llms.txt, schema, agent profile, and GEO audit tools for WordPress.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Interdeft
 * Author URI: https://interdeft.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: codeclinch-geo
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CODECLINCH_GEO_VERSION', '0.2.0');
define('CODECLINCH_GEO_FILE', __FILE__);
define('CODECLINCH_GEO_PATH', plugin_dir_path(__FILE__));
define('CODECLINCH_GEO_URL', plugin_dir_url(__FILE__));

require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-activity.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-ai-provider.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-generator.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-seo.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-core-seo.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-schema.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-ai-visibility.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-woocommerce.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-local-seo.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-reports.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-post-meta.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-admin.php';
require_once CODECLINCH_GEO_PATH . 'includes/class-codeclinch-geo-plugin.php';

register_activation_hook(__FILE__, array('CodeClinch_GEO_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('CodeClinch_GEO_Plugin', 'deactivate'));

add_action(
    'plugins_loaded',
    static function () {
        CodeClinch_GEO_Plugin::instance();
    }
);
