# Humano Chat – WordPress Plugin

Chat widget that connects to your **Humano** assistant (Option B: full assistant with router and flows). Authentication is via **team API token**.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A Humano instance with the **Assistant Chat API** enabled (POST `/api/team/assistant/chat`)
- A **team API token** from Humano (Team Settings → API Tokens). See Humano’s **/help** (and “API Authentication”) for details.

## Installation

1. Copy the `humano-chat` folder to `wp-content/plugins/`.
2. In WordPress admin go to **Plugins** and activate **Humano Chat**.
3. Go to **Settings → Humano Chat** and set:
   - **Humano Base URL**: your Humano URL (e.g. `https://humano.test` or your production URL), no trailing slash.
   - **API Token**: the team token from Humano (Team Settings → API Tokens).
   - **Prompt key** (optional): leave empty to use the assistant router; set a key to force a specific prompt flow.
   - **Floating widget**: check to show the chat button on all front-end pages, or leave unchecked and use the shortcode only.

## Usage

- **Shortcode**: add `[humano_chat]` in any page or post where you want the chat.
- **Floating widget**: enable “Show floating chat button on all front-end pages” in Settings → Humano Chat to show a button in the corner that opens the chat panel.

## API (Humano)

The plugin calls:

- **POST** `{Base URL}/api/team/assistant/chat`
- **Headers**: `Authorization: Bearer {token}`, `Content-Type: application/json`
- **Body**: `{"message": "user message", "prompt_key": "optional"}`
- **Response**: `{"success": true, "response": "...", "routed_to": "..."}`

Token and base URL are only used on the server (WordPress); they are never sent to the browser.

## Translations

The plugin uses WordPress's standard i18n (text domain: `humano-chat`). The UI language follows **Settings → General → Site Language**.

- **Included:** `languages/humano-chat.pot` (template) and `languages/humano-chat-es_ES.po` (Spanish). To compile the Spanish .mo: `msgfmt -o languages/humano-chat-es_ES.mo languages/humano-chat-es_ES.po` (requires gettext).
- **Adding a language:** Copy the .pot to `humano-chat-{locale}.po`, translate the `msgstr` lines, then compile to `.mo` with Poedit or `msgfmt`.
- **Tools:** Loco Translate or Poedit; WP-CLI: `wp i18n make-pot . languages/humano-chat.pot` to regenerate the template.

## Support

For token generation and API details, use the **Help** section in your Humano app (e.g. `/help` and “API Authentication”).
