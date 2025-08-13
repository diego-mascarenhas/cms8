# Twilio Team Settings

This document explains how to use the team-based Twilio configuration system.

## Overview

Each team can now configure their own Twilio settings independently. This allows multiple teams to use different Twilio accounts, phone numbers, and configurations.

## Available Settings

The following Twilio settings can be configured per team:

- **Account SID** (`twilio_sid`): Your Twilio Account SID
- **Auth Token** (`twilio_token`): Your Twilio Auth Token (encrypted)
- **SMS From Number** (`twilio_sms_from`): Phone number for SMS messages
- **WhatsApp From Number** (`twilio_whatsapp_from`): Phone number for WhatsApp messages
- **Webhook URL** (`twilio_webhook_url`): URL for Twilio webhooks
- **Status Callback URL** (`twilio_status_callback_url`): URL for status callbacks

## Configuration

### Via Web Interface

1. Navigate to Team Settings in your application
2. Click on the "Twilio Integration" card
3. Fill in your Twilio configuration details
4. Click "Save Changes"

### Programmatic Access

```php
// Get the current team's Twilio configuration
$team = auth()->user()->currentTeam;
$twilioConfig = $team->getTwilioConfig();

// Check if Twilio is configured for the team
if ($team->hasTwilioConfig()) {
    // Use Twilio with team-specific settings
    $sid = $twilioConfig['sid'];
    $token = $twilioConfig['token'];
    $fromNumber = $twilioConfig['sms_from'];
    
    // Initialize Twilio client
    $twilio = new \Twilio\Rest\Client($sid, $token);
    
    // Send SMS using team's configuration
    $message = $twilio->messages->create(
        '+1234567890', // To
        [
            'from' => $fromNumber,
            'body' => 'Hello from your team!'
        ]
    );
}

// Access individual settings
$sid = $team->getSetting('twilio_sid');
$token = $team->getSetting('twilio_token'); // Automatically decrypted
$smsFrom = $team->getSetting('twilio_sms_from');
$whatsappFrom = $team->getSetting('twilio_whatsapp_from');
```

## Security

- The Twilio Auth Token is automatically encrypted when stored in the database
- Only team members with appropriate permissions can view/edit settings
- Settings are isolated per team - teams cannot access each other's configurations

## Helper Methods

The `Team` model provides convenient helper methods:

```php
// Get all Twilio settings as an array
$config = $team->getTwilioConfig();

// Check if Twilio is properly configured
$isConfigured = $team->hasTwilioConfig();

// Get individual settings
$sid = $team->getSetting('twilio_sid');
$token = $team->getSetting('twilio_token');
```

## Migration from Global Settings

If you were previously using global Twilio settings via environment variables, you can migrate to team-specific settings by:

1. Creating the team settings for each team
2. Copying values from your `.env` file to team settings
3. Updating your code to use team-specific settings instead of global ones

## Example Usage in Services

```php
<?php

namespace App\Services;

use App\Models\Team;
use Twilio\Rest\Client;

class TeamTwilioService
{
    protected $team;
    protected $client;

    public function __construct(Team $team)
    {
        $this->team = $team;
        
        if ($team->hasTwilioConfig()) {
            $config = $team->getTwilioConfig();
            $this->client = new Client($config['sid'], $config['token']);
        }
    }

    public function sendSMS($to, $message)
    {
        if (!$this->client) {
            throw new \Exception('Twilio not configured for this team');
        }

        $config = $this->team->getTwilioConfig();
        
        return $this->client->messages->create($to, [
            'from' => $config['sms_from'],
            'body' => $message,
            'statusCallback' => $config['status_callback_url']
        ]);
    }

    public function sendWhatsApp($to, $message)
    {
        if (!$this->client) {
            throw new \Exception('Twilio not configured for this team');
        }

        $config = $this->team->getTwilioConfig();
        
        return $this->client->messages->create("whatsapp:$to", [
            'from' => "whatsapp:" . $config['whatsapp_from'],
            'body' => $message,
            'statusCallback' => $config['status_callback_url']
        ]);
    }
}
```

## Validation

The system validates:
- SID and Token format (string, max 255 chars)
- Phone numbers format (string, max 255 chars)
- URLs are properly formatted
- All fields are optional (nullable)

## Routes

The following routes are available for Twilio settings:

- `GET /team/{team}/settings` - List all team settings
- `GET /team/{team}/settings/twilio` - Edit Twilio settings form
- `PUT /team/{team}/settings` - Update Twilio settings
