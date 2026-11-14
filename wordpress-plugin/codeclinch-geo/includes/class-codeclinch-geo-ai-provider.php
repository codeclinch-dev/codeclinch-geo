<?php
/**
 * AI provider integration for OpenAI and OpenRouter.
 *
 * @package CodeClinch_GEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeClinch_GEO_AI_Provider {
    public function test_connection($options) {
        $prompt = 'Reply with exactly: connected';
        $result = $this->generate_text($options, $prompt, 20);

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'provider' => $this->get_provider($options),
            'model' => $this->get_model($options),
            'message' => trim($result),
        );
    }

    public function generate_seo_suggestions($options, $context) {
        $prompt = sprintf(
            "You are an SEO and GEO specialist. Generate concise WordPress optimization suggestions for this website.\n\nSite: %s\nURL: %s\nCurrent summary: %s\nServices: %s\n\nReturn JSON with keys: meta_title, meta_description, ai_summary, faq, citation_guidance.",
            get_bloginfo('name'),
            home_url('/'),
            isset($context['site_summary']) ? $context['site_summary'] : '',
            isset($context['services']) ? $context['services'] : ''
        );

        $result = $this->generate_text($options, $prompt, 700);
        if (is_wp_error($result)) {
            return $result;
        }
        $result = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($result));
        $draft = json_decode($result, true);
        if (!is_array($draft) || !isset($draft['meta_title'], $draft['meta_description'], $draft['ai_summary'])) {
            return new WP_Error('interdeft_geo_ai_invalid_draft', __('The provider did not return a usable draft. Try again or select another model.', 'codeclinch-geo'));
        }
        return $draft;
    }

    public function generate_text($options, $prompt, $max_tokens = 500) {
        if (empty($options['ai_enabled']) || empty($options['ai_consent'])) {
            return new WP_Error('codeclinch_geo_ai_disabled', __('AI features are disabled.', 'codeclinch-geo'));
        }

        $provider = $this->get_provider($options);
        $api_key = $this->get_api_key($options, $provider);

        if (!$api_key) {
            return new WP_Error('codeclinch_geo_missing_key', __('Missing API key for the selected AI provider.', 'codeclinch-geo'));
        }

        if (!$this->reserve_action((int) ($options['ai_usage_limit'] ?? 100))) {
            return new WP_Error('interdeft_geo_ai_limit', __('The monthly AI action limit has been reached, or AI requests are disabled by the configured limit.', 'codeclinch-geo'));
        }

        if ('openrouter' === $provider) {
            return $this->request_openrouter($api_key, $this->get_model($options), $prompt, $max_tokens);
        }

        return $this->request_openai($api_key, $this->get_model($options), $prompt, $max_tokens);
    }

    public function get_provider($options) {
        $provider = isset($options['ai_provider']) ? sanitize_key($options['ai_provider']) : 'openai';
        return in_array($provider, array('openai', 'openrouter'), true) ? $provider : 'openai';
    }

    public function get_model($options) {
        $provider = $this->get_provider($options);

        if ('openrouter' === $provider) {
            return !empty($options['openrouter_model']) ? sanitize_text_field($options['openrouter_model']) : 'openai/gpt-4o-mini';
        }

        return !empty($options['openai_model']) ? sanitize_text_field($options['openai_model']) : 'gpt-4o-mini';
    }

    public function mask_key($key) {
        return $key ? '********' : '';
    }

    private function reserve_action($limit) {
        if ($limit <= 0) {
            return false;
        }
        global $wpdb;
        $name = 'interdeft_geo_ai_usage_' . gmdate('Ym');
        add_option($name, '0', '', false);
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1
             WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
            $name, $limit
        ));
        wp_cache_delete($name, 'options');
        return 1 === $result;
    }

    private function get_api_key($options, $provider) {
        $option = 'openrouter' === $provider ? 'openrouter_api_key' : 'openai_api_key';
        $constant = 'INTERDEFT_GEO_' . strtoupper($option);
        return defined($constant) ? trim((string) constant($constant)) : trim((string) ($options[$option] ?? ''));
    }

    private function request_openai($api_key, $model, $prompt, $max_tokens) {
        $response = wp_safe_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'timeout' => 30,
                'redirection' => 0,
                'limit_response_size' => 262144,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(
                    array(
                        'model' => $model,
                        'messages' => array(
                            array(
                                'role' => 'system',
                                'content' => 'You are a concise SEO and GEO assistant for WordPress.',
                            ),
                            array(
                                'role' => 'user',
                                'content' => $prompt,
                            ),
                        ),
                        'max_completion_tokens' => $max_tokens,
                        'store' => false,
                    )
                ),
            )
        );

        return $this->parse_chat_response($response);
    }

    private function request_openrouter($api_key, $model, $prompt, $max_tokens) {
        $response = wp_safe_remote_post(
            'https://openrouter.ai/api/v1/chat/completions',
            array(
                'timeout' => 30,
                'redirection' => 0,
                'limit_response_size' => 262144,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => home_url('/'),
                    'X-Title' => get_bloginfo('name') . ' - Interdeft SEO & AI Search',
                ),
                'body' => wp_json_encode(
                    array(
                        'model' => $model,
                        'messages' => array(
                            array(
                                'role' => 'system',
                                'content' => 'You are a concise SEO and GEO assistant for WordPress.',
                            ),
                            array(
                                'role' => 'user',
                                'content' => $prompt,
                            ),
                        ),
                        'max_tokens' => $max_tokens,
                        'temperature' => 0.4,
                    )
                ),
            )
        );

        return $this->parse_chat_response($response);
    }

    private function parse_chat_response($response) {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            $message = sprintf(
                /* translators: %d: HTTP status code returned by the provider. */
                __('The AI provider returned HTTP %d. Check the selected model, key, and provider account limits.', 'codeclinch-geo'),
                $code
            );
            return new WP_Error('codeclinch_geo_ai_request_failed', $message, array('status' => $code));
        }

        if (empty($body['choices'][0]['message']['content'])) {
            return new WP_Error('codeclinch_geo_ai_empty_response', __('AI provider returned an empty response.', 'codeclinch-geo'));
        }

        return (string) $body['choices'][0]['message']['content'];
    }
}
