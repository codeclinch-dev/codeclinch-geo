<?php
/**
 * Client-ready reports.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_Reports {
    private $generator;

    private $ai_visibility;

    private $woocommerce;

    private $local_seo;

    public function __construct(CodeClinch_GEO_Generator $generator, CodeClinch_GEO_AI_Visibility $ai_visibility, CodeClinch_GEO_WooCommerce $woocommerce, CodeClinch_GEO_Local_SEO $local_seo) {
        $this->generator = $generator;
        $this->ai_visibility = $ai_visibility;
        $this->woocommerce = $woocommerce;
        $this->local_seo = $local_seo;

        add_action('admin_post_codeclinch_geo_export_report', array($this, 'export_report'));
    }

    public function export_report() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export reports.', 'codeclinch-geo'));
        }

        check_admin_referer('codeclinch_geo_export_report');

        $type = isset($_GET['report']) ? sanitize_key(wp_unslash($_GET['report'])) : 'seo';
        $format = isset($_GET['format']) ? sanitize_key(wp_unslash($_GET['format'])) : 'markdown';
        $content = 'html' === $format ? $this->generate_html_report($type) : $this->generate_markdown_report($type);
        $extension = 'html' === $format ? 'html' : 'md';

        header('Content-Type: ' . ('html' === $format ? 'text/html' : 'text/markdown') . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="codeclinch-' . $type . '-report.' . $extension . '"');
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function get_report_types() {
        return array(
            'seo' => __('SEO Report', 'codeclinch-geo'),
            'geo' => __('GEO Report', 'codeclinch-geo'),
            'technical' => __('Technical Audit', 'codeclinch-geo'),
            'content' => __('Content Audit', 'codeclinch-geo'),
            'woocommerce' => __('WooCommerce SEO Report', 'codeclinch-geo'),
            'local' => __('Local SEO Report', 'codeclinch-geo'),
        );
    }

    public function get_export_url($type, $format) {
        return wp_nonce_url(
            admin_url('admin-post.php?action=codeclinch_geo_export_report&report=' . sanitize_key($type) . '&format=' . sanitize_key($format)),
            'codeclinch_geo_export_report'
        );
    }

    public function generate_markdown_report($type) {
        $data = $this->get_report_data($type);
        $lines = array(
            '# ' . $data['title'],
            '',
            '**Site:** ' . get_bloginfo('name'),
            '**URL:** ' . home_url('/'),
            '**Generated:** ' . current_time('mysql'),
            '',
            '## Summary',
            '',
            $data['summary'],
            '',
            '## Scores',
            '',
        );

        foreach ($data['scores'] as $label => $value) {
            $lines[] = '- **' . $label . ':** ' . $value;
        }

        $lines[] = '';
        $lines[] = '## Findings';
        $lines[] = '';

        foreach ($data['findings'] as $finding) {
            $lines[] = '- ' . $finding;
        }

        $lines[] = '';
        $lines[] = '## Recommended Next Steps';
        $lines[] = '';

        foreach ($data['nextSteps'] as $step) {
            $lines[] = '- ' . $step;
        }

        return implode("\n", $lines) . "\n";
    }

    public function generate_html_report($type) {
        $markdown = $this->generate_markdown_report($type);
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Interdeft Report</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;max-width:900px;margin:40px auto;line-height:1.55;color:#1d2327}h1,h2{color:#0f172a}li{margin:6px 0}.box{padding:18px;border:1px solid #dcdcde;border-radius:8px;background:#fff}</style>';
        $html .= '</head><body><div class="box">';

        foreach (explode("\n", $markdown) as $line) {
            if (0 === strpos($line, '# ')) {
                $html .= '<h1>' . esc_html(substr($line, 2)) . '</h1>';
            } elseif (0 === strpos($line, '## ')) {
                $html .= '<h2>' . esc_html(substr($line, 3)) . '</h2>';
            } elseif (0 === strpos($line, '- ')) {
                $html .= '<p>&bull; ' . esc_html(substr($line, 2)) . '</p>';
            } elseif ('' === trim($line)) {
                continue;
            } else {
                $html .= '<p>' . esc_html($line) . '</p>';
            }
        }

        return $html . '</div></body></html>';
    }

    private function get_report_data($type) {
        $options = $this->generator->get_options();
        $geo_score = $this->ai_visibility->calculate_geo_score();
        $site_score = $this->generator->calculate_score();
        $local_score = $this->local_seo->calculate_local_score();
        $product_issues = $this->woocommerce->get_product_audit(10);

        $base = array(
            'title' => $this->get_report_types()[$type] ?? __('Interdeft Report', 'codeclinch-geo'),
            'summary' => __('This report summarizes the current Interdeft SEO & AI Search configuration and priority improvements.', 'codeclinch-geo'),
            'scores' => array(
                'GEO Score' => $geo_score['percentage'] . '%',
                'Setup Score' => $site_score['percentage'] . '%',
                'Local SEO Score' => $local_score['percentage'] . '%',
            ),
            'findings' => array(),
            'nextSteps' => array(),
        );

        if (empty($options['seo_home_description'])) {
            $base['findings'][] = __('Homepage meta description is missing or weak.', 'codeclinch-geo');
        }
        if (empty($options['geo_proof_points'])) {
            $base['findings'][] = __('Proof points are not configured for AI answer confidence.', 'codeclinch-geo');
        }
        if (empty($options['schema_enabled'])) {
            $base['findings'][] = __('Schema output is disabled.', 'codeclinch-geo');
        }
        if (!empty($product_issues)) {
            $base['findings'][] = sprintf(
                /* translators: %d: number of products */
                __('WooCommerce audit found issues on %d sampled products.', 'codeclinch-geo'),
                count($product_issues)
            );
        }

        if ('geo' === $type) {
            $base['summary'] = __('GEO readiness depends on discovery files, citation-ready content, proof points, business facts, and AI crawler visibility.', 'codeclinch-geo');
            foreach ($geo_score['checks'] as $label => $passed) {
                if (!$passed) {
                    $base['findings'][] = $label;
                }
            }
        }

        if ('local' === $type) {
            $base['summary'] = __('Local SEO readiness depends on complete NAP details, service areas, opening hours, map links, and city landing pages.', 'codeclinch-geo');
            foreach ($local_score['checks'] as $label => $passed) {
                if (!$passed) {
                    $base['findings'][] = $label;
                }
            }
        }

        if ('woocommerce' === $type && !empty($product_issues)) {
            foreach ($product_issues as $item) {
                $base['findings'][] = $item['title'] . ': ' . implode(', ', $item['issues']);
            }
        }

        if (empty($base['findings'])) {
            $base['findings'][] = __('No major issues found in the current report scope.', 'codeclinch-geo');
        }

        $base['nextSteps'] = array(
            __('Complete missing global settings and rerun the dashboard checks.', 'codeclinch-geo'),
            __('Improve high-value pages with AI summaries, FAQs, proof points, and citation-ready answers.', 'codeclinch-geo'),
            __('Validate generated schema with Google Rich Results Test before production rollout.', 'codeclinch-geo'),
            __('Review reports monthly after content or product updates.', 'codeclinch-geo'),
        );

        return $base;
    }
}
