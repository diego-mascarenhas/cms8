<?php

/**
 * AJAX endpoint: receives message from front-end, calls Humano API, returns JSON. Token never sent to browser.
 */

if (! defined('ABSPATH')) {
    exit;
}

class Humano_Chat_Ajax
{
    private const ACTION = 'humano_chat_send';

    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle_send']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handle_send']);
    }

    public function handle_send(): void
    {
        check_ajax_referer('humano_chat_nonce', 'nonce');

        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        if ($message === '') {
            wp_send_json_error(['message' => __('Message is required.', 'humano-chat')]);
            return;
        }

        $prompt_key = isset($_POST['prompt_key']) ? sanitize_text_field(wp_unslash($_POST['prompt_key'])) : null;
        if ($prompt_key === '') {
            $prompt_key = null;
        }

        $result = Humano_Chat_Api::send_message($message, $prompt_key);

        if (! empty($result['success'])) {
            wp_send_json_success([
                'response'  => $result['response'],
                'routed_to'  => $result['routed_to'] ?? null,
            ]);
        } else {
            wp_send_json_error(['message' => $result['message'] ?? __('Unknown error.', 'humano-chat')]);
        }
    }

    public static function get_ajax_url(): string
    {
        return admin_url('admin-ajax.php');
    }

    public static function get_action(): string
    {
        return self::ACTION;
    }
}
