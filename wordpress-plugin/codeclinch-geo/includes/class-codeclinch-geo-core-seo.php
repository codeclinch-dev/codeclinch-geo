<?php
/**
 * Core SEO modules: sitemaps, redirects, 404 monitor, image SEO, RSS, breadcrumbs.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_Core_SEO {
    private $generator;

    public function __construct(CodeClinch_GEO_Generator $generator) {
        $this->generator = $generator;

        add_action('template_redirect', array($this, 'handle_redirects'), 0);
        add_action('template_redirect', array($this, 'log_404'), 99);
        add_filter('wp_get_attachment_image_attributes', array($this, 'auto_image_alt'), 10, 3);
        add_filter('the_content_feed', array($this, 'append_rss_footer'));
        add_filter('the_excerpt_rss', array($this, 'append_rss_footer'));
        add_shortcode('codeclinch_breadcrumbs', array($this, 'breadcrumb_shortcode'));
        add_shortcode('interdeft_breadcrumbs', array($this, 'breadcrumb_shortcode'));
        add_filter('wp_sitemaps_post_types', array($this, 'sitemap_post_types'));
        add_filter('wp_sitemaps_posts_query_args', array($this, 'sitemap_query_args'), 10, 2);
        add_filter('wp_sitemaps_posts_pre_url_list', array($this, 'core_sitemap_page'), 10, 3);
    }

    public function render_endpoint() {
        $endpoint = get_query_var('codeclinch_geo_core_endpoint');
        if (!$endpoint) {
            return;
        }

        $options = $this->generator->get_options();
        if (!get_option('blog_public') || empty($options['core_sitemap_enabled'])) {
            status_header(404);
            exit;
        }

        if ('xml_sitemap' === $endpoint) {
            $sitemap = self::get_active_sitemap_url();
            if ($sitemap && wp_safe_redirect($sitemap, 302, 'Interdeft')) {
                exit;
            }
            status_header(404);
            exit;
        }

        if ('html_sitemap' === $endpoint) {
            status_header(200);
            $block_theme = wp_is_block_theme();
            if ($block_theme) {
                echo '<!doctype html><html ';
                language_attributes();
                echo '><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
                wp_head();
                echo '</head><body ';
                body_class();
                echo '>';
                wp_body_open();
                echo '<div class="wp-site-blocks">';
                block_template_part('header');
            } else {
                get_header();
            }
            echo '<main class="codeclinch-html-sitemap"><h1>' . esc_html__('Sitemap', 'codeclinch-geo') . '</h1>';
            echo $this->generate_html_sitemap(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</main>';
            if ($block_theme) {
                block_template_part('footer');
                echo '</div>';
                wp_footer();
                echo '</body></html>';
            } else {
                get_footer();
            }
            exit;
        }
    }

    public function handle_redirects() {
        if (is_admin()) {
            return;
        }

        $options = $this->generator->get_options();
        if (empty($options['redirects_enabled'])) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $request_path = CodeClinch_GEO_Activity::request_path($request_uri);
        $current = '/' . trim((string) $request_path, '/');
        foreach ($this->parse_redirects($options['redirects_rules']) as $rule) {
            if ($rule['source'] === $current) {
                if (wp_safe_redirect($rule['target'], $rule['code'], 'Interdeft')) {
                    exit;
                }
                return;
            }
        }
    }

    public function log_404() {
        $options = $this->generator->get_options();
        if (empty($options['monitor_404_enabled']) || !is_404()) {
            return;
        }

        CodeClinch_GEO_Activity::log('404');
    }

    public function auto_image_alt($attributes, $attachment, $size) {
        $options = $this->generator->get_options();
        if (empty($options['image_auto_alt_enabled']) || !empty($attributes['alt'])) {
            return $attributes;
        }

        $template = $options['image_alt_template'] ? $options['image_alt_template'] : '%title%';
        $attributes['alt'] = str_replace(
            array('%title%', '%sitename%'),
            array(get_the_title($attachment->ID), get_bloginfo('name')),
            $template
        );

        return $attributes;
    }

    public function append_rss_footer($content) {
        $options = $this->generator->get_options();
        if (empty($options['rss_footer_enabled']) || empty($options['rss_footer_content'])) {
            return $content;
        }

        return $content . "\n\n" . wp_kses_post($options['rss_footer_content']);
    }

    public function breadcrumb_shortcode() {
        $items = $this->get_breadcrumb_items();
        if (empty($items)) {
            return '';
        }

        $parts = array();
        foreach ($items as $item) {
            $parts[] = sprintf('<a href="%s">%s</a>', esc_url($item['url']), esc_html($item['label']));
        }

        return '<nav class="codeclinch-breadcrumbs" aria-label="Breadcrumb">' . implode(' <span>/</span> ', $parts) . '</nav>';
    }

    public static function get_active_sitemap_url() {
        if (!get_option('blog_public')) {
            return '';
        }
        $owner = CodeClinch_GEO_SEO::seo_owner();
        $url = '';
        if ('yoast' === $owner && class_exists('WPSEO_Options') && WPSEO_Options::get('enable_xml_sitemap', true)) {
            $url = home_url('/sitemap_index.xml');
        } elseif ('rank-math' === $owner && class_exists('RankMath\Helper') && RankMath\Helper::is_module_active('sitemap')) {
            $url = home_url('/sitemap_index.xml');
        } elseif ('aioseo' === $owner && function_exists('aioseo') && !empty(aioseo()->options->sitemap->general->enable)) {
            $url = home_url('/sitemap.xml');
        }
        if (!$url && function_exists('wp_sitemaps_get_server')) {
            $server = wp_sitemaps_get_server();
            if ($server && $server->sitemaps_enabled()) {
                $url = $server->index->get_index_url();
            }
        }
        return (string) apply_filters('interdeft_geo_sitemap_url', $url, $owner);
    }

    private function extends_core_sitemap() {
        return !CodeClinch_GEO_SEO::seo_owner() && !empty($this->generator->get_options()['core_sitemap_enabled']);
    }

    public function sitemap_post_types($types) {
        if (!$this->extends_core_sitemap()) {
            return $types;
        }
        $options = $this->generator->get_options();
        foreach (array('post' => 'sitemap_include_posts', 'page' => 'sitemap_include_pages') as $type => $setting) {
            if (empty($options[$setting])) {
                unset($types[$type]);
            }
        }
        return $types;
    }

    public function sitemap_query_args($args, $post_type) {
        if (!$this->extends_core_sitemap()) {
            return $args;
        }
        $args['has_password'] = false;
        $args['meta_query'] = array(
            'relation' => 'AND',
            $args['meta_query'] ?? array(),
            array(
                'relation' => 'OR',
                array('key' => '_codeclinch_geo_robots_index', 'compare' => 'NOT EXISTS'),
                array('key' => '_codeclinch_geo_robots_index', 'value' => 'noindex', 'compare' => '!='),
            ),
        );
        return $args;
    }

    public function core_sitemap_page($entries, $post_type, $page) {
        if (null !== $entries || !$this->extends_core_sitemap()) {
            return $entries;
        }
        $args = apply_filters('wp_sitemaps_posts_query_args', array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'orderby' => 'ID',
            'order' => 'ASC',
            'ignore_sticky_posts' => true,
            'posts_per_page' => wp_sitemaps_get_max_urls('post'),
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'paged' => $page,
        ), $post_type);
        $entries = array();
        if ('page' === $post_type && 1 === (int) $page && 'posts' === get_option('show_on_front')) {
            $entries[] = apply_filters('wp_sitemaps_posts_show_on_front_entry', array('loc' => home_url('/')));
        }
        foreach ((new WP_Query($args))->posts as $post) {
            if ($this->generator->is_discoverable($post)) {
                $entries[] = apply_filters('wp_sitemaps_posts_entry', array(
                    'loc' => get_permalink($post),
                    'lastmod' => get_post_modified_time(DATE_W3C, true, $post),
                ), $post, $post_type);
            }
        }
        return $entries;
    }

    public function generate_html_sitemap() {
        $options = $this->generator->get_options();
        $types = array();
        foreach (array('post' => 'sitemap_include_posts', 'page' => 'sitemap_include_pages') as $type => $setting) {
            if (!empty($options[$setting])) {
                $types[] = $type;
            }
        }
        if (!$types) {
            return '';
        }

        $page = max(1, absint(get_query_var('paged')));
        $query = new WP_Query(array(
            'post_type' => $types,
            'post_status' => 'publish',
            'has_password' => false,
            'ignore_sticky_posts' => true,
            'posts_per_page' => 100,
            'paged' => $page,
        ));
        $html = '<ul>';
        foreach ($query->posts as $post) {
            if ($this->generator->is_discoverable($post)) {
                $html .= sprintf('<li><a href="%s">%s</a></li>', esc_url(get_permalink($post)), esc_html(get_the_title($post)));
            }
        }
        $html .= '</ul>';
        return $html . (string) paginate_links(array(
            'base' => home_url('/interdeft-html-sitemap/?paged=%#%'),
            'current' => $page,
            'total' => $query->max_num_pages,
        ));
    }

    public function get_missing_alt_images($limit = 20) {
        $query = new WP_Query(
            array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'post_mime_type' => 'image',
                'posts_per_page' => min(100, max(1, absint($limit) * 5)),
                'fields' => 'ids',
                'no_found_rows' => true,
            )
        );

        $missing = array();
        foreach ($query->posts as $attachment_id) {
            if ('' !== trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true))) {
                continue;
            }

            $missing[] = get_post($attachment_id);
            if (count($missing) >= $limit) {
                break;
            }
        }

        return $missing;
    }

    public function get_404_logs() {
        return CodeClinch_GEO_Activity::get('404', 100);
    }

    public function parse_redirects($raw, &$invalid = null) {
        $rules = array();
        $invalid = false;
        $home = wp_parse_url(home_url('/'));
        foreach (preg_split('/\R/', trim((string) $raw)) as $line) {
            if ('' === trim($line)) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $source = $parts[0];
            $target = $parts[1] ?? '';
            $source_path = wp_parse_url($source, PHP_URL_PATH);
            $target_parts = wp_parse_url($target);
            $local = !empty($target_parts['host'])
                ? 0 === strcasecmp($target_parts['host'], $home['host'])
                    && ($target_parts['port'] ?? null) === ($home['port'] ?? null)
                    && in_array($target_parts['scheme'] ?? '', array('http', 'https'), true)
                    && !isset($target_parts['user'])
                : '/' === substr($target, 0, 1) && '//' !== substr($target, 0, 2);
            if ($source !== $source_path || '/' !== substr($source, 0, 1) || !$local
                || preg_match('/[\x00-\x20]/', $target) || false !== strpos($target, chr(92))
                || preg_match('/%0[ad]/i', $target)
                || preg_match('~/(?:\.|%2e){1,2}(?:/|$)~i', $target_parts['path'] ?? '')
                || !wp_validate_redirect($target, '')) {
                $invalid = true;
                continue;
            }
            $source = '/' . trim($source, '/');
            if (isset($rules[$source])) {
                $invalid = true;
                continue;
            }
            $code = isset($parts[2]) ? absint($parts[2]) : 301;
            $rules[$source] = array(
                'source' => $source,
                'target' => $target,
                'code' => in_array($code, array(301, 302, 307, 308), true) ? $code : 301,
            );
        }

        $valid = $rules;
        foreach ($rules as $source => $rule) {
            $visited = array();
            $next = $source;
            while (isset($rules[$next])) {
                if (isset($visited[$next])) {
                    unset($valid[$source]);
                    $invalid = true;
                    break;
                }
                $visited[$next] = true;
                $next = '/' . trim((string) wp_parse_url($rules[$next]['target'], PHP_URL_PATH), '/');
            }
        }
        return array_values($valid);
    }

    private function get_breadcrumb_items() {
        $items = array(
            array(
                'label' => __('Home', 'codeclinch-geo'),
                'url' => home_url('/'),
            ),
        );

        if (is_singular()) {
            $post_id = get_queried_object_id();
            $items[] = array(
                'label' => get_the_title($post_id),
                'url' => get_permalink($post_id),
            );
        } elseif (is_archive()) {
            $items[] = array(
                'label' => get_the_archive_title(),
                'url' => '',
            );
        }

        return $items;
    }
}
