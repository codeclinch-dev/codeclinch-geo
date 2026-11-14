<?php
/**
 * Frontend SEO output.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_SEO {
    private $generator;

    public function __construct(CodeClinch_GEO_Generator $generator) {
        $this->generator = $generator;

        add_filter('pre_get_document_title', array($this, 'filter_document_title'), 20);
        add_action('wp_head', array($this, 'output_meta_tags'), 1);
        add_filter('get_canonical_url', array($this, 'filter_canonical'), 10, 2);
        add_filter('wp_robots', array($this, 'filter_robots'));
    }

    public static function seo_owner() {
        $owner = '';
        if (defined('WPSEO_VERSION') || function_exists('YoastSEO')) {
            $owner = 'yoast';
        } elseif (defined('RANK_MATH_VERSION')) {
            $owner = 'rank-math';
        } elseif (function_exists('aioseo')) {
            $owner = 'aioseo';
        } elseif (defined('SEOPRESS_VERSION') || function_exists('seopress_get_service')) {
            $owner = 'seopress';
        } elseif (defined('THE_SEO_FRAMEWORK_VERSION') || function_exists('the_seo_framework')) {
            $owner = 'the-seo-framework';
        } elseif (defined('SLIM_SEO_VER')) {
            $owner = 'slim-seo';
        }
        return (string) apply_filters('interdeft_geo_seo_owner', $owner);
    }

    public function filter_document_title($title) {
        if ($title || !$this->should_output() || (!is_singular() && !is_front_page())) {
            return $title;
        }
        $seo_title = $this->get_title();
        return $seo_title ? esc_html(wp_strip_all_tags($seo_title)) : $title;
    }

    public function filter_canonical($canonical, $post) {
        if (!$this->should_output()) {
            return $canonical;
        }
        $override = $this->get_canonical_override($post->ID);
        return $override ?: $canonical;
    }

    public function filter_robots($robots) {
        if (!$this->should_output()) {
            return $robots;
        }
        $post_id = is_singular() ? get_queried_object_id() : 0;
        if ($post_id && 'noindex' === get_post_meta($post_id, '_codeclinch_geo_robots_index', true)) {
            unset($robots['index']);
            $robots['noindex'] = true;
        }
        if ($post_id && 'nofollow' === get_post_meta($post_id, '_codeclinch_geo_robots_follow', true)) {
            unset($robots['follow']);
            $robots['nofollow'] = true;
        }
        $options = $this->generator->get_options();
        foreach (array('nosnippet', 'noarchive') as $directive) {
            if (!empty($options['seo_robots_' . $directive])) {
                $robots[$directive] = true;
            }
        }
        return $robots;
    }

    private function should_output() {
        return !self::seo_owner() && !empty($this->generator->get_options()['seo_enabled']);
    }

    public function output_meta_tags() {
        $options = $this->generator->get_options();

        if (is_admin() || !$this->should_output() || (!is_singular() && !is_front_page())) {
            return;
        }

        $title = $this->get_title();
        $description = $this->get_description();
        $social_title = $this->get_social_title();
        $social_description = $this->get_social_description();
        $canonical = $this->get_canonical();
        $image = $this->get_image();

        if ($description) {
            printf('<meta name="description" content="%s">' . "\n", esc_attr($description));
        }

        if (is_front_page() && !is_singular() && $canonical) {
            printf('<link rel="canonical" href="%s">' . "\n", esc_url($canonical));
        }

        if (!empty($options['seo_social_enabled'])) {
            $url = $canonical ? $canonical : home_url(add_query_arg(array(), $GLOBALS['wp']->request ?? ''));
            printf('<meta property="og:title" content="%s">' . "\n", esc_attr($social_title ? $social_title : $title));
            printf('<meta property="og:description" content="%s">' . "\n", esc_attr($social_description ? $social_description : $description));
            printf('<meta property="og:url" content="%s">' . "\n", esc_url($url));
            printf('<meta property="og:site_name" content="%s">' . "\n", esc_attr(get_bloginfo('name')));
            printf('<meta property="og:type" content="%s">' . "\n", is_singular() ? 'article' : 'website');
            if ($image) {
                printf('<meta property="og:image" content="%s">' . "\n", esc_url($image));
            }

            printf('<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary');
            printf('<meta name="twitter:title" content="%s">' . "\n", esc_attr($social_title ? $social_title : $title));
            printf('<meta name="twitter:description" content="%s">' . "\n", esc_attr($social_description ? $social_description : $description));
            if ($image) {
                printf('<meta name="twitter:image" content="%s">' . "\n", esc_url($image));
            }
        }
    }

    public function get_title($post_id = 0) {
        $post_id = $post_id ?: (is_singular() ? get_queried_object_id() : 0);
        $custom = $post_id ? get_post_meta($post_id, '_codeclinch_geo_seo_title', true) : '';

        if ($custom) {
            return $this->replace_tokens($custom, $post_id);
        }

        $options = $this->generator->get_options();
        $template = is_singular() ? $options['seo_title_template'] : $options['seo_home_title'];

        if (is_front_page()) {
            $template = $options['seo_home_title'];
        }

        return $this->replace_tokens($template, $post_id);
    }

    public function get_description($post_id = 0) {
        $post_id = $post_id ?: (is_singular() ? get_queried_object_id() : 0);
        $custom = $post_id ? get_post_meta($post_id, '_codeclinch_geo_meta_description', true) : '';

        if ($custom) {
            return $this->replace_tokens($custom, $post_id);
        }

        if (is_singular() && $post_id) {
            $excerpt = get_the_excerpt($post_id);
            if ($excerpt) {
                return wp_trim_words(wp_strip_all_tags($excerpt), 28, '');
            }
        }

        $options = $this->generator->get_options();
        return $this->replace_tokens($options['seo_home_description'], $post_id);
    }

    public function get_canonical($post_id = 0) {
        $post_id = $post_id ?: (is_singular() ? get_queried_object_id() : 0);
        if ($post_id) {
            return wp_get_canonical_url($post_id);
        }
        return is_front_page() ? home_url('/') : '';
    }

    private function get_canonical_override($post_id) {
        $url = get_post_meta($post_id, '_codeclinch_geo_canonical', true);
        return preg_match('~^https?://~i', (string) $url) ? esc_url_raw($url, array('http', 'https')) : '';
    }

    public function get_image($post_id = 0) {
        $post_id = $post_id ?: (is_singular() ? get_queried_object_id() : 0);
        $custom = $post_id ? get_post_meta($post_id, '_codeclinch_geo_social_image', true) : '';

        if ($custom) {
            return esc_url_raw($custom);
        }

        if ($post_id && has_post_thumbnail($post_id)) {
            return get_the_post_thumbnail_url($post_id, 'full');
        }

        $options = $this->generator->get_options();
        return !empty($options['seo_default_social_image']) ? esc_url_raw($options['seo_default_social_image']) : '';
    }

    public function get_social_title($post_id = 0) {
        $post_id = $post_id ?: (is_singular() ? get_queried_object_id() : 0);
        $custom = $post_id ? get_post_meta($post_id, '_codeclinch_geo_social_title', true) : '';

        return $custom ? $this->replace_tokens($custom, $post_id) : '';
    }

    public function get_social_description($post_id = 0) {
        $post_id = $post_id ?: (is_singular() ? get_queried_object_id() : 0);
        $custom = $post_id ? get_post_meta($post_id, '_codeclinch_geo_social_description', true) : '';

        return $custom ? $this->replace_tokens($custom, $post_id) : '';
    }

    private function replace_tokens($value, $post_id = 0) {
        $title = $post_id ? get_the_title($post_id) : get_bloginfo('name');

        return trim(
            str_replace(
                array('%title%', '%sitename%', '%tagline%'),
                array($title, get_bloginfo('name'), get_bloginfo('description')),
                (string) $value
            )
        );
    }
}
