<?php
/**
 * Admin settings.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_Admin {
    private $generator;

    private $ai_provider;

    private $core_seo;

    private $ai_visibility;

    private $woocommerce;

    private $local_seo;

    private $reports;

    public function __construct(CodeClinch_GEO_Generator $generator, CodeClinch_GEO_AI_Provider $ai_provider, CodeClinch_GEO_Core_SEO $core_seo, CodeClinch_GEO_AI_Visibility $ai_visibility, CodeClinch_GEO_WooCommerce $woocommerce, CodeClinch_GEO_Local_SEO $local_seo, CodeClinch_GEO_Reports $reports) {
        $this->generator = $generator;
        $this->ai_provider = $ai_provider;
        $this->core_seo = $core_seo;
        $this->ai_visibility = $ai_visibility;
        $this->woocommerce = $woocommerce;
        $this->local_seo = $local_seo;
        $this->reports = $reports;

        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'output_storage_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('admin_footer_text', array($this, 'filter_admin_footer_text'));
        add_action('wp_ajax_codeclinch_geo_test_ai', array($this, 'ajax_test_ai'));
        add_action('wp_ajax_codeclinch_geo_generate_ai', array($this, 'ajax_generate_ai'));
    }

    public function add_menu() {
        $options = $this->generator->get_options();
        $menu_label = $this->get_admin_title($options);

        add_menu_page(
            $menu_label,
            $menu_label,
            'manage_options',
            'codeclinch-geo',
            array($this, 'render_page'),
            'dashicons-chart-area',
            58
        );

        foreach ($this->get_sections() as $slug => $section) {
            add_submenu_page(
                'codeclinch-geo',
                $section['label'],
                $section['label'],
                'manage_options',
                'codeclinch-geo&section=' . $slug,
                array($this, 'render_page')
            );
        }
    }

    public function register_settings() {
        register_setting(
            'codeclinch_geo_settings',
            'codeclinch_geo_options',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_options'),
            )
        );
    }

    public function enqueue_assets($hook) {
        $is_plugin_page = false !== strpos($hook, 'codeclinch-geo');
        $is_editor_page = in_array($hook, array('post.php', 'post-new.php'), true);

        if (!$is_plugin_page && !$is_editor_page) {
            return;
        }

        wp_enqueue_style(
            'codeclinch-geo-admin',
            CODECLINCH_GEO_URL . 'assets/admin.css',
            array(),
            CODECLINCH_GEO_VERSION
        );

        if (!$is_plugin_page) {
            return;
        }

        wp_enqueue_script(
            'codeclinch-geo-admin',
            CODECLINCH_GEO_URL . 'assets/admin.js',
            array(),
            CODECLINCH_GEO_VERSION,
            true
        );

        wp_localize_script(
            'codeclinch-geo-admin',
            'InterdeftGEO',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('codeclinch_geo_ai'),
            )
        );
    }

    public function sanitize_options($input) {
        $input = is_array($input) ? $input : array();
        $section = isset($input['_section']) ? sanitize_key($input['_section']) : 'settings';
        $output = $this->generator->get_options();

        if ('settings' === $section || 'ai-visibility' === $section) {
            $output['enabled'] = empty($input['enabled']) ? 0 : 1;
            $output['site_summary'] = sanitize_textarea_field($input['site_summary'] ?? '');
            $output['citation_guidance'] = sanitize_textarea_field($input['citation_guidance'] ?? '');
            $output['services'] = sanitize_textarea_field($input['services'] ?? '');
            $output['include_posts'] = empty($input['include_posts']) ? 0 : 1;
            $output['include_pages'] = empty($input['include_pages']) ? 0 : 1;
            $output['ai_enabled'] = empty($input['ai_enabled']) ? 0 : 1;
            $output['ai_consent'] = empty($input['ai_consent']) ? 0 : 1;
            $output['crawler_policies'] = array();
            foreach (CodeClinch_GEO_AI_Visibility::crawler_policy_tokens() as $agent => $label) {
                $policy = $input['crawler_policies'][$agent] ?? '';
                if (in_array($policy, array('inherit', 'block'), true)) {
                    $output['crawler_policies'][$agent] = $policy;
                }
            }
            $output['ai_provider'] = in_array(($input['ai_provider'] ?? 'openai'), array('openai', 'openrouter'), true) ? sanitize_key($input['ai_provider']) : 'openai';
            $output['openai_api_key'] = $this->sanitize_secret($input, 'openai_api_key');
            $output['openai_model'] = sanitize_text_field($input['openai_model'] ?? 'gpt-4o-mini');
            $output['openrouter_api_key'] = $this->sanitize_secret($input, 'openrouter_api_key');
            $output['openrouter_model'] = sanitize_text_field($input['openrouter_model'] ?? 'openai/gpt-4o-mini');
            $output['ai_usage_limit'] = max(0, absint($input['ai_usage_limit'] ?? 100));
            $output['ai_crawler_logs_enabled'] = empty($input['ai_crawler_logs_enabled']) ? 0 : 1;
            $output['geo_proof_points'] = sanitize_textarea_field($input['geo_proof_points'] ?? '');
            $output['geo_business_facts'] = sanitize_textarea_field($input['geo_business_facts'] ?? '');
            $output['geo_pricing_summary'] = sanitize_textarea_field($input['geo_pricing_summary'] ?? '');
            $output['geo_location_summary'] = sanitize_textarea_field($input['geo_location_summary'] ?? '');
            $output['geo_important_pages'] = sanitize_textarea_field($input['geo_important_pages'] ?? '');
        }

        if ('settings' === $section || 'woocommerce-seo' === $section) {
            $output['woocommerce_seo_enabled'] = empty($input['woocommerce_seo_enabled']) ? 0 : 1;
            $output['woocommerce_sync_product_schema'] = empty($input['woocommerce_sync_product_schema']) ? 0 : 1;
            $output['woocommerce_ai_summary_template'] = sanitize_textarea_field($input['woocommerce_ai_summary_template'] ?? '');
            $output['woocommerce_brand_schema_enabled'] = empty($input['woocommerce_brand_schema_enabled']) ? 0 : 1;
        }

        if ('settings' === $section || 'local-seo' === $section) {
            $output['local_seo_enabled'] = empty($input['local_seo_enabled']) ? 0 : 1;
            $output['local_business_name'] = sanitize_text_field($input['local_business_name'] ?? '');
            $output['local_phone'] = sanitize_text_field($input['local_phone'] ?? '');
            $output['local_address'] = sanitize_textarea_field($input['local_address'] ?? '');
            $output['local_service_areas'] = sanitize_textarea_field($input['local_service_areas'] ?? '');
            $output['local_opening_hours'] = sanitize_textarea_field($input['local_opening_hours'] ?? '');
            $output['local_map_url'] = esc_url_raw($input['local_map_url'] ?? '');
            $output['local_locations'] = sanitize_textarea_field($input['local_locations'] ?? '');
            $output['local_city_page_template'] = sanitize_text_field($input['local_city_page_template'] ?? '%service% in %city% | %sitename%');
        }

        if ('settings' === $section || 'seo-settings' === $section || 'titles-meta' === $section) {
            $output['seo_enabled'] = empty($input['seo_enabled']) ? 0 : 1;
            $output['seo_social_enabled'] = empty($input['seo_social_enabled']) ? 0 : 1;
            $output['seo_title_template'] = sanitize_text_field($input['seo_title_template'] ?? '%title% | %sitename%');
            $output['seo_home_title'] = sanitize_text_field($input['seo_home_title'] ?? '%sitename% | %tagline%');
            $output['seo_home_description'] = sanitize_textarea_field($input['seo_home_description'] ?? '');
            $output['seo_default_social_image'] = esc_url_raw($input['seo_default_social_image'] ?? '');
            $output['seo_robots_noarchive'] = empty($input['seo_robots_noarchive']) ? 0 : 1;
            $output['seo_robots_nosnippet'] = empty($input['seo_robots_nosnippet']) ? 0 : 1;
            $output['breadcrumbs_enabled'] = empty($input['breadcrumbs_enabled']) ? 0 : 1;
            $output['image_auto_alt_enabled'] = empty($input['image_auto_alt_enabled']) ? 0 : 1;
            $output['image_alt_template'] = sanitize_text_field($input['image_alt_template'] ?? '%title% - %sitename%');
            $output['rss_footer_enabled'] = empty($input['rss_footer_enabled']) ? 0 : 1;
            $output['rss_footer_content'] = wp_kses_post($input['rss_footer_content'] ?? '');
        }

        if ('settings' === $section || 'sitemap' === $section) {
            $output['core_sitemap_enabled'] = empty($input['core_sitemap_enabled']) ? 0 : 1;
            $output['sitemap_include_posts'] = empty($input['sitemap_include_posts']) ? 0 : 1;
            $output['sitemap_include_pages'] = empty($input['sitemap_include_pages']) ? 0 : 1;
            $output['robots_editor_content'] = sanitize_textarea_field($input['robots_editor_content'] ?? '');
            $output['htaccess_editor_content'] = sanitize_textarea_field($input['htaccess_editor_content'] ?? '');
        }

        if ('settings' === $section || 'redirects' === $section) {
            $output['redirects_enabled'] = empty($input['redirects_enabled']) ? 0 : 1;
            $redirects = sanitize_textarea_field($input['redirects_rules'] ?? '');
            $this->core_seo->parse_redirects($redirects, $invalid);
            if ($invalid) {
                add_settings_error('codeclinch_geo_options', 'interdeft_geo_invalid_redirect', __('Redirect rules contain a loop, duplicate source, or invalid destination. Review the rules and try again.', 'codeclinch-geo'));
            } else {
                $output['redirects_rules'] = $redirects;
            }
            $output['monitor_404_enabled'] = empty($input['monitor_404_enabled']) ? 0 : 1;
        }

        if ('settings' === $section || 'schema' === $section) {
            $allowed_schema_types = array('auto', 'Article', 'BlogPosting', 'WebPage', 'Product', 'Service', 'FAQPage', 'Review', 'AggregateRating', 'HowTo', 'VideoObject', 'Event', 'JobPosting', 'Course');
            $output['organization_name'] = sanitize_text_field($input['organization_name'] ?? '');
            $output['organization_logo'] = esc_url_raw($input['organization_logo'] ?? '');
            $output['organization_same_as'] = sanitize_textarea_field($input['organization_same_as'] ?? '');
            $output['schema_enabled'] = empty($input['schema_enabled']) ? 0 : 1;
            $output['schema_default_type'] = in_array(($input['schema_default_type'] ?? 'auto'), $allowed_schema_types, true) ? sanitize_text_field($input['schema_default_type'] ?? 'auto') : 'auto';
            $output['schema_business_type'] = in_array(($input['schema_business_type'] ?? 'Organization'), array('Organization', 'LocalBusiness'), true) ? sanitize_text_field($input['schema_business_type']) : 'Organization';
            $output['schema_business_name'] = sanitize_text_field($input['schema_business_name'] ?? '');
            $output['schema_business_phone'] = sanitize_text_field($input['schema_business_phone'] ?? '');
            $output['schema_business_address'] = sanitize_textarea_field($input['schema_business_address'] ?? '');
            $output['schema_service_area'] = sanitize_textarea_field($input['schema_service_area'] ?? '');
            $output['schema_opening_hours'] = sanitize_textarea_field($input['schema_opening_hours'] ?? '');
            $output['schema_price_range'] = sanitize_text_field($input['schema_price_range'] ?? '');
            $output['schema_default_author'] = sanitize_text_field($input['schema_default_author'] ?? '');
        }

        if ('settings' === $section || 'branding' === $section) {
            $output['branding_enabled'] = empty($input['branding_enabled']) ? 0 : 1;
            $output['branding_label'] = sanitize_text_field($input['branding_label'] ?? '');
            $output['branding_url'] = esc_url_raw($input['branding_url'] ?? '');
            $output['admin_branding_enabled'] = empty($input['admin_branding_enabled']) ? 0 : 1;
            $output['admin_footer_branding_enabled'] = empty($input['admin_footer_branding_enabled']) ? 0 : 1;
            $output['white_label_enabled'] = empty($input['white_label_enabled']) ? 0 : 1;
            $output['white_label_plugin_name'] = sanitize_text_field($input['white_label_plugin_name'] ?? 'SEO / GEO Suite');
            $output['white_label_logo_url'] = esc_url_raw($input['white_label_logo_url'] ?? '');
            $output['support_email'] = sanitize_email($input['support_email'] ?? '');
            $output['contact_url'] = esc_url_raw($input['contact_url'] ?? '');
            $output['contact_cta_label'] = sanitize_text_field($input['contact_cta_label'] ?? 'Contact Interdeft');
            $output['service_cta_url'] = esc_url_raw($input['service_cta_url'] ?? '');
            $output['service_cta_label'] = sanitize_text_field($input['service_cta_label'] ?? 'Request Custom Help');
            $output['services_box_title'] = sanitize_text_field($input['services_box_title'] ?? 'Services');
            $output['services_box_items'] = sanitize_textarea_field($input['services_box_items'] ?? '');
        }

        return $output;
    }

    public function ajax_test_ai() {
        $this->verify_ajax_request();

        $result = $this->ai_provider->test_connection($this->generator->get_options());

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function ajax_generate_ai() {
        $this->verify_ajax_request();

        $options = $this->generator->get_options();
        $result = $this->ai_provider->generate_seo_suggestions(
            $options,
            array(
                'site_summary' => $options['site_summary'],
                'services' => $options['services'],
            )
        );

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('content' => $result));
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = $this->generator->get_options();
        $score = $this->generator->calculate_score();
        $section = $this->get_current_section();
        ?>
        <div class="wrap codeclinch-geo-admin">
            <?php settings_errors('codeclinch_geo_options'); ?>
            <?php if (CodeClinch_GEO_SEO::seo_owner()) : ?>
                <div class="notice notice-info inline"><p><?php esc_html_e('An existing SEO plugin controls page metadata and XML sitemaps. Interdeft can provide discovery files, audits, and crawler policy.', 'codeclinch-geo'); ?></p></div>
            <?php endif; ?>
            <div class="codeclinch-geo-header">
                <div class="codeclinch-geo-title-row">
                    <?php if (!empty($options['white_label_logo_url'])) : ?>
                        <img class="codeclinch-geo-logo" src="<?php echo esc_url($options['white_label_logo_url']); ?>" alt="">
                    <?php endif; ?>
                    <div>
                        <h1><?php echo esc_html($this->get_admin_title($options)); ?></h1>
                        <p><?php esc_html_e('SEO, GEO, AI visibility, schema, audits, and growth tools for WordPress.', 'codeclinch-geo'); ?></p>
                    </div>
                </div>
                <div class="codeclinch-geo-header-actions">
                    <a class="button" href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank"><?php esc_html_e('View llms.txt', 'codeclinch-geo'); ?></a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=codeclinch-geo&section=reports')); ?>"><?php esc_html_e('Export Reports', 'codeclinch-geo'); ?></a>
                    <span class="codeclinch-geo-version">v<?php echo esc_html(CODECLINCH_GEO_VERSION); ?></span>
                </div>
            </div>

            <div class="codeclinch-geo-hero">
                <div>
                    <strong><?php esc_html_e('Professional SEO + GEO operating panel', 'codeclinch-geo'); ?></strong>
                    <p><?php esc_html_e('Control discovery files, page scoring, schema, local SEO, ecommerce SEO, and client reports from one workspace.', 'codeclinch-geo'); ?></p>
                </div>
                <div class="codeclinch-geo-hero-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=codeclinch-geo&section=ai-visibility')); ?>"><?php esc_html_e('AI Visibility', 'codeclinch-geo'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=codeclinch-geo&section=schema')); ?>"><?php esc_html_e('Schema', 'codeclinch-geo'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=codeclinch-geo&section=local-seo')); ?>"><?php esc_html_e('Local SEO', 'codeclinch-geo'); ?></a>
                </div>
            </div>

            <?php $this->render_section_nav($section); ?>

            <div class="codeclinch-geo-layout">
                <main class="codeclinch-geo-main">
                    <?php $this->render_section_content($section, $options, $score); ?>
                </main>

                <aside class="codeclinch-geo-sidebar">
                    <?php $this->render_ai_tools_card($options); ?>
                    <?php $this->render_codeclinch_card($options); ?>
                    <?php $this->render_services_card($options); ?>
                    <?php $this->render_custom_requirement_card($options); ?>
                </aside>
            </div>
        </div>
        <?php
    }

    private function get_sections() {
        return array(
            'dashboard' => array('label' => __('Dashboard', 'codeclinch-geo')),
            'seo-settings' => array('label' => __('SEO Settings', 'codeclinch-geo')),
            'titles-meta' => array('label' => __('Titles & Meta', 'codeclinch-geo')),
            'schema' => array('label' => __('Schema', 'codeclinch-geo')),
            'sitemap' => array('label' => __('Sitemap', 'codeclinch-geo')),
            'ai-visibility' => array('label' => __('AI Visibility', 'codeclinch-geo')),
            'content-audit' => array('label' => __('Content Audit', 'codeclinch-geo')),
            'redirects' => array('label' => __('Redirects', 'codeclinch-geo')),
            'woocommerce-seo' => array('label' => __('WooCommerce SEO', 'codeclinch-geo')),
            'local-seo' => array('label' => __('Local SEO', 'codeclinch-geo')),
            'branding' => array('label' => __('Branding / White Label', 'codeclinch-geo')),
            'reports' => array('label' => __('Reports', 'codeclinch-geo')),
            'settings' => array('label' => __('Settings', 'codeclinch-geo')),
        );
    }

    private function get_current_section() {
        $sections = $this->get_sections();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
        $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'dashboard';

        return isset($sections[$section]) ? $section : 'dashboard';
    }

    private function render_section_nav($current) {
        echo '<nav class="codeclinch-geo-nav">';
        foreach ($this->get_sections() as $slug => $section) {
            printf(
                '<a class="%s" href="%s">%s</a>',
                esc_attr($slug === $current ? 'is-active' : ''),
                esc_url(admin_url('admin.php?page=codeclinch-geo&section=' . $slug)),
                esc_html($section['label'])
            );
        }
        echo '</nav>';
    }

    private function render_section_content($section, $options, $score) {
        if ('dashboard' === $section) {
            $this->render_dashboard($options, $score);
            return;
        }

        if ('seo-settings' === $section || 'titles-meta' === $section || 'ai-visibility' === $section || 'branding' === $section || 'settings' === $section || 'sitemap' === $section || 'redirects' === $section || 'schema' === $section || 'woocommerce-seo' === $section || 'local-seo' === $section) {
            echo '<form method="post" action="options.php">';
            settings_fields('codeclinch_geo_settings');
            printf('<input type="hidden" name="codeclinch_geo_options[_section]" value="%s">', esc_attr($section));
            $this->render_settings_table($options, $section);
            submit_button();
            echo '</form>';
            if ('ai-visibility' === $section) {
                $this->render_ai_visibility_panels();
            }
            if ('woocommerce-seo' === $section) {
                $this->render_woocommerce_audit();
            }
            if ('local-seo' === $section) {
                $this->render_local_seo_panels();
            }
            return;
        }

        if ('reports' === $section) {
            $this->render_reports();
            return;
        }

        if ('content-audit' === $section) {
            $this->render_content_audit($options);
            return;
        }

        $this->render_module_placeholder($section);
    }

    private function render_dashboard($options, $score) {
        $seo_score = $this->calculate_seo_score($options);
        ?>
        <div class="codeclinch-geo-stat-grid">
            <div class="codeclinch-geo-stat"><strong><?php esc_html_e('SEO setup', 'codeclinch-geo'); ?></strong><span><?php echo esc_html($seo_score); ?>%</span></div>
            <div class="codeclinch-geo-stat"><strong><?php esc_html_e('Discovery setup', 'codeclinch-geo'); ?></strong><span><?php echo esc_html($score['percentage']); ?>%</span></div>
            <div class="codeclinch-geo-stat"><strong><?php esc_html_e('Enabled Modules', 'codeclinch-geo'); ?></strong><span><?php echo esc_html($this->count_enabled_modules($options)); ?></span></div>
        </div>

        <div class="codeclinch-geo-dashboard-grid">
            <section class="codeclinch-geo-panel">
                <h2><?php esc_html_e('Setup Checklist', 'codeclinch-geo'); ?></h2>
                <?php $this->render_setup_checklist($options); ?>
            </section>
            <section class="codeclinch-geo-panel">
                <h2><?php esc_html_e('Latest Audit Issues', 'codeclinch-geo'); ?></h2>
                <?php $this->render_audit_issues($options); ?>
            </section>
            <section class="codeclinch-geo-panel codeclinch-geo-panel-wide">
                <h2><?php esc_html_e('Enabled Modules', 'codeclinch-geo'); ?></h2>
                <?php $this->render_enabled_modules($options); ?>
            </section>
            <section class="codeclinch-geo-panel codeclinch-geo-panel-wide">
                <h2><?php esc_html_e('Setup Workflows', 'codeclinch-geo'); ?></h2>
                <div class="codeclinch-geo-workflow-grid">
                    <?php foreach ($this->get_workflow_cards() as $card) : ?>
                        <a href="<?php echo esc_url($card['url']); ?>">
                            <strong><?php echo esc_html($card['title']); ?></strong>
                            <span><?php echo esc_html($card['description']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="codeclinch-geo-links">
            <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank"><?php esc_html_e('llms.txt', 'codeclinch-geo'); ?></a>
            <a href="<?php echo esc_url(home_url('/ai-visibility.json')); ?>" target="_blank"><?php esc_html_e('ai-visibility.json', 'codeclinch-geo'); ?></a>
            <a href="<?php echo esc_url(home_url('/.well-known/agent.json')); ?>" target="_blank"><?php esc_html_e('agent.json', 'codeclinch-geo'); ?></a>
        </div>
        <?php
    }

    private function render_settings_table($options, $section = 'settings') {
        ?>
        <table class="form-table codeclinch-geo-settings" role="presentation">
            <?php if ('settings' === $section || 'ai-visibility' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Enable GEO endpoints', 'codeclinch-geo'); ?></th>
                <td><label><input type="checkbox" name="codeclinch_geo_options[enabled]" value="1" <?php checked(!empty($options['enabled'])); ?>> <?php esc_html_e('Enabled', 'codeclinch-geo'); ?></label></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Site summary', 'codeclinch-geo'); ?></th>
                <td><textarea class="large-text" rows="4" name="codeclinch_geo_options[site_summary]"><?php echo esc_textarea($options['site_summary']); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Citation guidance', 'codeclinch-geo'); ?></th>
                <td><textarea class="large-text" rows="3" name="codeclinch_geo_options[citation_guidance]"><?php echo esc_textarea($options['citation_guidance']); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Services', 'codeclinch-geo'); ?></th>
                <td>
                    <textarea class="large-text code" rows="6" name="codeclinch_geo_options[services]"><?php echo esc_textarea($options['services']); ?></textarea>
                    <p class="description"><?php esc_html_e('One service per line. Format: Service name | Short AI-readable description.', 'codeclinch-geo'); ?></p>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'seo-settings' === $section || 'titles-meta' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('SEO module', 'codeclinch-geo'); ?></th>
                <td>
                    <label><input type="checkbox" name="codeclinch_geo_options[seo_enabled]" value="1" <?php checked(!empty($options['seo_enabled'])); ?>> <?php esc_html_e('Output SEO meta tags', 'codeclinch-geo'); ?></label><br>
                    <label><input type="checkbox" name="codeclinch_geo_options[seo_social_enabled]" value="1" <?php checked(!empty($options['seo_social_enabled'])); ?>> <?php esc_html_e('Output OpenGraph and Twitter tags', 'codeclinch-geo'); ?></label>
                    <p><label><?php esc_html_e('Post title template', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[seo_title_template]" value="<?php echo esc_attr($options['seo_title_template']); ?>"></label></p>
                    <p><label><?php esc_html_e('Homepage title', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[seo_home_title]" value="<?php echo esc_attr($options['seo_home_title']); ?>"></label></p>
                    <p><label><?php esc_html_e('Homepage description', 'codeclinch-geo'); ?><br><textarea class="large-text" rows="3" name="codeclinch_geo_options[seo_home_description]"><?php echo esc_textarea($options['seo_home_description']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Default social image URL', 'codeclinch-geo'); ?><br><input class="regular-text" type="url" name="codeclinch_geo_options[seo_default_social_image]" value="<?php echo esc_attr($options['seo_default_social_image']); ?>"></label></p>
                    <p>
                        <label><input type="checkbox" name="codeclinch_geo_options[seo_robots_noarchive]" value="1" <?php checked(!empty($options['seo_robots_noarchive'])); ?>> <?php esc_html_e('Add noarchive to robots meta', 'codeclinch-geo'); ?></label><br>
                        <label><input type="checkbox" name="codeclinch_geo_options[seo_robots_nosnippet]" value="1" <?php checked(!empty($options['seo_robots_nosnippet'])); ?>> <?php esc_html_e('Add nosnippet to robots meta', 'codeclinch-geo'); ?></label><br>
                        <label><input type="checkbox" name="codeclinch_geo_options[breadcrumbs_enabled]" value="1" <?php checked(!empty($options['breadcrumbs_enabled'])); ?>> <?php esc_html_e('Enable breadcrumbs shortcode', 'codeclinch-geo'); ?></label>
                    </p>
                    <p><label><?php esc_html_e('Image alt template', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[image_alt_template]" value="<?php echo esc_attr($options['image_alt_template']); ?>"></label></p>
                    <p><label><input type="checkbox" name="codeclinch_geo_options[image_auto_alt_enabled]" value="1" <?php checked(!empty($options['image_auto_alt_enabled'])); ?>> <?php esc_html_e('Auto-fill missing image alt attributes on frontend output', 'codeclinch-geo'); ?></label></p>
                    <p><label><input type="checkbox" name="codeclinch_geo_options[rss_footer_enabled]" value="1" <?php checked(!empty($options['rss_footer_enabled'])); ?>> <?php esc_html_e('Append RSS footer content', 'codeclinch-geo'); ?></label></p>
                    <p><label><?php esc_html_e('RSS footer content', 'codeclinch-geo'); ?><br><textarea class="large-text" rows="3" name="codeclinch_geo_options[rss_footer_content]"><?php echo esc_textarea($options['rss_footer_content']); ?></textarea></label></p>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: 1: title token, 2: site name token, 3: tagline token. */
                            esc_html__('Available tokens: %1$s, %2$s, %3$s. Per-page fields can override these defaults.', 'codeclinch-geo'),
                            esc_html('%title%'),
                            esc_html('%sitename%'),
                            esc_html('%tagline%')
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'sitemap' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Sitemap and robots', 'codeclinch-geo'); ?></th>
                <td>
                    <label><input type="checkbox" name="codeclinch_geo_options[core_sitemap_enabled]" value="1" <?php checked(!empty($options['core_sitemap_enabled'])); ?>> <?php esc_html_e('Enhance WordPress XML sitemaps and enable the Interdeft HTML sitemap', 'codeclinch-geo'); ?></label>
                    <p>
                        <label><input type="checkbox" name="codeclinch_geo_options[sitemap_include_posts]" value="1" <?php checked(!empty($options['sitemap_include_posts'])); ?>> <?php esc_html_e('Include posts', 'codeclinch-geo'); ?></label><br>
                        <label><input type="checkbox" name="codeclinch_geo_options[sitemap_include_pages]" value="1" <?php checked(!empty($options['sitemap_include_pages'])); ?>> <?php esc_html_e('Include pages', 'codeclinch-geo'); ?></label>
                    </p>
                    <p>
                        <a href="<?php echo esc_url(home_url('/interdeft-sitemap.xml')); ?>" target="_blank"><?php esc_html_e('Open active XML sitemap', 'codeclinch-geo'); ?></a> |
                        <a href="<?php echo esc_url(home_url('/interdeft-html-sitemap/')); ?>" target="_blank"><?php esc_html_e('Open HTML sitemap', 'codeclinch-geo'); ?></a>
                    </p>
                    <p><label><?php esc_html_e('Custom robots.txt rules', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="6" name="codeclinch_geo_options[robots_editor_content]"><?php echo esc_textarea($options['robots_editor_content']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('.htaccess notes / planned rules', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="6" name="codeclinch_geo_options[htaccess_editor_content]"><?php echo esc_textarea($options['htaccess_editor_content']); ?></textarea></label></p>
                    <p class="description"><?php esc_html_e('.htaccess content is saved as notes for safety in this version; it is not written to disk automatically.', 'codeclinch-geo'); ?></p>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'redirects' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Redirects and 404 monitor', 'codeclinch-geo'); ?></th>
                <td>
                    <label><input type="checkbox" name="codeclinch_geo_options[redirects_enabled]" value="1" <?php checked(!empty($options['redirects_enabled'])); ?>> <?php esc_html_e('Enable redirects manager', 'codeclinch-geo'); ?></label><br>
                    <label><input type="checkbox" name="codeclinch_geo_options[monitor_404_enabled]" value="1" <?php checked(!empty($options['monitor_404_enabled'])); ?>> <?php esc_html_e('Enable 404 monitor', 'codeclinch-geo'); ?></label>
                    <p><label><?php esc_html_e('Redirect rules', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="8" name="codeclinch_geo_options[redirects_rules]"><?php echo esc_textarea($options['redirects_rules']); ?></textarea></label></p>
                    <p class="description"><?php esc_html_e('One rule per line. Use a path on this site, for example /old-url | /new-url | 301.', 'codeclinch-geo'); ?></p>
                    <?php $this->render_404_logs(); ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'ai-visibility' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('AI provider', 'codeclinch-geo'); ?></th>
                <td>
                    <label><input type="checkbox" name="codeclinch_geo_options[ai_enabled]" value="1" <?php checked(!empty($options['ai_enabled'])); ?>> <?php esc_html_e('Enable AI writing tools', 'codeclinch-geo'); ?></label>
                    <p>
                        <select name="codeclinch_geo_options[ai_provider]">
                            <option value="openai" <?php selected($options['ai_provider'], 'openai'); ?>><?php esc_html_e('OpenAI direct', 'codeclinch-geo'); ?></option>
                            <option value="openrouter" <?php selected($options['ai_provider'], 'openrouter'); ?>><?php esc_html_e('OpenRouter', 'codeclinch-geo'); ?></option>
                        </select>
                    </p>
                    <p><label><input type="checkbox" name="codeclinch_geo_options[ai_consent]" value="1" <?php checked(!empty($options['ai_consent'])); ?>> <?php esc_html_e('I allow Interdeft to send the site name, URL, site summary, and services to the selected AI provider when an admin requests suggestions.', 'codeclinch-geo'); ?></label></p>
                    <p><label><?php esc_html_e('OpenAI API key', 'codeclinch-geo'); ?><br><input class="regular-text" type="password" autocomplete="off" name="codeclinch_geo_options[openai_api_key]" placeholder="<?php echo esc_attr($this->ai_provider->mask_key($options['openai_api_key'])); ?>"></label></p>
                    <p><label><input type="checkbox" name="codeclinch_geo_options[clear_openai_api_key]" value="1"> <?php esc_html_e('Remove the saved OpenAI key', 'codeclinch-geo'); ?></label></p>
                    <p><label><?php esc_html_e('OpenAI model', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[openai_model]" value="<?php echo esc_attr($options['openai_model']); ?>"></label></p>
                    <p><label><?php esc_html_e('OpenRouter API key', 'codeclinch-geo'); ?><br><input class="regular-text" type="password" autocomplete="off" name="codeclinch_geo_options[openrouter_api_key]" placeholder="<?php echo esc_attr($this->ai_provider->mask_key($options['openrouter_api_key'])); ?>"></label></p>
                    <p><label><input type="checkbox" name="codeclinch_geo_options[clear_openrouter_api_key]" value="1"> <?php esc_html_e('Remove the saved OpenRouter key', 'codeclinch-geo'); ?></label></p>
                    <p><label><?php esc_html_e('OpenRouter model', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[openrouter_model]" value="<?php echo esc_attr($options['openrouter_model']); ?>"></label></p>
                    <p><label><?php esc_html_e('Monthly AI action limit (0 disables requests)', 'codeclinch-geo'); ?><br><input class="small-text" type="number" min="0" name="codeclinch_geo_options[ai_usage_limit]" value="<?php echo esc_attr($options['ai_usage_limit']); ?>"></label></p>
                    <p><label><input type="checkbox" name="codeclinch_geo_options[ai_crawler_logs_enabled]" value="1" <?php checked(!empty($options['ai_crawler_logs_enabled'])); ?>> <?php esc_html_e('Log requests matching AI crawler names for 30 days', 'codeclinch-geo'); ?></label></p>
                    <p><label><?php esc_html_e('Proof points', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="4" name="codeclinch_geo_options[geo_proof_points]"><?php echo esc_textarea($options['geo_proof_points']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Business facts', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="4" name="codeclinch_geo_options[geo_business_facts]"><?php echo esc_textarea($options['geo_business_facts']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Pricing summary', 'codeclinch-geo'); ?><br><textarea class="large-text" rows="3" name="codeclinch_geo_options[geo_pricing_summary]"><?php echo esc_textarea($options['geo_pricing_summary']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Location summary', 'codeclinch-geo'); ?><br><textarea class="large-text" rows="3" name="codeclinch_geo_options[geo_location_summary]"><?php echo esc_textarea($options['geo_location_summary']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Important page guidance', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="4" name="codeclinch_geo_options[geo_important_pages]"><?php echo esc_textarea($options['geo_important_pages']); ?></textarea></label></p>
                    <p class="description"><?php esc_html_e('Leave a key field blank to keep the saved key. You can also define INTERDEFT_GEO_OPENAI_API_KEY or INTERDEFT_GEO_OPENROUTER_API_KEY in wp-config.php to keep the secret outside the database.', 'codeclinch-geo'); ?></p>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'ai-visibility' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Crawler policy', 'codeclinch-geo'); ?></th>
                <td>
                    <?php foreach (CodeClinch_GEO_AI_Visibility::crawler_policy_tokens() as $agent => $label) : ?>
                        <?php $policy = $options['crawler_policies'][$agent] ?? ''; ?>
                        <p><label><?php echo esc_html($label . ' (' . $agent . ')'); ?>
                            <select name="codeclinch_geo_options[crawler_policies][<?php echo esc_attr($agent); ?>]">
                                <option value="" <?php selected($policy, ''); ?>><?php esc_html_e('Use existing rules', 'codeclinch-geo'); ?></option>
                                <option value="inherit" <?php selected($policy, 'inherit'); ?>><?php esc_html_e('Apply site-wide rules', 'codeclinch-geo'); ?></option>
                                <option value="block" <?php selected($policy, 'block'); ?>><?php esc_html_e('Disallow all paths', 'codeclinch-geo'); ?></option>
                            </select>
                        </label></p>
                    <?php endforeach; ?>
                    <p class="description"><?php esc_html_e('Rules already present for a crawler take precedence. These settings apply to the WordPress-generated robots.txt response.', 'codeclinch-geo'); ?></p>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'woocommerce-seo' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('WooCommerce SEO', 'codeclinch-geo'); ?></th>
                <td>
                    <label><input type="checkbox" name="codeclinch_geo_options[woocommerce_seo_enabled]" value="1" <?php checked(!empty($options['woocommerce_seo_enabled'])); ?>> <?php esc_html_e('Enable WooCommerce SEO module', 'codeclinch-geo'); ?></label><br>
                    <label><input type="checkbox" name="codeclinch_geo_options[woocommerce_sync_product_schema]" value="1" <?php checked(!empty($options['woocommerce_sync_product_schema'])); ?>> <?php esc_html_e('Sync product schema from WooCommerce product data on save', 'codeclinch-geo'); ?></label><br>
                    <label><input type="checkbox" name="codeclinch_geo_options[woocommerce_brand_schema_enabled]" value="1" <?php checked(!empty($options['woocommerce_brand_schema_enabled'])); ?>> <?php esc_html_e('Prepare brand/manufacturer schema support', 'codeclinch-geo'); ?></label>
                    <p><label><?php esc_html_e('Product AI summary template', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="3" name="codeclinch_geo_options[woocommerce_ai_summary_template]"><?php echo esc_textarea($options['woocommerce_ai_summary_template']); ?></textarea></label></p>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: 1: product token, 2: price token, 3: SKU token, 4: site name token. */
                            esc_html__('Available tokens: %1$s, %2$s, %3$s, %4$s. Product fields use the Interdeft SEO panel on each product.', 'codeclinch-geo'),
                            esc_html('%product%'),
                            esc_html('%price%'),
                            esc_html('%sku%'),
                            esc_html('%sitename%')
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'local-seo' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Local SEO', 'codeclinch-geo'); ?></th>
                <td>
                    <label><input type="checkbox" name="codeclinch_geo_options[local_seo_enabled]" value="1" <?php checked(!empty($options['local_seo_enabled'])); ?>> <?php esc_html_e('Enable Local SEO module', 'codeclinch-geo'); ?></label>
                    <p><label><?php esc_html_e('Business name', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[local_business_name]" value="<?php echo esc_attr($options['local_business_name']); ?>"></label></p>
                    <p><label><?php esc_html_e('Phone', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[local_phone]" value="<?php echo esc_attr($options['local_phone']); ?>"></label></p>
                    <p><label><?php esc_html_e('Address', 'codeclinch-geo'); ?><br><textarea class="large-text" rows="3" name="codeclinch_geo_options[local_address]"><?php echo esc_textarea($options['local_address']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Service areas', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="4" name="codeclinch_geo_options[local_service_areas]"><?php echo esc_textarea($options['local_service_areas']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Opening hours', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="4" name="codeclinch_geo_options[local_opening_hours]"><?php echo esc_textarea($options['local_opening_hours']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Map URL', 'codeclinch-geo'); ?><br><input class="regular-text" type="url" name="codeclinch_geo_options[local_map_url]" value="<?php echo esc_attr($options['local_map_url']); ?>"></label></p>
                    <p><label><?php esc_html_e('Locations for sitemap', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="5" name="codeclinch_geo_options[local_locations]"><?php echo esc_textarea($options['local_locations']); ?></textarea></label></p>
                    <p class="description"><?php esc_html_e('One location per line. Format: City | Region | https://example.com/service-area/city', 'codeclinch-geo'); ?></p>
                    <p><label><?php esc_html_e('City page title template', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[local_city_page_template]" value="<?php echo esc_attr($options['local_city_page_template']); ?>"></label></p>
                    <p><a href="<?php echo esc_url(home_url('/interdeft-location-sitemap.xml')); ?>" target="_blank"><?php esc_html_e('Open location sitemap', 'codeclinch-geo'); ?></a></p>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'schema' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Schema module', 'codeclinch-geo'); ?></th>
                <td>
                    <label><input type="checkbox" name="codeclinch_geo_options[schema_enabled]" value="1" <?php checked(!empty($options['schema_enabled'])); ?>> <?php esc_html_e('Output Schema.org JSON-LD', 'codeclinch-geo'); ?></label>
                    <p>
                        <label><?php esc_html_e('Default schema type for posts/pages', 'codeclinch-geo'); ?><br>
                            <select name="codeclinch_geo_options[schema_default_type]">
                                <?php foreach ($this->get_schema_types() as $schema_type) : ?>
                                    <option value="<?php echo esc_attr($schema_type); ?>" <?php selected($options['schema_default_type'], $schema_type); ?>><?php echo esc_html('auto' === $schema_type ? __('Automatic by content type', 'codeclinch-geo') : $schema_type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </p>
                    <p><label><?php esc_html_e('Default author / publisher name', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[schema_default_author]" value="<?php echo esc_attr($options['schema_default_author']); ?>"></label></p>
                    <p class="description"><?php esc_html_e('Each post/page can override the schema type from the Interdeft SEO panel.', 'codeclinch-geo'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Organization name', 'codeclinch-geo'); ?></th>
                <td><input class="regular-text" type="text" name="codeclinch_geo_options[organization_name]" value="<?php echo esc_attr($options['organization_name']); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Organization logo URL', 'codeclinch-geo'); ?></th>
                <td><input class="regular-text" type="url" name="codeclinch_geo_options[organization_logo]" value="<?php echo esc_attr($options['organization_logo']); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('SameAs profiles', 'codeclinch-geo'); ?></th>
                <td><textarea class="large-text code" rows="4" name="codeclinch_geo_options[organization_same_as]"><?php echo esc_textarea($options['organization_same_as']); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Business schema', 'codeclinch-geo'); ?></th>
                <td>
                    <p>
                        <label><?php esc_html_e('Homepage business type', 'codeclinch-geo'); ?><br>
                            <select name="codeclinch_geo_options[schema_business_type]">
                                <option value="Organization" <?php selected($options['schema_business_type'], 'Organization'); ?>><?php esc_html_e('Organization', 'codeclinch-geo'); ?></option>
                                <option value="LocalBusiness" <?php selected($options['schema_business_type'], 'LocalBusiness'); ?>><?php esc_html_e('LocalBusiness', 'codeclinch-geo'); ?></option>
                            </select>
                        </label>
                    </p>
                    <p><label><?php esc_html_e('Business name', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[schema_business_name]" value="<?php echo esc_attr($options['schema_business_name']); ?>"></label></p>
                    <p><label><?php esc_html_e('Phone', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[schema_business_phone]" value="<?php echo esc_attr($options['schema_business_phone']); ?>"></label></p>
                    <p><label><?php esc_html_e('Address', 'codeclinch-geo'); ?><br><textarea class="large-text" rows="3" name="codeclinch_geo_options[schema_business_address]"><?php echo esc_textarea($options['schema_business_address']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Service areas', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="3" name="codeclinch_geo_options[schema_service_area]"><?php echo esc_textarea($options['schema_service_area']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Opening hours', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="3" name="codeclinch_geo_options[schema_opening_hours]"><?php echo esc_textarea($options['schema_opening_hours']); ?></textarea></label></p>
                    <p><label><?php esc_html_e('Price range', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[schema_price_range]" value="<?php echo esc_attr($options['schema_price_range']); ?>" placeholder="$$"></label></p>
                    <p class="description"><?php esc_html_e('Use one service area or opening-hours rule per line. Opening-hours format example: Mo-Fr 09:00-18:00.', 'codeclinch-geo'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Supported schema types', 'codeclinch-geo'); ?></th>
                <td>
                    <div class="codeclinch-geo-schema-badges">
                        <?php foreach ($this->get_schema_types(true) as $schema_type) : ?>
                            <span><?php echo esc_html($schema_type); ?></span>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'ai-visibility' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Include content', 'codeclinch-geo'); ?></th>
                <td>
                    <label><input type="checkbox" name="codeclinch_geo_options[include_posts]" value="1" <?php checked(!empty($options['include_posts'])); ?>> <?php esc_html_e('Posts', 'codeclinch-geo'); ?></label><br>
                    <label><input type="checkbox" name="codeclinch_geo_options[include_pages]" value="1" <?php checked(!empty($options['include_pages'])); ?>> <?php esc_html_e('Pages', 'codeclinch-geo'); ?></label>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ('settings' === $section || 'branding' === $section) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Branding / White Label', 'codeclinch-geo'); ?></th>
                <td>
                    <label><input type="checkbox" name="codeclinch_geo_options[branding_enabled]" value="1" <?php checked(!empty($options['branding_enabled'])); ?>> <?php esc_html_e('Show footer attribution', 'codeclinch-geo'); ?></label>
                    <br><label><input type="checkbox" name="codeclinch_geo_options[admin_branding_enabled]" value="1" <?php checked(!empty($options['admin_branding_enabled'])); ?>> <?php esc_html_e('Show admin sidebar branding', 'codeclinch-geo'); ?></label>
                    <br><label><input type="checkbox" name="codeclinch_geo_options[admin_footer_branding_enabled]" value="1" <?php checked(!empty($options['admin_footer_branding_enabled'])); ?>> <?php esc_html_e('Show admin footer attribution on Interdeft pages', 'codeclinch-geo'); ?></label>
                    <br><label><input type="checkbox" name="codeclinch_geo_options[white_label_enabled]" value="1" <?php checked(!empty($options['white_label_enabled'])); ?>> <?php esc_html_e('White label mode', 'codeclinch-geo'); ?></label>
                    <p><label><?php esc_html_e('White-label plugin name', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[white_label_plugin_name]" value="<?php echo esc_attr($options['white_label_plugin_name']); ?>"></label></p>
                    <p><label><?php esc_html_e('White-label logo URL', 'codeclinch-geo'); ?><br><input class="regular-text" type="url" name="codeclinch_geo_options[white_label_logo_url]" value="<?php echo esc_attr($options['white_label_logo_url']); ?>"></label></p>
                    <p><label><?php esc_html_e('Footer attribution label', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[branding_label]" value="<?php echo esc_attr($options['branding_label']); ?>"></label></p>
                    <p><label><?php esc_html_e('Footer attribution URL', 'codeclinch-geo'); ?><br><input class="regular-text" type="url" name="codeclinch_geo_options[branding_url]" value="<?php echo esc_attr($options['branding_url']); ?>"></label></p>
                    <p><label><?php esc_html_e('Contact URL', 'codeclinch-geo'); ?><br><input class="regular-text" type="url" name="codeclinch_geo_options[contact_url]" value="<?php echo esc_attr($options['contact_url']); ?>"></label></p>
                    <p><label><?php esc_html_e('Contact CTA label', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[contact_cta_label]" value="<?php echo esc_attr($options['contact_cta_label']); ?>"></label></p>
                    <p><label><?php esc_html_e('Service CTA URL', 'codeclinch-geo'); ?><br><input class="regular-text" type="url" name="codeclinch_geo_options[service_cta_url]" value="<?php echo esc_attr($options['service_cta_url']); ?>"></label></p>
                    <p><label><?php esc_html_e('Service CTA label', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[service_cta_label]" value="<?php echo esc_attr($options['service_cta_label']); ?>"></label></p>
                    <p><label><?php esc_html_e('Support email', 'codeclinch-geo'); ?><br><input class="regular-text" type="email" name="codeclinch_geo_options[support_email]" value="<?php echo esc_attr($options['support_email']); ?>"></label></p>
                    <p><label><?php esc_html_e('Services box title', 'codeclinch-geo'); ?><br><input class="regular-text" type="text" name="codeclinch_geo_options[services_box_title]" value="<?php echo esc_attr($options['services_box_title']); ?>"></label></p>
                    <p><label><?php esc_html_e('Services box items', 'codeclinch-geo'); ?><br><textarea class="large-text code" rows="6" name="codeclinch_geo_options[services_box_items]"><?php echo esc_textarea($options['services_box_items']); ?></textarea></label></p>
                    <p class="description"><?php esc_html_e('White label mode hides Interdeft service/sidebar branding and uses the custom plugin name/logo where available.', 'codeclinch-geo'); ?></p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    private function render_module_placeholder($section) {
        $sections = $this->get_sections();
        $label = isset($sections[$section]) ? $sections[$section]['label'] : __('Module', 'codeclinch-geo');
        ?>
        <section class="codeclinch-geo-panel">
            <h2><?php echo esc_html($label); ?></h2>
            <p><?php esc_html_e('This module is part of the professional roadmap and has a dedicated admin area prepared.', 'codeclinch-geo'); ?></p>
            <ul class="codeclinch-geo-checklist">
                <li class="is-pending"><?php esc_html_e('Admin architecture ready', 'codeclinch-geo'); ?></li>
                <li class="is-pending"><?php esc_html_e('Settings and data model pending', 'codeclinch-geo'); ?></li>
                <li class="is-pending"><?php esc_html_e('Frontend/runtime feature pending', 'codeclinch-geo'); ?></li>
            </ul>
        </section>
        <?php
    }

    private function render_content_audit($options) {
        $missing_alt = $this->core_seo->get_missing_alt_images(20);
        ?>
        <section class="codeclinch-geo-panel">
            <h2><?php esc_html_e('Content Audit', 'codeclinch-geo'); ?></h2>
            <p><?php esc_html_e('Current audit focus: missing image alt text. More content checks will be added in the next scoring pass.', 'codeclinch-geo'); ?></p>

            <h3><?php esc_html_e('Images Missing Alt Text', 'codeclinch-geo'); ?></h3>
            <?php if (empty($missing_alt)) : ?>
                <p class="codeclinch-geo-muted"><?php esc_html_e('No missing image alt text found in the latest scan.', 'codeclinch-geo'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Image', 'codeclinch-geo'); ?></th>
                            <th><?php esc_html_e('Uploaded', 'codeclinch-geo'); ?></th>
                            <th><?php esc_html_e('Edit', 'codeclinch-geo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($missing_alt as $image) : ?>
                            <tr>
                                <td><?php echo esc_html(get_the_title($image)); ?></td>
                                <td><?php echo esc_html(get_the_date('', $image)); ?></td>
                                <td><a href="<?php echo esc_url(get_edit_post_link($image->ID)); ?>"><?php esc_html_e('Edit media', 'codeclinch-geo'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_404_logs() {
        $logs = $this->core_seo->get_404_logs();
        ?>
        <h3><?php esc_html_e('Recent 404 URLs', 'codeclinch-geo'); ?></h3>
        <?php if (empty($logs)) : ?>
            <p class="codeclinch-geo-muted"><?php esc_html_e('No 404 hits logged yet.', 'codeclinch-geo'); ?></p>
            <?php return; ?>
        <?php endif; ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('URL', 'codeclinch-geo'); ?></th>
                    <th><?php esc_html_e('Hits', 'codeclinch-geo'); ?></th>
                    <th><?php esc_html_e('Last seen', 'codeclinch-geo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($logs, 0, 10) as $log) : ?>
                    <tr>
                        <td><code><?php echo esc_html($log['url']); ?></code></td>
                        <td><?php echo esc_html($log['hits']); ?></td>
                        <td><?php echo esc_html($log['last_seen']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_ai_visibility_panels() {
        $score = $this->ai_visibility->calculate_geo_score();
        $logs = $this->ai_visibility->get_logs(15);
        ?>
        <div class="codeclinch-geo-dashboard-grid codeclinch-geo-after-form">
            <section class="codeclinch-geo-panel">
                <h2><?php esc_html_e('Discovery setup', 'codeclinch-geo'); ?></h2>
                <div class="codeclinch-geo-large-score"><?php echo esc_html($score['percentage']); ?>%</div>
                <ul class="codeclinch-geo-checklist">
                    <?php foreach ($score['checks'] as $label => $passed) : ?>
                        <li class="<?php echo esc_attr($passed ? 'is-done' : 'is-pending'); ?>"><?php echo esc_html($label); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="codeclinch-geo-panel">
                <h2><?php esc_html_e('AI Answer Preview', 'codeclinch-geo'); ?></h2>
                <pre class="codeclinch-geo-preview-box"><?php echo esc_html($this->ai_visibility->generate_answer_preview()); ?></pre>
            </section>
            <section class="codeclinch-geo-panel codeclinch-geo-panel-wide">
                <h2><?php esc_html_e('AI Crawler Logs', 'codeclinch-geo'); ?></h2>
                <?php if (empty($logs)) : ?>
                    <p class="codeclinch-geo-muted"><?php esc_html_e('No AI crawler visits logged yet.', 'codeclinch-geo'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Crawler', 'codeclinch-geo'); ?></th>
                                <th><?php esc_html_e('URL', 'codeclinch-geo'); ?></th>
                                <th><?php esc_html_e('Hits', 'codeclinch-geo'); ?></th>
                                <th><?php esc_html_e('Last seen', 'codeclinch-geo'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html($log['crawler'] . (!empty($log['verified']) ? ' · ' . __('IP verified', 'codeclinch-geo') : ' · ' . __('User agent only', 'codeclinch-geo'))); ?></td>
                                    <td><code><?php echo esc_html($log['url']); ?></code></td>
                                    <td><?php echo esc_html($log['hits']); ?></td>
                                    <td><?php echo esc_html($log['last_seen']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    private function render_woocommerce_audit() {
        ?>
        <section class="codeclinch-geo-panel codeclinch-geo-after-form">
            <h2><?php esc_html_e('WooCommerce Product Audit', 'codeclinch-geo'); ?></h2>
            <?php if (!$this->woocommerce->is_available()) : ?>
                <p class="codeclinch-geo-muted"><?php esc_html_e('WooCommerce product post type was not detected. This module will activate when WooCommerce is installed.', 'codeclinch-geo'); ?></p>
                <?php return; ?>
            <?php endif; ?>
            <?php $items = $this->woocommerce->get_product_audit(20); ?>
            <?php if (empty($items)) : ?>
                <p class="codeclinch-geo-muted"><?php esc_html_e('No major product SEO issues found in the current audit sample.', 'codeclinch-geo'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'codeclinch-geo'); ?></th>
                            <th><?php esc_html_e('Issues', 'codeclinch-geo'); ?></th>
                            <th><?php esc_html_e('Edit', 'codeclinch-geo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td><?php echo esc_html($item['title']); ?></td>
                                <td><?php echo esc_html(implode(', ', $item['issues'])); ?></td>
                                <td><a href="<?php echo esc_url($item['editUrl']); ?>"><?php esc_html_e('Edit product', 'codeclinch-geo'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_local_seo_panels() {
        $score = $this->local_seo->calculate_local_score();
        $ideas = $this->local_seo->get_city_page_ideas();
        ?>
        <div class="codeclinch-geo-dashboard-grid codeclinch-geo-after-form">
            <section class="codeclinch-geo-panel">
                <h2><?php esc_html_e('Local SEO Score', 'codeclinch-geo'); ?></h2>
                <div class="codeclinch-geo-large-score"><?php echo esc_html($score['percentage']); ?>%</div>
                <ul class="codeclinch-geo-checklist">
                    <?php foreach ($score['checks'] as $label => $passed) : ?>
                        <li class="<?php echo esc_attr($passed ? 'is-done' : 'is-pending'); ?>"><?php echo esc_html($label); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="codeclinch-geo-panel">
                <h2><?php esc_html_e('Location Sitemap', 'codeclinch-geo'); ?></h2>
                <p><?php esc_html_e('Use location URLs for city or service-area landing pages. The sitemap updates from the locations field above.', 'codeclinch-geo'); ?></p>
                <a class="button" href="<?php echo esc_url(home_url('/interdeft-location-sitemap.xml')); ?>" target="_blank"><?php esc_html_e('Open Location Sitemap', 'codeclinch-geo'); ?></a>
            </section>
            <section class="codeclinch-geo-panel codeclinch-geo-panel-wide">
                <h2><?php esc_html_e('City Landing Page Ideas', 'codeclinch-geo'); ?></h2>
                <?php if (empty($ideas)) : ?>
                    <p class="codeclinch-geo-muted"><?php esc_html_e('Add services and locations to generate city landing page ideas.', 'codeclinch-geo'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Title', 'codeclinch-geo'); ?></th>
                                <th><?php esc_html_e('Slug', 'codeclinch-geo'); ?></th>
                                <th><?php esc_html_e('Content Direction', 'codeclinch-geo'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($ideas, 0, 20) as $idea) : ?>
                                <tr>
                                    <td><?php echo esc_html($idea['title']); ?></td>
                                    <td><code><?php echo esc_html($idea['slug']); ?></code></td>
                                    <td><?php echo esc_html($idea['summary']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    private function render_reports() {
        ?>
        <section class="codeclinch-geo-panel">
            <h2><?php esc_html_e('Client-Ready Reports', 'codeclinch-geo'); ?></h2>
            <p><?php esc_html_e('Export SEO, GEO, technical, content, WooCommerce, and local SEO reports as HTML or Markdown.', 'codeclinch-geo'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Report', 'codeclinch-geo'); ?></th>
                        <th><?php esc_html_e('Markdown', 'codeclinch-geo'); ?></th>
                        <th><?php esc_html_e('HTML', 'codeclinch-geo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->reports->get_report_types() as $type => $label) : ?>
                        <tr>
                            <td><?php echo esc_html($label); ?></td>
                            <td><a class="button" href="<?php echo esc_url($this->reports->get_export_url($type, 'markdown')); ?>"><?php esc_html_e('Download Markdown', 'codeclinch-geo'); ?></a></td>
                            <td><a class="button" href="<?php echo esc_url($this->reports->get_export_url($type, 'html')); ?>"><?php esc_html_e('Download HTML', 'codeclinch-geo'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
    }

    private function render_setup_checklist($options) {
        $items = array(
            __('GEO endpoints enabled', 'codeclinch-geo') => !empty($options['enabled']),
            __('Site summary added', 'codeclinch-geo') => strlen(trim($options['site_summary'])) >= 50,
            __('SEO module enabled', 'codeclinch-geo') => !empty($options['seo_enabled']),
            __('Social tags enabled', 'codeclinch-geo') => !empty($options['seo_social_enabled']),
            __('AI provider configured', 'codeclinch-geo') => empty($options['ai_enabled']) || !empty($options['openai_api_key']) || !empty($options['openrouter_api_key']),
            __('Services added', 'codeclinch-geo') => !empty($this->generator->parse_services($options['services'])),
            __('Organization schema configured', 'codeclinch-geo') => !empty($options['organization_name']),
        );

        echo '<ul class="codeclinch-geo-checklist">';
        foreach ($items as $label => $passed) {
            printf('<li class="%s">%s</li>', esc_attr($passed ? 'is-done' : 'is-pending'), esc_html($label));
        }
        echo '</ul>';
    }

    private function get_workflow_cards() {
        return array(
            array(
                'title' => __('SEO Setup', 'codeclinch-geo'),
                'description' => __('Titles, meta, robots, social tags, and page defaults.', 'codeclinch-geo'),
                'url' => admin_url('admin.php?page=codeclinch-geo&section=seo-settings'),
            ),
            array(
                'title' => __('AI Visibility', 'codeclinch-geo'),
                'description' => __('llms.txt, crawler logs, AI summaries, and citation guidance.', 'codeclinch-geo'),
                'url' => admin_url('admin.php?page=codeclinch-geo&section=ai-visibility'),
            ),
            array(
                'title' => __('Schema', 'codeclinch-geo'),
                'description' => __('Organization, LocalBusiness, Product, FAQ, and page schema.', 'codeclinch-geo'),
                'url' => admin_url('admin.php?page=codeclinch-geo&section=schema'),
            ),
            array(
                'title' => __('Reports', 'codeclinch-geo'),
                'description' => __('Export client-ready SEO, GEO, technical, and ecommerce reports.', 'codeclinch-geo'),
                'url' => admin_url('admin.php?page=codeclinch-geo&section=reports'),
            ),
        );
    }

    private function render_audit_issues($options) {
        $issues = array();

        if (strlen(trim($options['seo_home_description'])) < 50) {
            $issues[] = __('Homepage meta description should be at least 50 characters.', 'codeclinch-geo');
        }

        if (empty($options['services'])) {
            $issues[] = __('Add AI-readable services for stronger GEO discovery.', 'codeclinch-geo');
        }

        if (!empty($options['ai_enabled']) && empty($options['openai_api_key']) && empty($options['openrouter_api_key'])) {
            $issues[] = __('AI is enabled but no provider API key is saved.', 'codeclinch-geo');
        }

        if (empty($issues)) {
            echo '<p class="codeclinch-geo-muted">' . esc_html__('No major setup issues found in the current configuration.', 'codeclinch-geo') . '</p>';
            return;
        }

        echo '<ul class="codeclinch-geo-issues">';
        foreach ($issues as $issue) {
            printf('<li>%s</li>', esc_html($issue));
        }
        echo '</ul>';
    }

    private function render_enabled_modules($options) {
        $modules = array(
            __('SEO Meta Output', 'codeclinch-geo') => !empty($options['seo_enabled']),
            __('OpenGraph / Twitter Cards', 'codeclinch-geo') => !empty($options['seo_social_enabled']),
            __('GEO Discovery Files', 'codeclinch-geo') => !empty($options['enabled']),
            __('AI Writing Tools', 'codeclinch-geo') => !empty($options['ai_enabled']),
            __('Schema Output', 'codeclinch-geo') => !empty($options['schema_enabled']),
            __('XML Sitemaps', 'codeclinch-geo') => !empty($options['core_sitemap_enabled']),
            __('Redirects Manager', 'codeclinch-geo') => !empty($options['redirects_enabled']),
            __('404 Monitor', 'codeclinch-geo') => !empty($options['monitor_404_enabled']),
            __('AI Crawler Logs', 'codeclinch-geo') => !empty($options['ai_crawler_logs_enabled']),
            __('WooCommerce SEO', 'codeclinch-geo') => !empty($options['woocommerce_seo_enabled']),
            __('Local SEO', 'codeclinch-geo') => !empty($options['local_seo_enabled']),
            __('Footer Branding', 'codeclinch-geo') => !empty($options['branding_enabled']),
            __('Admin Branding', 'codeclinch-geo') => !empty($options['admin_branding_enabled']),
        );

        echo '<div class="codeclinch-geo-module-grid">';
        foreach ($modules as $label => $enabled) {
            printf(
                '<div class="codeclinch-geo-module %s"><strong>%s</strong><span>%s</span></div>',
                esc_attr($enabled ? 'is-on' : 'is-off'),
                esc_html($label),
                esc_html($enabled ? __('Enabled', 'codeclinch-geo') : __('Disabled', 'codeclinch-geo'))
            );
        }
        echo '</div>';
    }

    private function calculate_seo_score($options) {
        $checks = array(
            !empty($options['seo_enabled']),
            !empty($options['seo_title_template']),
            strlen(trim($options['seo_home_description'])) >= 50,
            !empty($options['seo_social_enabled']),
            !empty($options['organization_name']),
        );

        return (int) round((count(array_filter($checks)) / count($checks)) * 100);
    }

    private function count_enabled_modules($options) {
        $modules = array(
            !empty($options['seo_enabled']),
            !empty($options['seo_social_enabled']),
            !empty($options['enabled']),
            !empty($options['ai_enabled']),
            !empty($options['schema_enabled']),
            !empty($options['core_sitemap_enabled']),
            !empty($options['redirects_enabled']),
            !empty($options['monitor_404_enabled']),
            !empty($options['ai_crawler_logs_enabled']),
            !empty($options['woocommerce_seo_enabled']),
            !empty($options['local_seo_enabled']),
            !empty($options['branding_enabled']),
            !empty($options['admin_branding_enabled']),
        );

        return count(array_filter($modules));
    }

    private function get_admin_title($options) {
        if (!empty($options['white_label_enabled']) && !empty($options['white_label_plugin_name'])) {
            return $options['white_label_plugin_name'];
        }

        return __('Interdeft SEO & AI Search', 'codeclinch-geo');
    }

    private function render_ai_tools_card($options) {
        ?>
        <div class="codeclinch-geo-card">
            <h2><?php esc_html_e('AI Tools', 'codeclinch-geo'); ?></h2>
            <p><?php esc_html_e('Use OpenAI direct or OpenRouter to generate SEO/GEO drafts.', 'codeclinch-geo'); ?></p>
            <button type="button" class="button button-secondary" data-codeclinch-geo-action="test-ai"><?php esc_html_e('Test AI Connection', 'codeclinch-geo'); ?></button>
            <button type="button" class="button button-primary" data-codeclinch-geo-action="generate-ai"><?php esc_html_e('Generate Suggestions', 'codeclinch-geo'); ?></button>
            <pre class="codeclinch-geo-ai-output" aria-live="polite"></pre>
        </div>
        <?php
    }

    private function render_codeclinch_card($options) {
        if (empty($options['admin_branding_enabled']) || !empty($options['white_label_enabled'])) {
            return;
        }
        ?>
        <div class="codeclinch-geo-card codeclinch-geo-brand-card">
            <h2><?php esc_html_e('Need SEO/GEO Help?', 'codeclinch-geo'); ?></h2>
            <p><?php esc_html_e('Interdeft can help with setup, audits, schema, WooCommerce SEO, and AI search visibility.', 'codeclinch-geo'); ?></p>
            <a class="button button-primary" href="<?php echo esc_url($options['contact_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['contact_cta_label']); ?></a>
            <?php if (!empty($options['support_email'])) : ?>
                <p class="codeclinch-geo-muted"><?php echo esc_html($options['support_email']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_services_card($options) {
        if (empty($options['admin_branding_enabled']) || !empty($options['white_label_enabled'])) {
            return;
        }
        ?>
        <div class="codeclinch-geo-card">
            <h2><?php echo esc_html($options['services_box_title']); ?></h2>
            <ul>
                <?php foreach ($this->generator->parse_lines($options['services_box_items']) as $service_item) : ?>
                    <li><?php echo esc_html($service_item); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    private function render_custom_requirement_card($options) {
        if (empty($options['admin_branding_enabled']) || !empty($options['white_label_enabled'])) {
            return;
        }
        ?>
        <div class="codeclinch-geo-card">
            <h2><?php esc_html_e('Custom Requirement?', 'codeclinch-geo'); ?></h2>
            <p><?php esc_html_e('For client-specific SEO/GEO workflows, request a custom implementation.', 'codeclinch-geo'); ?></p>
            <a class="button" href="<?php echo esc_url($options['service_cta_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['service_cta_label']); ?></a>
        </div>
        <?php
    }

    public function filter_admin_footer_text($text) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page check for footer text.
        if (empty($_GET['page']) || false === strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'codeclinch-geo')) {
            return $text;
        }

        $options = $this->generator->get_options();
        if (empty($options['admin_footer_branding_enabled']) || !empty($options['white_label_enabled'])) {
            return $text;
        }

        return sprintf(
            '%s <a href="%s" target="_blank" rel="noopener noreferrer">Interdeft</a>.',
            esc_html__('SEO and GEO workspace powered by', 'codeclinch-geo'),
            esc_url($options['branding_url'] ? $options['branding_url'] : 'https://interdeft.com')
        );
    }

    private function sanitize_secret($input, $option_key) {
        if (!empty($input['clear_' . $option_key])) {
            return '';
        }
        $value = sanitize_text_field($input[$option_key] ?? '');
        $options = $this->generator->get_options();
        return '' === $value ? $options[$option_key] : $value;
    }

    public function output_storage_notice() {
        if (!current_user_can('manage_options') || CODECLINCH_GEO_VERSION === get_option('codeclinch_geo_version')) {
            return;
        }
        echo '<div class="notice notice-error"><p>' . esc_html__('Interdeft could not install its activity table. Check database table creation permissions and reopen the admin dashboard.', 'codeclinch-geo') . '</p></div>';
    }

    private function get_schema_types($include_business = false) {
        $types = array(
            'auto',
            'Article',
            'BlogPosting',
            'WebPage',
            'Product',
            'Service',
            'FAQPage',
            'Review',
            'AggregateRating',
            'HowTo',
            'VideoObject',
            'Event',
            'JobPosting',
            'Course',
        );

        if ($include_business) {
            array_unshift($types, 'WebSite', 'LocalBusiness', 'Organization');
            $types[] = 'BreadcrumbList';
            $types[] = 'Review';
            $types[] = 'AggregateRating';
        }

        return $types;
    }

    private function verify_ajax_request() {
        check_ajax_referer('codeclinch_geo_ai', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to use AI tools.', 'codeclinch-geo')), 403);
        }
    }
}
