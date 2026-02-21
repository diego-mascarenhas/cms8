<?php

/**
 * Plugin settings: Base URL, API token, floating widget option.
 */

if (! defined('ABSPATH')) {
    exit;
}

class Humano_Chat_Settings
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
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
    }

    public function add_menu(): void
    {
        add_options_page(
            __('Humano Chat', 'humano-chat'),
            __('Humano Chat', 'humano-chat'),
            'manage_options',
            'humano-chat',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('humano_chat', 'humano_chat_base_url', [
            'type'              => 'string',
            'sanitize_callback' => function ($value) {
                return sanitize_text_field($value);
            },
        ]);
        register_setting('humano_chat', 'humano_chat_api_token', [
            'type'              => 'string',
            'sanitize_callback' => function ($value) {
                return sanitize_text_field($value);
            },
        ]);
        register_setting('humano_chat', 'humano_chat_floating', [
            'type'              => 'string',
            'sanitize_callback' => function ($value) {
                return in_array($value, ['1', '0'], true) ? $value : '0';
            },
        ]);
        register_setting('humano_chat', 'humano_chat_prompt_key', [
            'type'              => 'string',
            'sanitize_callback' => function ($value) {
                return sanitize_text_field($value);
            },
        ]);
        register_setting('humano_chat', 'humano_chat_ssl_verify', [
            'type'              => 'string',
            'sanitize_callback' => function ($value) {
                return in_array($value, ['1', '0'], true) ? $value : '1';
            },
        ]);
    }

    public function admin_styles(string $hook): void
    {
        if ($hook !== 'settings_page_humano-chat') {
            return;
        }
        echo '<style>.humano-chat-help { margin-top: 1em; padding: 1em; background: #f0f0f1; border-left: 4px solid #2271b1; }</style>';
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $base_url = get_option('humano_chat_base_url', '');
        $token = get_option('humano_chat_api_token', '');
        $floating = get_option('humano_chat_floating', '0');
        $prompt_key = get_option('humano_chat_prompt_key', '');
        $ssl_verify = get_option('humano_chat_ssl_verify', '1');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php settings_fields('humano_chat'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="humano_chat_base_url"><?php esc_html_e('Humano Base URL', 'humano-chat'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="humano_chat_base_url" name="humano_chat_base_url"
                                   value="<?php echo esc_attr($base_url); ?>"
                                   class="regular-text" placeholder="https://your-humano.test"/>
                            <p class="description"><?php esc_html_e('Your Humano instance URL (no trailing slash).', 'humano-chat'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="humano_chat_api_token"><?php esc_html_e('API Token', 'humano-chat'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="humano_chat_api_token" name="humano_chat_api_token"
                                   value="<?php echo esc_attr($token); ?>"
                                   class="regular-text" autocomplete="off"/>
                            <p class="description"><?php esc_html_e('Team API token from Humano: Team Settings → API Tokens. See your Humano /help for details.', 'humano-chat'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="humano_chat_prompt_key"><?php esc_html_e('Prompt key (optional)', 'humano-chat'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="humano_chat_prompt_key" name="humano_chat_prompt_key"
                                   value="<?php echo esc_attr($prompt_key); ?>"
                                   class="regular-text" placeholder="e.g. landing"/>
                            <p class="description"><?php esc_html_e('Leave empty to use the assistant router. Set a key to force a specific prompt flow.', 'humano-chat'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Floating widget', 'humano-chat'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="humano_chat_floating" value="1" <?php checked($floating, '1'); ?> />
                                <?php esc_html_e('Show floating chat button on all front-end pages', 'humano-chat'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Otherwise use the shortcode [humano_chat] where you want the chat.', 'humano-chat'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('SSL verification', 'humano-chat'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="humano_chat_ssl_verify" value="0" />
                                <input type="checkbox" name="humano_chat_ssl_verify" value="1" <?php checked($ssl_verify, '1'); ?> />
                                <?php esc_html_e('Verify SSL certificate when calling Humano (recommended)', 'humano-chat'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Uncheck only for local development (e.g. .test, self-signed certs). Fixes "cURL error 60: unable to get local issuer certificate". Do not disable in production.', 'humano-chat'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <div class="humano-chat-help">
                <p><strong><?php esc_html_e('Getting your API token', 'humano-chat'); ?></strong></p>
                <p><?php esc_html_e('Log in to Humano, go to Team Settings → API Tokens, generate a token and paste it above. Full documentation: open the Help section in Humano (e.g. /help and API Authentication).', 'humano-chat'); ?></p>
            </div>
        </div>
        <?php
    }
}
