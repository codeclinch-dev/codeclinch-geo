<?php
/**
 * GEO output generation.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_Generator {
    public function get_options() {
        return wp_parse_args(get_option('codeclinch_geo_options', array()), $this->get_defaults());
    }

    public function get_defaults() {
        return array(
            'enabled' => 0,
            'site_summary' => get_bloginfo('description'),
            'citation_guidance' => 'Use canonical URLs from this website and prefer service, product, case study, and article pages.',
            'services' => '',
            'organization_name' => get_bloginfo('name'),
            'organization_logo' => '',
            'organization_same_as' => '',
            'schema_enabled' => 1,
            'schema_default_type' => 'auto',
            'schema_business_type' => 'Organization',
            'schema_business_name' => get_bloginfo('name'),
            'schema_business_phone' => '',
            'schema_business_address' => '',
            'schema_service_area' => '',
            'schema_opening_hours' => '',
            'schema_price_range' => '',
            'schema_default_author' => '',
            'branding_enabled' => 0,
            'branding_label' => 'SEO & GEO powered by Interdeft',
            'branding_url' => 'https://interdeft.com',
            'admin_branding_enabled' => 1,
            'admin_footer_branding_enabled' => 1,
            'white_label_enabled' => 0,
            'white_label_plugin_name' => 'SEO / GEO Suite',
            'white_label_logo_url' => '',
            'support_email' => '',
            'contact_url' => 'https://interdeft.com/contact',
            'contact_cta_label' => 'Contact Interdeft',
            'service_cta_url' => 'https://interdeft.com/contact?service=custom-geo',
            'service_cta_label' => 'Request Custom Help',
            'services_box_title' => 'Services',
            'services_box_items' => "WordPress SEO setup\nGEO / AI search optimization\nTechnical SEO audit\nSchema and WooCommerce SEO\nCustom website development",
            'include_posts' => 1,
            'include_pages' => 1,
            'ai_enabled' => 0,
            'ai_consent' => 0,
            'crawler_policies' => array(),
            'ai_provider' => 'openai',
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o-mini',
            'openrouter_api_key' => '',
            'openrouter_model' => 'openai/gpt-4o-mini',
            'ai_usage_limit' => 100,
            'seo_enabled' => 1,
            'seo_social_enabled' => 1,
            'seo_title_template' => '%title% | %sitename%',
            'seo_home_title' => '%sitename% | %tagline%',
            'seo_home_description' => get_bloginfo('description'),
            'seo_default_social_image' => '',
            'seo_robots_noarchive' => 0,
            'seo_robots_nosnippet' => 0,
            'breadcrumbs_enabled' => 1,
            'core_sitemap_enabled' => 1,
            'sitemap_include_posts' => 1,
            'sitemap_include_pages' => 1,
            'robots_editor_content' => '',
            'htaccess_editor_content' => '',
            'monitor_404_enabled' => 0,
            'redirects_enabled' => 1,
            'redirects_rules' => '',
            'image_auto_alt_enabled' => 0,
            'image_alt_template' => '%title% - %sitename%',
            'rss_footer_enabled' => 0,
            'rss_footer_content' => '',
            'ai_crawler_logs_enabled' => 0,
            'geo_proof_points' => '',
            'geo_business_facts' => '',
            'geo_pricing_summary' => '',
            'geo_location_summary' => '',
            'geo_important_pages' => '',
            'woocommerce_seo_enabled' => 1,
            'woocommerce_sync_product_schema' => 1,
            'woocommerce_ai_summary_template' => '%product% from %sitename%. Price: %price%. SKU: %sku%.',
            'woocommerce_category_seo_enabled' => 1,
            'woocommerce_brand_schema_enabled' => 0,
            'local_seo_enabled' => 1,
            'local_business_name' => get_bloginfo('name'),
            'local_phone' => '',
            'local_address' => '',
            'local_service_areas' => '',
            'local_opening_hours' => '',
            'local_map_url' => '',
            'local_locations' => '',
            'local_city_page_template' => '%service% in %city% | %sitename%',
        );

    }

    public function generate_llms_txt() {
        $options = $this->get_options();
        $lines = array(
            '# ' . get_bloginfo('name'),
            '',
            '> ' . preg_replace('/\R/u', "\n> ", $options['site_summary'] ?: get_bloginfo('description')),
            '',
        );
        $sitemap = CodeClinch_GEO_Core_SEO::get_active_sitemap_url();
        if ($sitemap) {
            $lines[] = '## Sitemap';
            $lines[] = $this->markdown_link('XML sitemap', $sitemap);
            $lines[] = '';
        }
        $lines = array_merge($lines, array('## Exports',
            $this->markdown_link('AI visibility data', home_url('/ai-visibility.json')),
            $this->markdown_link('Site profile', home_url('/.well-known/agent.json')),
            '',
        ));

        $content = $this->get_important_content();
        if (!empty($content)) {
            $lines[] = '## Public pages';
            foreach ($content as $item) {
                $description = preg_replace('/\s+/', ' ', $item['aiSummary']);
                $lines[] = $this->markdown_link($item['title'], $item['url']) . ($description ? ': ' . $description : '');
            }
        }

        return trim(implode("\n", $lines)) . "\n";
    }

    public function generate_ai_visibility_json() {
        $options = $this->get_options();

        return array(
            'schemaVersion' => 'codeclinch-geo/wp-v1',
            'site' => array(
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'description' => $options['site_summary'] ? $options['site_summary'] : get_bloginfo('description'),
                'language' => get_bloginfo('language'),
            ),
            'organization' => array(
                'name' => $options['organization_name'],
                'logo' => $options['organization_logo'],
                'sameAs' => $this->parse_lines($options['organization_same_as']),
            ),
            'discovery' => array(
                'llmsTxt' => home_url('/llms.txt'),
                'aiVisibility' => home_url('/ai-visibility.json'),
                'agent' => home_url('/.well-known/agent.json'),
                'sitemap' => CodeClinch_GEO_Core_SEO::get_active_sitemap_url(),
                'robots' => home_url('/robots.txt'),
            ),
            'services' => $this->parse_services($options['services']),
            'geoBlocks' => array(
                'proofPoints' => $this->parse_lines($options['geo_proof_points']),
                'businessFacts' => $this->parse_lines($options['geo_business_facts']),
                'pricingSummary' => $options['geo_pricing_summary'],
                'locationSummary' => $options['geo_location_summary'],
                'importantPages' => $this->parse_lines($options['geo_important_pages']),
            ),
            'content' => $this->get_important_content(),
            'citationGuidance' => $options['citation_guidance'],
        );
    }

    public function generate_agent_json() {
        $options = $this->get_options();

        return array(
            'schemaVersion' => 'agent-profile/v1',
            'name' => get_bloginfo('name'),
            'description' => $options['site_summary'] ? $options['site_summary'] : get_bloginfo('description'),
            'url' => home_url('/'),
            'preferredCitationUrl' => home_url('/'),
            'language' => get_bloginfo('language'),
            'contentEndpoints' => array(
                'llms' => home_url('/llms.txt'),
                'aiVisibility' => home_url('/ai-visibility.json'),
                'sitemap' => CodeClinch_GEO_Core_SEO::get_active_sitemap_url(),
                'robots' => home_url('/robots.txt'),
            ),
            'services' => $this->parse_services($options['services']),
            'proofPoints' => $this->parse_lines($options['geo_proof_points']),
            'businessFacts' => $this->parse_lines($options['geo_business_facts']),
        );
    }

    public function generate_organization_schema() {
        $options = $this->get_options();

        return array_filter(
            array(
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $options['organization_name'],
                'url' => home_url('/'),
                'logo' => $options['organization_logo'],
                'sameAs' => $this->parse_lines($options['organization_same_as']),
            )
        );
    }

    public function generate_branding_html() {
        $options = $this->get_options();

        if (empty($options['branding_enabled'])) {
            return '';
        }

        $label = $options['branding_label'] ? $options['branding_label'] : 'SEO & GEO powered by Interdeft';
        $url = $options['branding_url'] ? $options['branding_url'] : 'https://interdeft.com';

        return sprintf(
            '<div class="codeclinch-geo-branding"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></div>',
            esc_url($url),
            esc_html($label)
        );
    }

    public function calculate_score() {
        $options = $this->get_options();
        $checks = array(
            'Plugin enabled' => !empty($options['enabled']),
            'Site summary added' => strlen(trim($options['site_summary'])) >= 50,
            'Citation guidance added' => strlen(trim($options['citation_guidance'])) >= 30,
            'Services configured' => !empty($this->parse_services($options['services'])),
            'Organization name configured' => !empty($options['organization_name']),
            'Important content discoverable' => !empty($this->get_important_content()),
            'Branding configured' => empty($options['branding_enabled']) || (!empty($options['branding_label']) && !empty($options['branding_url'])),
            'AI provider configured' => empty($options['ai_enabled']) || (!empty($options['openai_api_key']) || !empty($options['openrouter_api_key'])),
            'SEO output enabled' => !empty($options['seo_enabled']),
            'SEO title template configured' => !empty($options['seo_title_template']),
            'Homepage meta description configured' => strlen(trim($options['seo_home_description'])) >= 50,
            'Sitemap enabled' => !empty($options['core_sitemap_enabled']),
            '404 monitor enabled' => !empty($options['monitor_404_enabled']),
            'AI crawler logging enabled' => !empty($options['ai_crawler_logs_enabled']),
            'Proof points configured' => !empty($this->parse_lines($options['geo_proof_points'])),
        );

        $passed = count(array_filter($checks));
        $total = count($checks);

        return array(
            'percentage' => (int) round(($passed / $total) * 100),
            'passed' => $passed,
            'total' => $total,
            'checks' => $checks,
        );
    }

    public function parse_services($raw) {
        $services = array();
        $lines = $this->parse_lines($raw);

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            $name = $parts[0];
            $description = isset($parts[1]) ? $parts[1] : $parts[0];

            if (!$name) {
                continue;
            }

            $services[] = array(
                'name' => $name,
                'description' => $description,
            );
        }

        return $services;
    }

    public function parse_lines($raw) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
        $lines = array_map('trim', $lines);

        return array_values(array_filter($lines));
    }

    public function get_important_content() {
        $options = $this->get_options();
        $types = array();
        foreach (array('post' => 'include_posts', 'page' => 'include_pages') as $type => $setting) {
            if (!empty($options[$setting])) {
                $types[] = $type;
            }
        }

        if (empty($types) || !get_option('blog_public')) {
            return array();
        }

        $items = array();
        $page = 1;
        do {
            $query = new WP_Query(array(
                'post_type' => $types,
                'post_status' => 'publish',
                'has_password' => false,
                'posts_per_page' => 50,
                'paged' => $page++,
                'orderby' => array('modified' => 'DESC', 'ID' => 'DESC'),
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
                'meta_key' => '_codeclinch_geo_ai_visible',
                'meta_value' => '1',
            ));
            foreach ($query->posts as $post) {
                if (!$this->is_discoverable($post)) {
                    continue;
                }
                $items[] = array(
                    'title' => get_the_title($post),
                    'url' => get_permalink($post),
                    'type' => get_post_type($post),
                    'modified' => get_post_modified_time(DATE_ATOM, true, $post),
                    'aiVisible' => true,
                    'aiSummary' => get_post_meta($post->ID, '_codeclinch_geo_ai_summary', true),
                    'citationAnswer' => get_post_meta($post->ID, '_codeclinch_geo_citation_answer', true),
                );
                if (count($items) >= 20) {
                    break 2;
                }
            }
        } while (count($query->posts) === 50);

        return $items;
    }

    public function is_discoverable($post) {
        $post = get_post($post);
        if (!$post || !get_option('blog_public') || 'publish' !== $post->post_status
            || '' !== $post->post_password || !is_post_type_viewable($post->post_type)
            || 'noindex' === get_post_meta($post->ID, '_codeclinch_geo_robots_index', true)) {
            return false;
        }

        $url = get_permalink($post);
        $canonical = get_post_meta($post->ID, '_codeclinch_geo_canonical', true);
        if (!$url || ($canonical && $canonical !== $url)) {
            return false;
        }

        $owner = CodeClinch_GEO_SEO::seo_owner();
        if ('yoast' === $owner) {
            $meta = function_exists('YoastSEO') ? YoastSEO()->meta->for_post($post->ID) : false;
            if (!$meta || in_array('noindex', (array) $meta->robots, true) || $meta->canonical !== $url) {
                return false;
            }
        } elseif ('rank-math' === $owner) {
            if (!class_exists('RankMath\Helper')) {
                return false;
            }
            $robots = get_post_meta($post->ID, 'rank_math_robots', true);
            if (empty($robots)) {
                $robots = RankMath\Helper::get_settings('titles.pt_' . $post->post_type . '_robots');
                if (!RankMath\Helper::get_settings('titles.pt_' . $post->post_type . '_custom_robots')) {
                    $robots = RankMath\Helper::get_settings('titles.robots_global');
                }
            }
            $canonical = get_post_meta($post->ID, 'rank_math_canonical_url', true);
            if (in_array('noindex', (array) $robots, true) || ($canonical && $canonical !== $url)) {
                return false;
            }
        } elseif ($owner) {
            return (bool) apply_filters('interdeft_geo_external_post_discoverable', false, $post, $owner);
        }

        return (bool) apply_filters('interdeft_geo_post_discoverable', true, $post);
    }

    private function markdown_link($label, $url) {
        $label = strtr(preg_replace('/\s+/', ' ', wp_strip_all_tags($label)), array(
            '\\' => '\\\\',
            '[' => '\\[',
            ']' => '\\]',
        ));
        return '- [' . $label . '](<' . str_replace(array('<', '>', ' '), array('%3C', '%3E', '%20'), $url) . '>)';
    }

}
