<?php
/**
 * Post/page SEO meta box.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_Post_Meta {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta'));
    }

    public function add_meta_box() {
        $post_types = get_post_types(array('public' => true), 'names');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'codeclinch-geo-seo',
                __('Interdeft SEO / GEO', 'codeclinch-geo'),
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box($post) {
        wp_nonce_field('codeclinch_geo_save_meta', 'codeclinch_geo_meta_nonce');

        $meta = $this->get_post_meta_values($post->ID);
        $score = $this->calculate_scores($post, $meta);
        $preview_title = $meta['seo_title'] ? $meta['seo_title'] : get_the_title($post);
        $preview_description = $meta['meta_description'] ? $meta['meta_description'] : wp_trim_words(wp_strip_all_tags($post->post_content), 26, '');
        $preview_url = $meta['canonical'] ? $meta['canonical'] : get_permalink($post);
        ?>
        <div class="codeclinch-geo-editor-panel">
            <div class="codeclinch-geo-editor-scores">
                <div>
                    <strong><?php esc_html_e('SEO Score', 'codeclinch-geo'); ?></strong>
                    <span><?php echo esc_html($score['seo']); ?>/100</span>
                </div>
                <div>
                    <strong><?php esc_html_e('GEO Score', 'codeclinch-geo'); ?></strong>
                    <span><?php echo esc_html($score['geo']); ?>/100</span>
                </div>
            </div>

            <div class="codeclinch-geo-editor-layout">
                <div class="codeclinch-geo-editor-main">
                    <section class="codeclinch-geo-editor-section">
                        <h3><?php esc_html_e('SEO Preview', 'codeclinch-geo'); ?></h3>
                        <div class="codeclinch-geo-serp-preview">
                            <strong><?php echo esc_html($preview_title); ?></strong>
                            <span><?php echo esc_url($preview_url); ?></span>
                            <p><?php echo esc_html($preview_description); ?></p>
                        </div>
                    </section>

                    <section class="codeclinch-geo-editor-section">
                        <h3><?php esc_html_e('SEO Fields', 'codeclinch-geo'); ?></h3>
                        <div class="codeclinch-geo-meta-grid">
                            <p>
                                <label for="codeclinch_geo_focus_keyword"><strong><?php esc_html_e('Focus Keyword', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_focus_keyword" name="codeclinch_geo_focus_keyword" type="text" value="<?php echo esc_attr($meta['focus_keyword']); ?>">
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_type"><strong><?php esc_html_e('Schema Type', 'codeclinch-geo'); ?></strong></label>
                                <select class="widefat" id="codeclinch_geo_schema_type" name="codeclinch_geo_schema_type">
                                    <?php foreach ($this->get_schema_types() as $type) : ?>
                                        <option value="<?php echo esc_attr($type); ?>" <?php selected($meta['schema_type'] ? $meta['schema_type'] : 'Article', $type); ?>><?php echo esc_html($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                            <p class="codeclinch-geo-field-wide">
                                <label for="codeclinch_geo_seo_title"><strong><?php esc_html_e('SEO Title', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_seo_title" name="codeclinch_geo_seo_title" type="text" value="<?php echo esc_attr($meta['seo_title']); ?>" placeholder="%title% | %sitename%">
                                <span class="codeclinch-geo-counter"><?php echo esc_html(strlen($meta['seo_title'])); ?> <?php esc_html_e('characters', 'codeclinch-geo'); ?></span>
                            </p>
                            <p class="codeclinch-geo-field-wide">
                                <label for="codeclinch_geo_meta_description"><strong><?php esc_html_e('Meta Description', 'codeclinch-geo'); ?></strong></label>
                                <textarea class="widefat" id="codeclinch_geo_meta_description" name="codeclinch_geo_meta_description" rows="3"><?php echo esc_textarea($meta['meta_description']); ?></textarea>
                                <span class="codeclinch-geo-counter"><?php echo esc_html(strlen($meta['meta_description'])); ?> <?php esc_html_e('characters', 'codeclinch-geo'); ?></span>
                            </p>
                            <p>
                                <label for="codeclinch_geo_canonical"><strong><?php esc_html_e('Canonical URL', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_canonical" name="codeclinch_geo_canonical" type="url" value="<?php echo esc_attr($meta['canonical']); ?>">
                            </p>
                            <p>
                                <label><strong><?php esc_html_e('Robots', 'codeclinch-geo'); ?></strong></label>
                                <span class="codeclinch-geo-inline-controls">
                                    <select name="codeclinch_geo_robots_index">
                                        <option value="index" <?php selected($meta['robots_index'] ? $meta['robots_index'] : 'index', 'index'); ?>>index</option>
                                        <option value="noindex" <?php selected($meta['robots_index'], 'noindex'); ?>>noindex</option>
                                    </select>
                                    <select name="codeclinch_geo_robots_follow">
                                        <option value="follow" <?php selected($meta['robots_follow'] ? $meta['robots_follow'] : 'follow', 'follow'); ?>>follow</option>
                                        <option value="nofollow" <?php selected($meta['robots_follow'], 'nofollow'); ?>>nofollow</option>
                                    </select>
                                </span>
                            </p>
                        </div>
                    </section>

                    <section class="codeclinch-geo-editor-section">
                        <h3><?php esc_html_e('Social Preview', 'codeclinch-geo'); ?></h3>
                        <div class="codeclinch-geo-meta-grid">
                            <p>
                                <label for="codeclinch_geo_social_title"><strong><?php esc_html_e('Social Title', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_social_title" name="codeclinch_geo_social_title" type="text" value="<?php echo esc_attr($meta['social_title']); ?>">
                            </p>
                            <p>
                                <label for="codeclinch_geo_social_image"><strong><?php esc_html_e('Social Image URL', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_social_image" name="codeclinch_geo_social_image" type="url" value="<?php echo esc_attr($meta['social_image']); ?>">
                            </p>
                            <p class="codeclinch-geo-field-wide">
                                <label for="codeclinch_geo_social_description"><strong><?php esc_html_e('Social Description', 'codeclinch-geo'); ?></strong></label>
                                <textarea class="widefat" id="codeclinch_geo_social_description" name="codeclinch_geo_social_description" rows="2"><?php echo esc_textarea($meta['social_description']); ?></textarea>
                            </p>
                        </div>
                        <div class="codeclinch-geo-social-preview">
                            <div>
                                <strong><?php echo esc_html($meta['social_title'] ? $meta['social_title'] : $preview_title); ?></strong>
                                <p><?php echo esc_html($meta['social_description'] ? $meta['social_description'] : $preview_description); ?></p>
                                <span><?php echo esc_html(wp_parse_url(home_url('/'), PHP_URL_HOST)); ?></span>
                            </div>
                        </div>
                    </section>

                    <section class="codeclinch-geo-editor-section">
                        <h3><?php esc_html_e('AI Visibility', 'codeclinch-geo'); ?></h3>
                        <p>
                            <label>
                                <input type="checkbox" name="codeclinch_geo_ai_visible" value="1" <?php checked(!empty($meta['ai_visible'])); ?>>
                                <?php esc_html_e('Mark this page as important for AI discovery files and GEO audits', 'codeclinch-geo'); ?>
                            </label>
                        </p>
                        <p>
                            <label for="codeclinch_geo_ai_summary"><strong><?php esc_html_e('AI Summary', 'codeclinch-geo'); ?></strong></label>
                            <textarea class="widefat" id="codeclinch_geo_ai_summary" name="codeclinch_geo_ai_summary" rows="4"><?php echo esc_textarea($meta['ai_summary']); ?></textarea>
                        </p>
                        <p>
                            <label for="codeclinch_geo_citation_answer"><strong><?php esc_html_e('Citation-Ready Answer', 'codeclinch-geo'); ?></strong></label>
                            <textarea class="widefat" id="codeclinch_geo_citation_answer" name="codeclinch_geo_citation_answer" rows="4"><?php echo esc_textarea($meta['citation_answer']); ?></textarea>
                        </p>
                        <p>
                            <label for="codeclinch_geo_faq"><strong><?php esc_html_e('FAQ Content', 'codeclinch-geo'); ?></strong></label>
                            <textarea class="widefat code" id="codeclinch_geo_faq" name="codeclinch_geo_faq" rows="5" placeholder="<?php esc_attr_e('Question | Answer', 'codeclinch-geo'); ?>"><?php echo esc_textarea($meta['faq']); ?></textarea>
                        </p>
                    </section>

                    <section class="codeclinch-geo-editor-section">
                        <h3><?php esc_html_e('Schema Overrides', 'codeclinch-geo'); ?></h3>
                        <p>
                            <label>
                                <input type="checkbox" name="codeclinch_geo_schema_disabled" value="1" <?php checked(!empty($meta['schema_disabled'])); ?>>
                                <?php esc_html_e('Disable schema output for this page', 'codeclinch-geo'); ?>
                            </label>
                        </p>
                        <div class="codeclinch-geo-meta-grid">
                            <p>
                                <label for="codeclinch_geo_schema_price"><strong><?php esc_html_e('Product Price', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_schema_price" name="codeclinch_geo_schema_price" type="text" value="<?php echo esc_attr($meta['schema_price']); ?>">
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_currency"><strong><?php esc_html_e('Currency', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_schema_currency" name="codeclinch_geo_schema_currency" type="text" value="<?php echo esc_attr($meta['schema_currency']); ?>" placeholder="USD">
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_sku"><strong><?php esc_html_e('SKU', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_schema_sku" name="codeclinch_geo_schema_sku" type="text" value="<?php echo esc_attr($meta['schema_sku']); ?>">
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_area_served"><strong><?php esc_html_e('Service Areas', 'codeclinch-geo'); ?></strong></label>
                                <textarea class="widefat code" id="codeclinch_geo_schema_area_served" name="codeclinch_geo_schema_area_served" rows="2"><?php echo esc_textarea($meta['schema_area_served']); ?></textarea>
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_rating_value"><strong><?php esc_html_e('Rating Value', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_schema_rating_value" name="codeclinch_geo_schema_rating_value" type="text" value="<?php echo esc_attr($meta['schema_rating_value']); ?>" placeholder="4.8">
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_rating_count"><strong><?php esc_html_e('Rating Count', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_schema_rating_count" name="codeclinch_geo_schema_rating_count" type="number" min="0" value="<?php echo esc_attr($meta['schema_rating_count']); ?>">
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_video_url"><strong><?php esc_html_e('Video URL', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_schema_video_url" name="codeclinch_geo_schema_video_url" type="url" value="<?php echo esc_attr($meta['schema_video_url']); ?>">
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_event_date"><strong><?php esc_html_e('Event Date', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_schema_event_date" name="codeclinch_geo_schema_event_date" type="text" value="<?php echo esc_attr($meta['schema_event_date']); ?>" placeholder="2026-07-01T10:00:00+05:30">
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_location"><strong><?php esc_html_e('Location', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_schema_location" name="codeclinch_geo_schema_location" type="text" value="<?php echo esc_attr($meta['schema_location']); ?>">
                            </p>
                            <p>
                                <label for="codeclinch_geo_schema_course_provider"><strong><?php esc_html_e('Course Provider', 'codeclinch-geo'); ?></strong></label>
                                <input class="widefat" id="codeclinch_geo_schema_course_provider" name="codeclinch_geo_schema_course_provider" type="text" value="<?php echo esc_attr($meta['schema_course_provider']); ?>">
                            </p>
                            <p class="codeclinch-geo-field-wide">
                                <label for="codeclinch_geo_schema_steps"><strong><?php esc_html_e('HowTo Steps', 'codeclinch-geo'); ?></strong></label>
                                <textarea class="widefat code" id="codeclinch_geo_schema_steps" name="codeclinch_geo_schema_steps" rows="4" placeholder="<?php esc_attr_e('One step per line', 'codeclinch-geo'); ?>"><?php echo esc_textarea($meta['schema_steps']); ?></textarea>
                            </p>
                        </div>
                    </section>
                </div>

                <aside class="codeclinch-geo-editor-sidebar">
                    <section class="codeclinch-geo-editor-section">
                        <h3><?php esc_html_e('Suggestions', 'codeclinch-geo'); ?></h3>
                        <?php $this->render_checks($score['checks']); ?>
                    </section>
                </aside>
            </div>
        </div>
        <?php
    }

    public function save_meta($post_id) {
        if (!isset($_POST['codeclinch_geo_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['codeclinch_geo_meta_nonce'])), 'codeclinch_geo_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            '_codeclinch_geo_seo_title' => array('field' => 'codeclinch_geo_seo_title', 'type' => 'text'),
            '_codeclinch_geo_meta_description' => array('field' => 'codeclinch_geo_meta_description', 'type' => 'textarea'),
            '_codeclinch_geo_focus_keyword' => array('field' => 'codeclinch_geo_focus_keyword', 'type' => 'text'),
            '_codeclinch_geo_canonical' => array('field' => 'codeclinch_geo_canonical', 'type' => 'url'),
            '_codeclinch_geo_robots_index' => array('field' => 'codeclinch_geo_robots_index', 'type' => 'text'),
            '_codeclinch_geo_robots_follow' => array('field' => 'codeclinch_geo_robots_follow', 'type' => 'text'),
            '_codeclinch_geo_schema_type' => array('field' => 'codeclinch_geo_schema_type', 'type' => 'text'),
            '_codeclinch_geo_ai_summary' => array('field' => 'codeclinch_geo_ai_summary', 'type' => 'textarea'),
            '_codeclinch_geo_citation_answer' => array('field' => 'codeclinch_geo_citation_answer', 'type' => 'textarea'),
            '_codeclinch_geo_faq' => array('field' => 'codeclinch_geo_faq', 'type' => 'textarea'),
            '_codeclinch_geo_social_title' => array('field' => 'codeclinch_geo_social_title', 'type' => 'text'),
            '_codeclinch_geo_social_description' => array('field' => 'codeclinch_geo_social_description', 'type' => 'textarea'),
            '_codeclinch_geo_social_image' => array('field' => 'codeclinch_geo_social_image', 'type' => 'url'),
            '_codeclinch_geo_schema_price' => array('field' => 'codeclinch_geo_schema_price', 'type' => 'text'),
            '_codeclinch_geo_schema_currency' => array('field' => 'codeclinch_geo_schema_currency', 'type' => 'text'),
            '_codeclinch_geo_schema_sku' => array('field' => 'codeclinch_geo_schema_sku', 'type' => 'text'),
            '_codeclinch_geo_schema_area_served' => array('field' => 'codeclinch_geo_schema_area_served', 'type' => 'textarea'),
            '_codeclinch_geo_schema_rating_value' => array('field' => 'codeclinch_geo_schema_rating_value', 'type' => 'text'),
            '_codeclinch_geo_schema_rating_count' => array('field' => 'codeclinch_geo_schema_rating_count', 'type' => 'number'),
            '_codeclinch_geo_schema_video_url' => array('field' => 'codeclinch_geo_schema_video_url', 'type' => 'url'),
            '_codeclinch_geo_schema_event_date' => array('field' => 'codeclinch_geo_schema_event_date', 'type' => 'text'),
            '_codeclinch_geo_schema_location' => array('field' => 'codeclinch_geo_schema_location', 'type' => 'text'),
            '_codeclinch_geo_schema_course_provider' => array('field' => 'codeclinch_geo_schema_course_provider', 'type' => 'text'),
            '_codeclinch_geo_schema_steps' => array('field' => 'codeclinch_geo_schema_steps', 'type' => 'textarea'),
        );

        foreach ($fields as $meta_key => $config) {
            $field_key = $config['field'];
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Dynamic field is unslashed here and sanitized by type below before use.
            $value = isset($_POST[$field_key]) ? wp_unslash($_POST[$field_key]) : '';

            if ('url' === $config['type']) {
                $value = esc_url_raw($value);
            } elseif ('textarea' === $config['type']) {
                $value = sanitize_textarea_field($value);
            } elseif ('number' === $config['type']) {
                $value = absint($value);
            } else {
                $value = sanitize_text_field($value);
            }

            if ('' === $value) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        update_post_meta($post_id, '_codeclinch_geo_ai_visible', empty($_POST['codeclinch_geo_ai_visible']) ? 0 : 1);
        update_post_meta($post_id, '_codeclinch_geo_schema_disabled', empty($_POST['codeclinch_geo_schema_disabled']) ? 0 : 1);
    }

    private function get_post_meta_values($post_id) {
        return array(
            'seo_title' => get_post_meta($post_id, '_codeclinch_geo_seo_title', true),
            'meta_description' => get_post_meta($post_id, '_codeclinch_geo_meta_description', true),
            'focus_keyword' => get_post_meta($post_id, '_codeclinch_geo_focus_keyword', true),
            'canonical' => get_post_meta($post_id, '_codeclinch_geo_canonical', true),
            'robots_index' => get_post_meta($post_id, '_codeclinch_geo_robots_index', true),
            'robots_follow' => get_post_meta($post_id, '_codeclinch_geo_robots_follow', true),
            'schema_type' => get_post_meta($post_id, '_codeclinch_geo_schema_type', true),
            'ai_summary' => get_post_meta($post_id, '_codeclinch_geo_ai_summary', true),
            'citation_answer' => get_post_meta($post_id, '_codeclinch_geo_citation_answer', true),
            'faq' => get_post_meta($post_id, '_codeclinch_geo_faq', true),
            'ai_visible' => (int) get_post_meta($post_id, '_codeclinch_geo_ai_visible', true),
            'social_title' => get_post_meta($post_id, '_codeclinch_geo_social_title', true),
            'social_description' => get_post_meta($post_id, '_codeclinch_geo_social_description', true),
            'social_image' => get_post_meta($post_id, '_codeclinch_geo_social_image', true),
            'schema_disabled' => (int) get_post_meta($post_id, '_codeclinch_geo_schema_disabled', true),
            'schema_price' => get_post_meta($post_id, '_codeclinch_geo_schema_price', true),
            'schema_currency' => get_post_meta($post_id, '_codeclinch_geo_schema_currency', true),
            'schema_sku' => get_post_meta($post_id, '_codeclinch_geo_schema_sku', true),
            'schema_area_served' => get_post_meta($post_id, '_codeclinch_geo_schema_area_served', true),
            'schema_rating_value' => get_post_meta($post_id, '_codeclinch_geo_schema_rating_value', true),
            'schema_rating_count' => get_post_meta($post_id, '_codeclinch_geo_schema_rating_count', true),
            'schema_video_url' => get_post_meta($post_id, '_codeclinch_geo_schema_video_url', true),
            'schema_event_date' => get_post_meta($post_id, '_codeclinch_geo_schema_event_date', true),
            'schema_location' => get_post_meta($post_id, '_codeclinch_geo_schema_location', true),
            'schema_course_provider' => get_post_meta($post_id, '_codeclinch_geo_schema_course_provider', true),
            'schema_steps' => get_post_meta($post_id, '_codeclinch_geo_schema_steps', true),
        );
    }

    private function calculate_scores($post, $meta) {
        $content = wp_strip_all_tags((string) $post->post_content);
        $html = (string) $post->post_content;
        $focus = strtolower(trim($meta['focus_keyword']));
        $seo_title = $meta['seo_title'] ? $meta['seo_title'] : get_the_title($post);
        $description = $meta['meta_description'];

        $checks = array(
            array('group' => 'seo', 'passed' => strlen($seo_title) >= 30 && strlen($seo_title) <= 65, 'label' => __('Title length is 30-65 characters.', 'codeclinch-geo')),
            array('group' => 'seo', 'passed' => strlen($description) >= 120 && strlen($description) <= 160, 'label' => __('Meta description is 120-160 characters.', 'codeclinch-geo')),
            array('group' => 'seo', 'passed' => $focus && false !== strpos(strtolower($seo_title), $focus), 'label' => __('Focus keyword appears in SEO title.', 'codeclinch-geo')),
            array('group' => 'seo', 'passed' => $focus && false !== strpos(strtolower(get_the_title($post)), $focus), 'label' => __('Focus keyword appears in page H1/title.', 'codeclinch-geo')),
            array('group' => 'seo', 'passed' => preg_match('/href=["\']' . preg_quote(home_url('/'), '/') . '/i', $html) || preg_match('/href=["\']\//i', $html), 'label' => __('Internal link found in content.', 'codeclinch-geo')),
            array('group' => 'seo', 'passed' => preg_match('/href=["\']https?:\/\/(?!' . preg_quote(wp_parse_url(home_url('/'), PHP_URL_HOST), '/') . ')/i', $html), 'label' => __('External link found in content.', 'codeclinch-geo')),
            array('group' => 'seo', 'passed' => !$this->content_has_missing_alt($html), 'label' => __('Images have alt text.', 'codeclinch-geo')),
            array('group' => 'geo', 'passed' => !empty($meta['schema_type']), 'label' => __('Schema type selected.', 'codeclinch-geo')),
            array('group' => 'geo', 'passed' => strlen(trim($meta['faq'])) >= 20, 'label' => __('FAQ content added.', 'codeclinch-geo')),
            array('group' => 'geo', 'passed' => strlen(trim($meta['ai_summary'])) >= 50, 'label' => __('AI summary added.', 'codeclinch-geo')),
            array('group' => 'geo', 'passed' => strlen(trim($meta['citation_answer'])) >= 80, 'label' => __('Citation-ready answer added.', 'codeclinch-geo')),
            array('group' => 'geo', 'passed' => strlen($content) >= 400, 'label' => __('Page content has enough context for AI answers.', 'codeclinch-geo')),
        );

        return array(
            'seo' => $this->score_group($checks, 'seo'),
            'geo' => $this->score_group($checks, 'geo'),
            'checks' => $checks,
        );
    }

    private function score_group($checks, $group) {
        $group_checks = array_filter(
            $checks,
            static function ($check) use ($group) {
                return $group === $check['group'];
            }
        );

        if (empty($group_checks)) {
            return 0;
        }

        $passed = count(array_filter(wp_list_pluck($group_checks, 'passed')));
        return (int) round(($passed / count($group_checks)) * 100);
    }

    private function content_has_missing_alt($html) {
        if (!preg_match_all('/<img\b[^>]*>/i', $html, $matches)) {
            return false;
        }

        foreach ($matches[0] as $image) {
            if (!preg_match('/\salt=(["\']).*?\1/i', $image)) {
                return true;
            }
        }

        return false;
    }

    private function render_checks($checks) {
        echo '<ul class="codeclinch-geo-checklist codeclinch-geo-editor-checks">';
        foreach ($checks as $check) {
            printf(
                '<li class="%s"><span>%s</span><em>%s</em></li>',
                esc_attr($check['passed'] ? 'is-done' : 'is-pending'),
                esc_html($check['label']),
                esc_html('geo' === $check['group'] ? __('GEO', 'codeclinch-geo') : __('SEO', 'codeclinch-geo'))
            );
        }
        echo '</ul>';
    }

    private function get_schema_types() {
        return array(
            'Article',
            'BlogPosting',
            'WebPage',
            'Service',
            'Product',
            'FAQPage',
            'Review',
            'AggregateRating',
            'HowTo',
            'VideoObject',
            'Event',
            'Course',
            'JobPosting',
        );
    }
}
