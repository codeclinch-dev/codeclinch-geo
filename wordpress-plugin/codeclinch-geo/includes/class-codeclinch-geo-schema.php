<?php
/**
 * Schema.org JSON-LD generation.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_Schema {
    private $generator;

    public function __construct(CodeClinch_GEO_Generator $generator) {
        $this->generator = $generator;

        add_action('wp_head', array($this, 'output_schema'), 20);
    }

    public function output_schema() {
        if (is_admin() || !get_option('blog_public') || CodeClinch_GEO_SEO::seo_owner()) {
            return;
        }

        $options = $this->generator->get_options();
        if (empty($options['schema_enabled'])) {
            return;
        }

        $post_id = is_singular() ? get_queried_object_id() : 0;
        if ($post_id && (!$this->generator->is_discoverable($post_id)
            || get_post_meta($post_id, '_codeclinch_geo_schema_disabled', true)
            || ('product' === get_post_type($post_id) && function_exists('wc_get_product')))) {
            return;
        }

        $graph = array();

        if (is_front_page()) {
            $graph[] = $this->organization_schema($options);
            $graph[] = $this->website_schema();

            if ('LocalBusiness' === $options['schema_business_type']) {
                $graph[] = $this->local_business_schema($options);
            }
        }

        if ($post_id && !is_front_page() && !empty($options['breadcrumbs_enabled'])) {
            $graph[] = $this->breadcrumb_schema();
        }

        if ($post_id) {
            $schema = $this->singular_schema($post_id, $options);
            if (!empty($schema)) {
                $graph[] = $schema;
            }
        }

        $graph = array_values(array_filter($graph));
        if (empty($graph)) {
            return;
        }

        $payload = count($graph) > 1
            ? array('@context' => 'https://schema.org', '@graph' => $graph)
            : array_merge(array('@context' => 'https://schema.org'), $graph[0]);
        $json = wp_json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (false !== $json) {
            printf('<script type="application/ld+json">%s</script>' . "\n", $json); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded for a script element.
        }
    }

    public function organization_schema($options) {
        return array_filter(
            array(
                '@type' => 'Organization',
                '@id' => home_url('/#organization'),
                'name' => $options['organization_name'] ?: get_bloginfo('name'),
                'url' => home_url('/'),
                'logo' => $options['organization_logo'],
                'sameAs' => $this->generator->parse_lines($options['organization_same_as']),
            )
        );
    }

    private function website_schema() {
        return array_filter(
            array(
                '@type' => 'WebSite',
                '@id' => home_url('/#website'),
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'description' => get_bloginfo('description'),
                'publisher' => array('@id' => home_url('/#organization')),
            )
        );
    }

    private function local_business_schema($options) {
        $address = $options['schema_business_address'] ?: $options['local_address'];
        if (!$address) {
            return array();
        }
        return array_filter(
            array(
                '@type' => 'LocalBusiness',
                '@id' => home_url('/#local-business'),
                'name' => $options['schema_business_name'] ? $options['schema_business_name'] : $options['organization_name'],
                'url' => home_url('/'),
                'image' => $options['organization_logo'],
                'telephone' => $options['schema_business_phone'] ?: $options['local_phone'],
                'priceRange' => $options['schema_price_range'],
                'address' => array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => $address,
                ),
                'areaServed' => $this->generator->parse_lines($options['schema_service_area'] ?: $options['local_service_areas']),
                'openingHours' => $this->generator->parse_lines($options['schema_opening_hours'] ?: $options['local_opening_hours']),
            )
        );
    }

    private function singular_schema($post_id, $options) {
        $type = get_post_meta($post_id, '_codeclinch_geo_schema_type', true);
        $type = $type ?: $options['schema_default_type'];
        if ('auto' === $type || !in_array($type, array('Article', 'BlogPosting', 'WebPage', 'Product', 'Service', 'FAQPage', 'Review', 'AggregateRating', 'VideoObject', 'HowTo', 'Event', 'JobPosting', 'Course'), true)) {
            $type = 'post' === get_post_type($post_id) ? 'BlogPosting' : 'WebPage';
        }
        $description = get_post_meta($post_id, '_codeclinch_geo_meta_description', true);
        $description = $description ? $description : wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post_id)), 30, '');

        $base = array_filter(
            array(
                '@type' => $type,
                '@id' => get_permalink($post_id) . '#schema',
                'headline' => get_the_title($post_id),
                'name' => get_the_title($post_id),
                'description' => $description,
                'url' => get_permalink($post_id),
                'datePublished' => get_the_date(DATE_W3C, $post_id),
                'dateModified' => get_post_modified_time(DATE_W3C, true, $post_id),
                'mainEntityOfPage' => get_permalink($post_id),
                'author' => array(
                    '@type' => 'Person',
                    'name' => $options['schema_default_author'] ? $options['schema_default_author'] : get_the_author_meta('display_name', (int) get_post_field('post_author', $post_id)),
                ),
                'publisher' => array('@id' => home_url('/#organization')),
            )
        );

        if (has_post_thumbnail($post_id)) {
            $base['image'] = get_the_post_thumbnail_url($post_id, 'full');
        }

        return $this->schema_by_type($type, $base, $post_id, $options);
    }

    private function schema_by_type($type, $base, $post_id, $options) {
        if ('FAQPage' === $type) {
            $faq = $this->parse_faq_schema(get_post_meta($post_id, '_codeclinch_geo_faq', true));
            $content = $this->normalize_visible_text(get_post_field('post_content', $post_id));
            foreach ($faq as $item) {
                if (false === strpos($content, $this->normalize_visible_text($item['name']))
                    || false === strpos($content, $this->normalize_visible_text($item['acceptedAnswer']['text']))) {
                    return array();
                }
            }
            if (!$faq) {
                return array();
            }
            $base['mainEntity'] = $faq;
        }

        if ('Product' === $type) {
            $base['sku'] = get_post_meta($post_id, '_codeclinch_geo_schema_sku', true);
            $price = get_post_meta($post_id, '_codeclinch_geo_schema_price', true);
            $currency = strtoupper((string) get_post_meta($post_id, '_codeclinch_geo_schema_currency', true));
            if (is_numeric($price) && (float) $price >= 0 && preg_match('/^[A-Z]{3}$/', $currency)) {
                $base['offers'] = array(
                    '@type' => 'Offer',
                    'price' => $price,
                    'priceCurrency' => $currency,
                    'url' => get_permalink($post_id),
                );
            }
        }

        if ('Service' === $type) {
            $base['provider'] = array('@id' => home_url('/#organization'));
            $base['areaServed'] = $this->generator->parse_lines(get_post_meta($post_id, '_codeclinch_geo_schema_area_served', true) ?: $options['schema_service_area']);
        }

        $rating = get_post_meta($post_id, '_codeclinch_geo_schema_rating_value', true);
        $rating = is_numeric($rating) && (float) $rating >= 1 && (float) $rating <= 5 ? $rating : '';
        $count = absint(get_post_meta($post_id, '_codeclinch_geo_schema_rating_count', true));

        if (('AggregateRating' === $type && (!$rating || !$count)) || ('Review' === $type && !$rating)) {
            return array();
        }
        if ('AggregateRating' === $type && $rating && $count) {
            return array(
                '@type' => 'AggregateRating',
                '@id' => get_permalink($post_id) . '#aggregate-rating',
                'itemReviewed' => array(
                    '@type' => 'Thing',
                    'name' => get_the_title($post_id),
                    'url' => get_permalink($post_id),
                ),
                'ratingValue' => $rating,
                'reviewCount' => $count,
            );
        }

        if ('Review' === $type && $rating) {
            $base['itemReviewed'] = array(
                '@type' => 'Thing',
                'name' => get_the_title($post_id),
                'url' => get_permalink($post_id),
            );
            $base['reviewRating'] = array(
                '@type' => 'Rating',
                'ratingValue' => $rating,
                'bestRating' => '5',
            );
        }

        if ('Product' === $type || 'Service' === $type) {
            if ($rating && $count) {
                $base['aggregateRating'] = array(
                    '@type' => 'AggregateRating',
                    'ratingValue' => $rating,
                    'reviewCount' => $count,
                );
            }
        }

        if ('VideoObject' === $type) {
            $video_url = get_post_meta($post_id, '_codeclinch_geo_schema_video_url', true);
            $base['contentUrl'] = $video_url;
            $base['thumbnailUrl'] = has_post_thumbnail($post_id) ? get_the_post_thumbnail_url($post_id, 'full') : '';
            $base['uploadDate'] = get_the_date(DATE_W3C, $post_id);
            if (!$video_url || !$base['thumbnailUrl'] || empty($base['description'])) {
                return array();
            }
        }

        if ('HowTo' === $type) {
            $steps = $this->parse_steps(get_post_meta($post_id, '_codeclinch_geo_schema_steps', true));
            if (!$steps) {
                return array();
            }
            $content = $this->normalize_visible_text(get_post_field('post_content', $post_id));
            foreach ($steps as $step) {
                if (false === strpos($content, $this->normalize_visible_text($step['text']))) {
                    return array();
                }
            }
            $base['step'] = $steps;
        }

        if ('Event' === $type) {
            $date = get_post_meta($post_id, '_codeclinch_geo_schema_event_date', true);
            $location = get_post_meta($post_id, '_codeclinch_geo_schema_location', true);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}(?:T.+)?$/', (string) $date) || false === strtotime($date) || !$location) {
                return array();
            }
            $base['startDate'] = $date;
            $base['location'] = array_filter(
                array(
                    '@type' => 'Place',
                    'name' => $location,
                )
            );
        }

        if ('JobPosting' === $type) {
            if (empty($base['description']) || !get_post_meta($post_id, '_codeclinch_geo_schema_location', true)) {
                return array();
            }
            $base['title'] = get_the_title($post_id);
            $base['datePosted'] = $base['datePublished'];
            $base['hiringOrganization'] = array('@id' => home_url('/#organization'));
            $base['jobLocation'] = array(
                '@type' => 'Place',
                'address' => get_post_meta($post_id, '_codeclinch_geo_schema_location', true),
            );
        }

        if ('Product' === $type && empty($base['offers']) && empty($base['aggregateRating'])) {
            return array();
        }

        if ('Course' === $type) {
            if (empty($base['description'])) {
                return array();
            }
            $base['provider'] = array(
                '@type' => 'Organization',
                'name' => get_post_meta($post_id, '_codeclinch_geo_schema_course_provider', true) ?: $options['organization_name'],
                'sameAs' => home_url('/'),
            );
        }

        return array_filter($base);
    }

    private function breadcrumb_schema() {
        $items = array(
            array(
                '@type' => 'ListItem',
                'position' => 1,
                'name' => __('Home', 'codeclinch-geo'),
                'item' => home_url('/'),
            ),
        );

        if (is_singular()) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => 2,
                'name' => get_the_title(get_queried_object_id()),
                'item' => get_permalink(get_queried_object_id()),
            );
        }

        return array(
            '@type' => 'BreadcrumbList',
            '@id' => home_url(add_query_arg(array(), $GLOBALS['wp']->request ?? '')) . '#breadcrumbs',
            'itemListElement' => $items,
        );
    }

    private function normalize_visible_text($value) {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8')));
    }

    private function parse_faq_schema($raw) {
        $items = array();
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) < 2 || !$parts[0] || !$parts[1]) {
                continue;
            }

            $items[] = array(
                '@type' => 'Question',
                'name' => $parts[0],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $parts[1],
                ),
            );
        }

        return $items;
    }

    private function parse_steps($raw) {
        $steps = array();
        foreach ($this->generator->parse_lines($raw) as $index => $line) {
            $steps[] = array(
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'text' => $line,
            );
        }

        return $steps;
    }
}
