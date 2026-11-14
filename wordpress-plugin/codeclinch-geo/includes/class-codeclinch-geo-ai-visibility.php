<?php
/**
 * Advanced GEO / AI visibility features.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_AI_Visibility {
    private $generator;

    public function __construct(CodeClinch_GEO_Generator $generator) {
        $this->generator = $generator;

        add_action('template_redirect', array($this, 'log_ai_crawler_visit'), 1);
    }

    public function log_ai_crawler_visit() {
        if (is_admin()) {
            return;
        }

        $options = $this->generator->get_options();
        if (empty($options['ai_crawler_logs_enabled'])) {
            return;
        }

        $crawler = $this->detect_ai_crawler();
        if (!$crawler) {
            return;
        }

        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $ranges = get_option('interdeft_geo_crawler_ranges', array());
        $list = $ranges[$crawler] ?? array();
        $verified = ($list['updated_at'] ?? 0) >= time() - 7 * DAY_IN_SECONDS
            && self::ip_in_ranges($ip, $list['prefixes'] ?? array());
        CodeClinch_GEO_Activity::log('crawler', $crawler, $verified);
    }

    public static function crawler_policy_tokens() {
        return array(
            'OAI-SearchBot' => 'OpenAI search',
            'GPTBot' => 'OpenAI training',
            'Claude-SearchBot' => 'Claude search',
            'ClaudeBot' => 'Claude training',
            'Claude-User' => 'Claude user fetches',
            'Google-Extended' => 'Gemini training and grounding',
        );
    }

    public static function append_crawler_policy($robots, $settings) {
        $settings = (array) $settings;
        $agents = array();
        $managed = array();
        $wildcard = array();
        $has_rules = false;
        foreach (preg_split('/\R/', $robots) as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (preg_match('/^user-agent\s*:\s*(\S+)$/i', $line, $matches)) {
                if ($has_rules) {
                    $agents = array();
                    $has_rules = false;
                }
                $agents[] = strtolower($matches[1]);
                $managed[strtolower($matches[1])] = true;
            } elseif (preg_match('/^(allow|disallow)\s*:/i', $line)) {
                $has_rules = true;
                if (in_array('*', $agents, true)) {
                    $wildcard[] = $line;
                }
            }
        }

        $robots = rtrim($robots);
        foreach (self::crawler_policy_tokens() as $agent => $label) {
            $policy = $settings[$agent] ?? '';
            if (!isset($managed[strtolower($agent)]) && in_array($policy, array('inherit', 'block'), true)) {
                $rules = 'block' === $policy ? array('Disallow: /') : ($wildcard ?: array('Disallow:'));
                $robots .= "\n\nUser-agent: " . $agent . "\n" . implode("\n", $rules);
            }
        }
        return $robots . "\n";
    }

    public function detect_ai_crawler() {
        $ua = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
        foreach (array(
            'GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'Claude-User', 'Claude-SearchBot',
            'PerplexityBot', 'Perplexity-User', 'CCBot', 'Bytespider', 'YouBot', 'Meta-ExternalAgent',
        ) as $crawler) {
            if (preg_match('/(?<![a-z0-9_-])' . preg_quote($crawler, '/') . '(?![a-z0-9_-])/i', $ua)) {
                return $crawler;
            }
        }
        return '';
    }

    public static function refresh_verification_ranges() {
        $ranges = get_option('interdeft_geo_crawler_ranges', array());
        $urls = array(
            'OAI-SearchBot' => 'https://openai.com/searchbot.json',
            'GPTBot' => 'https://openai.com/gptbot.json',
            'ChatGPT-User' => 'https://openai.com/chatgpt-user.json',
        );
        foreach ($urls as $agent => $url) {
            $response = wp_safe_remote_get($url, array('timeout' => 5, 'limit_response_size' => 1048576));
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (200 !== wp_remote_retrieve_response_code($response) || empty($body['prefixes']) || !is_array($body['prefixes'])) {
                continue;
            }
            $prefixes = array();
            foreach ($body['prefixes'] as $entry) {
                $prefix = $entry['ipv4Prefix'] ?? $entry['ipv6Prefix'] ?? '';
                if (is_string($prefix) && strpos($prefix, '/') !== false) {
                    $prefixes[] = $prefix;
                }
            }
            if ($prefixes) {
                $ranges[$agent] = array('updated_at' => time(), 'prefixes' => $prefixes);
            }
        }
        update_option('interdeft_geo_crawler_ranges', $ranges, false);
    }

    private static function ip_in_ranges($address, $ranges) {
        $ip = filter_var($address, FILTER_VALIDATE_IP) ? inet_pton($address) : false;
        if (false === $ip) {
            return false;
        }
        foreach ($ranges as $range) {
            list($network, $bits) = explode('/', $range, 2);
            $network = filter_var($network, FILTER_VALIDATE_IP) ? inet_pton($network) : false;
            if (false === $network || strlen($network) !== strlen($ip) || !ctype_digit($bits)
                || (int) $bits <= 0 || (int) $bits > strlen($ip) * 8) {
                continue;
            }
            $bytes = intdiv((int) $bits, 8);
            $tail = (int) $bits % 8;
            if (substr($ip, 0, $bytes) !== substr($network, 0, $bytes)) {
                continue;
            }
            if (!$tail || ((ord($ip[$bytes]) ^ ord($network[$bytes])) >> (8 - $tail)) === 0) {
                return true;
            }
        }
        return false;
    }

    public function get_logs($limit = 20) {
        return CodeClinch_GEO_Activity::get('crawler', $limit);
    }

    public function calculate_geo_score() {
        $options = $this->generator->get_options();
        $checks = array(
            'Discovery files enabled' => !empty($options['enabled']),
            'AI crawler logging enabled' => !empty($options['ai_crawler_logs_enabled']),
            'Site summary added' => strlen(trim($options['site_summary'])) >= 50,
            'Citation guidance added' => strlen(trim($options['citation_guidance'])) >= 30,
            'Service summaries added' => !empty($this->generator->parse_services($options['services'])),
            'Proof points added' => !empty($this->generator->parse_lines($options['geo_proof_points'])),
            'Business facts added' => !empty($this->generator->parse_lines($options['geo_business_facts'])),
            'Pricing summary added' => strlen(trim($options['geo_pricing_summary'])) >= 20,
            'Location summary added' => strlen(trim($options['geo_location_summary'])) >= 20,
            'Important content discoverable' => !empty($this->generator->get_important_content()),
        );

        $passed = count(array_filter($checks));

        return array(
            'percentage' => (int) round(($passed / count($checks)) * 100),
            'checks' => $checks,
        );
    }

    public function generate_answer_preview() {
        $options = $this->generator->get_options();
        $services = $this->generator->parse_services($options['services']);
        $content = $this->generator->get_important_content();
        $proof_points = $this->generator->parse_lines($options['geo_proof_points']);

        $lines = array();
        $lines[] = get_bloginfo('name') . ' is ' . ($options['site_summary'] ? $options['site_summary'] : get_bloginfo('description'));

        if (!empty($services)) {
            $lines[] = 'Key services include ' . implode(', ', wp_list_pluck($services, 'name')) . '.';
        }

        if (!empty($proof_points)) {
            $lines[] = 'Proof points: ' . implode('; ', array_slice($proof_points, 0, 3)) . '.';
        }

        if (!empty($options['geo_pricing_summary'])) {
            $lines[] = 'Pricing: ' . $options['geo_pricing_summary'];
        }

        if (!empty($options['geo_location_summary'])) {
            $lines[] = 'Location: ' . $options['geo_location_summary'];
        }

        if (!empty($content)) {
            $lines[] = 'Useful source pages: ' . implode(', ', array_slice(wp_list_pluck($content, 'title'), 0, 5)) . '.';
        }

        return implode("\n\n", array_filter($lines));
    }
}
