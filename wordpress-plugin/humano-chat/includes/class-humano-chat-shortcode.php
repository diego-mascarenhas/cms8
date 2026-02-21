<?php

/**
 * Shortcode [humano_chat] and optional floating widget. Enqueues assets and outputs chat markup.
 */

if (! defined('ABSPATH')) {
    exit;
}

class Humano_Chat_Shortcode
{
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
        add_shortcode('humano_chat', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_footer', [$this, 'maybe_render_floating'], 5);
    }

    public function register_assets(): void
    {
        wp_register_style(
            'humano-chat',
            HUMANO_CHAT_PLUGIN_URL . 'assets/css/chat.css',
            [],
            HUMANO_CHAT_VERSION
        );
        wp_register_script(
            'humano-chat',
            HUMANO_CHAT_PLUGIN_URL . 'assets/js/chat.js',
            [],
            HUMANO_CHAT_VERSION,
            true
        );
    }

    private function enqueue_and_localize(bool $floating = false): void
    {
        wp_enqueue_style('humano-chat');
        wp_enqueue_script('humano-chat');
        wp_localize_script('humano-chat', 'humanoChat', [
            'ajaxUrl'   => Humano_Chat_Ajax::get_ajax_url(),
            'action'    => Humano_Chat_Ajax::get_action(),
            'nonce'     => wp_create_nonce('humano_chat_nonce'),
            'promptKey' => get_option('humano_chat_prompt_key', '') ?: '',
            'floating'  => $floating,
            'i18n'      => [
                'send'       => __('Send', 'humano-chat'),
                'placeholder' => __('Type your message...', 'humano-chat'),
                'thinking'   => __('Thinking...', 'humano-chat'),
                'error'     => __('Something went wrong. Try again.', 'humano-chat'),
                'open'      => __('Open chat', 'humano-chat'),
                'close'     => __('Close chat', 'humano-chat'),
            ],
        ]);
    }

    public function render_shortcode(array $atts): string
    {
        $this->enqueue_and_localize(false);

        ob_start();
        ?>
        <div class="humano-chat-wrap humano-chat-inline" id="humano-chat-root" data-floating="0">
            <div class="humano-chat-messages" role="log" aria-live="polite"></div>
            <div class="humano-chat-input-wrap">
                <textarea class="humano-chat-input" rows="2" placeholder="<?php echo esc_attr(__('Type your message...', 'humano-chat')); ?>" maxlength="16000"></textarea>
                <button type="button" class="humano-chat-send" aria-label="<?php esc_attr_e('Send', 'humano-chat'); ?>"><?php esc_html_e('Send', 'humano-chat'); ?></button>
            </div>
            <div class="humano-chat-status" aria-live="polite"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function maybe_render_floating(): void
    {
        if (get_option('humano_chat_floating', '0') !== '1') {
            return;
        }

        $this->enqueue_and_localize(true);
        ?>
        <div class="humano-chat-floating" id="humano-chat-floating">
            <button type="button" class="humano-chat-float-btn" aria-label="<?php esc_attr_e('Open chat', 'humano-chat'); ?>" aria-expanded="false">
                <span class="humano-chat-float-icon" aria-hidden="true">💬</span>
            </button>
            <div class="humano-chat-float-panel" hidden>
                <div class="humano-chat-wrap humano-chat-float-inner" data-floating="1">
                    <div class="humano-chat-messages" role="log" aria-live="polite"></div>
                    <div class="humano-chat-input-wrap">
                        <textarea class="humano-chat-input" rows="2" placeholder="<?php echo esc_attr(__('Type your message...', 'humano-chat')); ?>" maxlength="16000"></textarea>
                        <button type="button" class="humano-chat-send" aria-label="<?php esc_attr_e('Send', 'humano-chat'); ?>"><?php esc_html_e('Send', 'humano-chat'); ?></button>
                    </div>
                    <div class="humano-chat-status" aria-live="polite"></div>
                </div>
            </div>
        </div>
        <?php
    }
}
