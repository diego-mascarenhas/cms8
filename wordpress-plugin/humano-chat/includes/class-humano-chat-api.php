<?php

/**
 * Calls the Humano assistant chat API. Token and base URL are used only server-side.
 */

if (! defined('ABSPATH')) {
    exit;
}

class Humano_Chat_Api
{
    public static function send_message(string $message, ?string $prompt_key = null): array
    {
        $base_url = get_option('humano_chat_base_url', '');
        $token = get_option('humano_chat_api_token', '');

        $base_url = rtrim($base_url, '/');
        if ($base_url === '' || $token === '') {
            return [
                'success' => false,
                'message' => __('Humano Chat is not configured. Add Base URL and API Token in Settings → Humano Chat.', 'humano-chat'),
            ];
        }

        $url = $base_url . '/api/team/assistant/chat';
        $body = [
            'message' => $message,
        ];
        if ($prompt_key !== null && $prompt_key !== '') {
            $body['prompt_key'] = $prompt_key;
        }

        $ssl_verify = get_option('humano_chat_ssl_verify', '1') === '1';

        $response = wp_remote_post($url, [
            'timeout'  => 60,
            'sslverify' => $ssl_verify,
            'headers'  => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => __('Network error: ', 'humano-chat') . $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $data = json_decode($body_raw, true);

        if ($code === 401) {
            return [
                'success' => false,
                'message' => __('Invalid or expired API token. Check your token in Humano (Team Settings → API Tokens) and in Settings → Humano Chat.', 'humano-chat'),
            ];
        }

        if ($code === 429) {
            return [
                'success' => false,
                'message' => __('Too many requests. Please wait a moment and try again.', 'humano-chat'),
            ];
        }

        if ($code >= 500) {
            return [
                'success' => false,
                'message' => __('Humano service is temporarily unavailable. Try again later.', 'humano-chat'),
            ];
        }

        if ($code !== 200 || ! is_array($data)) {
            $msg = isset($data['message']) ? $data['message'] : __('Unexpected response from Humano.', 'humano-chat');
            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        if (empty($data['success']) || ! isset($data['response'])) {
            return [
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : __('Invalid response from Humano.', 'humano-chat'),
            ];
        }

        return [
            'success'    => true,
            'response'   => $data['response'],
            'routed_to'  => $data['routed_to'] ?? null,
        ];
    }
}
