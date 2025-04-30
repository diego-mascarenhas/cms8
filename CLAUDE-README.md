# Claude AI Chat Integration

This integration allows you to use Anthropic's Claude AI in your existing WhatsApp chat application.

## Setup Instructions

1. Ensure you have an API key from Anthropic. You can get one by signing up at [Anthropic's Console](https://console.anthropic.com/).

2. Add the following environment variables to your `.env` file:
   ```
   CLAUDE_API_KEY=your_claude_api_key_here
   CLAUDE_MODEL=claude-3-opus-20240229
   CLAUDE_MAX_TOKENS=1000
   CLAUDE_AUTO_RESPOND=false
   CLAUDE_SYSTEM_PROMPT=
   ```

3. Choose the appropriate Claude model:
   - `claude-3-opus-20240229`: Most powerful, highest quality
   - `claude-3-sonnet-20240229`: Balanced performance and cost
   - `claude-3-haiku-20240229`: Fastest, most cost-effective

4. Update your cache:
   ```
   php artisan config:cache
   ```

## How to Use

### Manual AI Assistance
1. Navigate to the chat interface
2. Toggle the "Claude AI" switch in the message input area
3. Type your message and send it
4. Claude will process the message and respond automatically

### Automatic Responses
Claude can automatically respond to incoming messages without human intervention:

1. Set `CLAUDE_AUTO_RESPOND=true` in your `.env` file
2. Run `php artisan config:cache` to apply the change
3. Now when users send messages to your WhatsApp number, Claude will automatically:
   - Process the incoming message
   - Generate a response based on message history and context
   - Send that response back to the user

This feature is useful for:
- 24/7 customer support
- Automated responses outside business hours
- Initial triage of customer inquiries

## Managing Prompts

Prompts are instructions that guide Claude's behavior and responses. The system comes with a prompt management interface that allows you to create, edit, test, and activate different prompts for various scenarios.

### Accessing the Prompt Manager

Navigate to `/claude/prompts` in your browser to access the prompt management interface.

### Creating a New Prompt

1. Click "Create New Prompt" in the prompts interface
2. Give your prompt a descriptive name (no spaces, use underscores)
3. Write detailed instructions for Claude in the content area
4. Click "Create Prompt" to save

### Testing Prompts

1. Click the "Preview" button next to any prompt
2. Enter a test message
3. See how Claude would respond using that prompt
4. Make adjustments to the prompt as needed

### Activating Prompts

To set a prompt as active:
1. Click the "Activate" button next to the desired prompt
2. This will use the prompt for the current session

For a permanent change, set the `CLAUDE_SYSTEM_PROMPT` environment variable to your prompt's content, or use the prompt management interface which stores prompts in the `/storage/app/claude_prompts/` directory.

### Example Prompts

The system includes example prompts for:
- Technical support
- Sales assistance
- Customer service

You can use these as starting points or create your own for specific use cases.

### Prompt Writing Tips

Effective prompts typically include:
1. A clear role definition for Claude
2. Specific responsibilities
3. Communication guidelines (tone, style, length)
4. Constraints and limitations
5. Special formatting instructions for WhatsApp

Keep prompts focused and avoid conflicting instructions.

## Features

- Context-aware conversations (Claude receives recent message history)
- Seamless integration with existing WhatsApp messaging
- Fallback to normal messaging if Claude is unavailable
- Manual AI assistance mode for agent-assisted conversations
- Automatic response mode for fully automated conversations
- Prompt management system for customizing Claude's behavior

## Troubleshooting

If you encounter issues:

1. Check that your API key is valid and has sufficient quota
2. Verify your internet connection
3. Check the Laravel logs for specific error messages

## Technical Implementation

This integration consists of:

- `ClaudeService` - A service class for interacting with the Claude API
- ChatController modifications to handle AI-assisted messaging
- Frontend updates to support toggling AI assistance
- TwilioService integration for automatic responses to incoming messages
- Prompt management system for customizing Claude's behavior

## Security Notes

- Your Claude API key should be kept secure and never exposed to the frontend
- All communications with the Claude API are encrypted with HTTPS
- User data is processed according to Anthropic's privacy policy 