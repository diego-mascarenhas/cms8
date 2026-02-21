<?php

/**
 * Plugin Name: Humano Chat
 * Description: Chat widget that connects to your Humano assistant. Authentication via team API token (see your Humano /help).
 * Version: 1.0.0
 * Author: Humano
 * Text Domain: humano-chat
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('HUMANO_CHAT_VERSION', '1.0.0');
define('HUMANO_CHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HUMANO_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HUMANO_CHAT_PLUGIN_DIR . 'includes/class-humano-chat-api.php';
require_once HUMANO_CHAT_PLUGIN_DIR . 'includes/class-humano-chat-settings.php';
require_once HUMANO_CHAT_PLUGIN_DIR . 'includes/class-humano-chat-shortcode.php';
require_once HUMANO_CHAT_PLUGIN_DIR . 'includes/class-humano-chat-ajax.php';

function humano_chat_load_textdomain(): void
{
    load_plugin_textdomain(
        'humano-chat',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'humano_chat_load_textdomain', 0);

function humano_chat_init(): void
{
    Humano_Chat_Settings::get_instance();
    Humano_Chat_Shortcode::get_instance();
    Humano_Chat_Ajax::get_instance();
}
add_action('plugins_loaded', 'humano_chat_init');

function humano_chat_activate(): void
{
    // Set defaults on first activation
    if (get_option('humano_chat_base_url') === false) {
        update_option('humano_chat_base_url', '');
    }
    if (get_option('humano_chat_api_token') === false) {
        update_option('humano_chat_api_token', '');
    }
    if (get_option('humano_chat_floating') === false) {
        update_option('humano_chat_floating', '0');
    }
    if (get_option('humano_chat_ssl_verify') === false) {
        update_option('humano_chat_ssl_verify', '1');
    }
}
register_activation_hook(__FILE__, 'humano_chat_activate');
