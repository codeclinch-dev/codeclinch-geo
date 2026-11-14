<?php
/**
 * WooCommerce SEO helpers.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_WooCommerce {
    private $generator;

    public function __construct(CodeClinch_GEO_Generator $generator) {
        $this->generator = $generator;

        add_filter('woocommerce_structured_data_product', array($this, 'extend_product_schema'), 10, 2);
    }

    public function extend_product_schema($schema, $product) {
        $options = $this->generator->get_options();
        if (CodeClinch_GEO_SEO::seo_owner() || empty($options['woocommerce_seo_enabled'])
            || empty($options['woocommerce_sync_product_schema']) || !is_a($product, 'WC_Product')) {
            return $schema;
        }
        if (!empty($options['woocommerce_brand_schema_enabled']) && empty($schema['brand'])) {
            $brands = wc_get_product_terms($product->get_id(), 'product_brand', array('fields' => 'names'));
            if (is_array($brands) && !empty($brands[0])) {
                $schema['brand'] = array('@type' => 'Brand', 'name' => $brands[0]);
            }
        }
        return $schema;
    }

    public function get_product_audit($limit = 20) {
        if (!post_type_exists('product')) {
            return array();
        }

        $query = new WP_Query(
            array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'no_found_rows' => true,
            )
        );

        $items = array();
        foreach ($query->posts as $post) {
            $issues = array();

            if (!get_post_meta($post->ID, '_codeclinch_geo_meta_description', true)) {
                $issues[] = __('Missing meta description', 'codeclinch-geo');
            }

            $product = function_exists('wc_get_product') ? wc_get_product($post->ID) : null;
            $template_summary = $product ? $this->replace_product_tokens($this->generator->get_options()['woocommerce_ai_summary_template'], $product) : '';
            if (!get_post_meta($post->ID, '_codeclinch_geo_ai_summary', true) && !$template_summary) {
                $issues[] = __('Missing product AI summary', 'codeclinch-geo');
            }

            if (!get_post_meta($post->ID, '_codeclinch_geo_faq', true)) {
                $issues[] = __('Missing product FAQ', 'codeclinch-geo');
            }

            if (!has_post_thumbnail($post->ID)) {
                $issues[] = __('Missing product image', 'codeclinch-geo');
            }

            if (strlen(wp_strip_all_tags($post->post_content)) < 300) {
                $issues[] = __('Thin product description', 'codeclinch-geo');
            }

            if (!empty($issues)) {
                $items[] = array(
                    'id' => $post->ID,
                    'title' => get_the_title($post),
                    'editUrl' => get_edit_post_link($post->ID),
                    'issues' => $issues,
                );
            }

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    public function is_available() {
        return post_type_exists('product') || class_exists('WooCommerce');
    }

    private function replace_product_tokens($template, $product) {
        return trim(
            str_replace(
                array('%product%', '%price%', '%sku%', '%sitename%'),
                array($product->get_name(), $product->get_price(), $product->get_sku(), get_bloginfo('name')),
                (string) $template
            )
        );
    }
}
