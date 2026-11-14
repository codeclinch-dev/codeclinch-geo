<?php
/**
 * Main plugin runtime.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_Plugin {
    private static $instance = null;

    private $generator;

    private $ai_provider;

    private $seo;

    private $core_seo;

    private $schema;

    private $ai_visibility;

    private $woocommerce;

    private $local_seo;

    private $reports;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        self::upgrade();
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('interdeft_geo_prune_activity');
        delete_option('rewrite_rules');
    }

    private function __construct() {
        self::upgrade();

        CodeClinch_GEO_Activity::register();
        $this->generator = new CodeClinch_GEO_Generator();
        $this->ai_provider = new CodeClinch_GEO_AI_Provider();
        $this->seo = new CodeClinch_GEO_SEO($this->generator);
        $this->core_seo = new CodeClinch_GEO_Core_SEO($this->generator);
        $this->schema = new CodeClinch_GEO_Schema($this->generator);
        $this->ai_visibility = new CodeClinch_GEO_AI_Visibility($this->generator);
        $this->woocommerce = new CodeClinch_GEO_WooCommerce($this->generator);
        $this->local_seo = new CodeClinch_GEO_Local_SEO($this->generator);
        $this->reports = new CodeClinch_GEO_Reports($this->generator, $this->ai_visibility, $this->woocommerce, $this->local_seo);

        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'render_endpoint'), 2);
        add_action('template_redirect', array($this->core_seo, 'render_endpoint'), 2);
        add_filter('robots_txt', array($this, 'append_robots_txt'), 10, 2);
        add_action('wp_footer', array($this, 'output_branding'), 50);

        if (is_admin()) {
            new CodeClinch_GEO_Post_Meta();
            new CodeClinch_GEO_Admin($this->generator, $this->ai_provider, $this->core_seo, $this->ai_visibility, $this->woocommerce, $this->local_seo, $this->reports);
        }
    }

    private static function upgrade() {
        if (CODECLINCH_GEO_VERSION === get_option('codeclinch_geo_version')) {
            return;
        }
        $generator = new CodeClinch_GEO_Generator();
        $saved = get_option('codeclinch_geo_options');
        if (false === $saved) {
            add_option('codeclinch_geo_options', $generator->get_defaults(), '', false);
        } elseif (is_array($saved)) {
            $stock = array(
                'branding_label' => 'SEO & GEO powered by CodeClinch',
                'branding_url' => 'https://codeclinch.com',
                'contact_url' => 'https://codeclinch.com/contact',
                'contact_cta_label' => 'Contact CodeClinch',
                'service_cta_url' => 'https://codeclinch.com/contact?service=custom-geo',
            );
            if (($saved['branding_label'] ?? '') === $stock['branding_label']
                && ($saved['branding_url'] ?? '') === $stock['branding_url']) {
                $saved['branding_enabled'] = 0;
            }
            $defaults = $generator->get_defaults();
            foreach ($stock as $key => $legacy) {
                if (($saved[$key] ?? '') === $legacy) {
                    $saved[$key] = $defaults[$key];
                }
            }
            update_option('codeclinch_geo_options', $saved, false);
        }
        if (CodeClinch_GEO_Activity::install()) {
            update_option('codeclinch_geo_version', CODECLINCH_GEO_VERSION, false);
        }
        delete_option('rewrite_rules');
    }

    public static function add_rewrite_rules() {
        add_rewrite_rule('^llms\.txt$', 'index.php?codeclinch_geo_endpoint=llms', 'top');
        add_rewrite_rule('^ai-visibility\.json$', 'index.php?codeclinch_geo_endpoint=ai_visibility', 'top');
        add_rewrite_rule('^\.well-known/agent\.json$', 'index.php?codeclinch_geo_endpoint=agent', 'top');
        add_rewrite_rule('^(?:codeclinch|interdeft)-sitemap\.xml$', 'index.php?codeclinch_geo_core_endpoint=xml_sitemap', 'top');
        add_rewrite_rule('^(?:codeclinch|interdeft)-html-sitemap/?$', 'index.php?codeclinch_geo_core_endpoint=html_sitemap', 'top');
        add_rewrite_rule('^(?:codeclinch|interdeft)-location-sitemap\.xml$', 'index.php?codeclinch_geo_local_endpoint=location_sitemap', 'top');
    }

    public function add_query_vars($vars) {
        $vars[] = 'codeclinch_geo_endpoint';
        $vars[] = 'codeclinch_geo_core_endpoint';
        return $vars;
    }

    public function render_endpoint() {
        $endpoint = get_query_var('codeclinch_geo_endpoint');

        if (!$endpoint) {
            return;
        }

        $options = $this->generator->get_options();
        if (!get_option('blog_public') || empty($options['enabled'])) {
            status_header(404);
            exit;
        }

        if (!in_array($endpoint, array('llms', 'ai_visibility', 'agent'), true)) {
            return;
        }

        status_header(200);
        nocache_headers();
        if ('llms' === $endpoint) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $this->generator->generate_llms_txt(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        if ('ai_visibility' === $endpoint) {
            wp_send_json($this->generator->generate_ai_visibility_json());
        }

        if ('agent' === $endpoint) {
            wp_send_json($this->generator->generate_agent_json());
        }
    }

    public function append_robots_txt($output, $public) {
        if (!$public) {
            return $output;
        }

        $options = $this->generator->get_options();
        $lines = array();
        if (!empty($options['local_seo_enabled']) && $this->local_seo->get_sitemap_locations()) {
            $lines[] = 'Sitemap: ' . home_url('/interdeft-location-sitemap.xml');
        }
        if (!empty($options['robots_editor_content'])) {
            $lines[] = trim($options['robots_editor_content']);
        }
        return CodeClinch_GEO_AI_Visibility::append_crawler_policy(rtrim($output) . "\n" . implode("\n", $lines), $options['crawler_policies']);
    }

    public function output_branding() {
        $options = $this->generator->get_options();

        if (empty($options['enabled']) || empty($options['branding_enabled']) || !empty($options['white_label_enabled'])) {
            return;
        }

        echo $this->generator->generate_branding_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
