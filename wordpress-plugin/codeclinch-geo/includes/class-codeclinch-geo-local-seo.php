<?php
/**
 * Local SEO helpers.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_Local_SEO {
    private $generator;

    public function __construct(CodeClinch_GEO_Generator $generator) {
        $this->generator = $generator;

        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'render_location_sitemap'), 2);
    }

    public function add_query_vars($vars) {
        $vars[] = 'codeclinch_geo_local_endpoint';
        return $vars;
    }

    public function render_location_sitemap() {
        if ('location_sitemap' !== get_query_var('codeclinch_geo_local_endpoint')) {
            return;
        }

        $options = $this->generator->get_options();
        if (!get_option('blog_public') || empty($options['local_seo_enabled']) || !$this->get_sitemap_locations()) {
            status_header(404);
            exit;
        }

        status_header(200);
        header('Content-Type: application/xml; charset=utf-8');
        echo $this->generate_location_sitemap(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function generate_location_sitemap() {
        $locations = $this->get_sitemap_locations();
        $lines = array('<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

        foreach ($locations as $location) {
            if (empty($location['url'])) {
                continue;
            }
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . esc_xml($location['url']) . '</loc>';
            $lines[] = '    <lastmod>' . esc_xml($location['modified']) . '</lastmod>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';
        return implode("\n", $lines) . "\n";
    }

    public function get_sitemap_locations() {
        $items = array();
        foreach ($this->get_locations() as $location) {
            $id = url_to_postid($location['url']);
            if (!$id || !$this->generator->is_discoverable($id)) {
                continue;
            }
            $url = get_permalink($id);
            if ($url !== $location['url']) {
                continue;
            }
            $items[$id] = array('url' => $url, 'modified' => get_post_modified_time(DATE_W3C, true, $id));
        }
        return $items;
    }

    public function get_locations() {
        $options = $this->generator->get_options();
        $locations = array();

        foreach ($this->generator->parse_lines($options['local_locations']) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $locations[] = array(
                'city' => $parts[0] ?? '',
                'region' => $parts[1] ?? '',
                'url' => isset($parts[2]) ? esc_url_raw($parts[2]) : '',
            );
        }

        return array_values(array_filter($locations, static function ($location) {
            return !empty($location['city']);
        }));
    }

    public function get_city_page_ideas() {
        $options = $this->generator->get_options();
        $services = $this->generator->parse_services($options['services']);
        $locations = $this->get_locations();
        $ideas = array();

        foreach ($locations as $location) {
            foreach (array_slice($services, 0, 8) as $service) {
                $ideas[] = array(
                    'title' => strtr($options['local_city_page_template'], array(
                        '%service%' => $service['name'],
                        '%city%' => $location['city'],
                        '%sitename%' => get_bloginfo('name'),
                    )),
                    'slug' => sanitize_title($service['name'] . ' in ' . $location['city']),
                    'summary' => sprintf(
                        /* translators: 1: service name, 2: city name */
                        __('Create a landing page for %1$s customers in %2$s with local proof, FAQs, service area details, and a clear contact CTA.', 'codeclinch-geo'),
                        $service['name'],
                        $location['city']
                    ),
                );
            }
        }

        return $ideas;
    }

    public function calculate_local_score() {
        $options = $this->generator->get_options();
        $checks = array(
            'Local SEO enabled' => !empty($options['local_seo_enabled']),
            'Business name added' => !empty($options['local_business_name']) || !empty($options['schema_business_name']),
            'Phone added' => !empty($options['local_phone']) || !empty($options['schema_business_phone']),
            'Address added' => !empty($options['local_address']) || !empty($options['schema_business_address']),
            'Opening hours added' => !empty($options['local_opening_hours']) || !empty($options['schema_opening_hours']),
            'Service areas added' => !empty($options['local_service_areas']) || !empty($options['schema_service_area']),
            'Map URL added' => !empty($options['local_map_url']),
            'Locations added' => !empty($this->get_locations()),
        );

        $passed = count(array_filter($checks));

        return array(
            'percentage' => (int) round(($passed / count($checks)) * 100),
            'checks' => $checks,
        );
    }
}
