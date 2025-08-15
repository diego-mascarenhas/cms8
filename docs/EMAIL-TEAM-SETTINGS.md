# Email Team Settings

This document explains how to configure team-specific email settings for both outgoing (SMTP) and incoming (IMAP) email.

## Overview

Each team can now configure their own email settings independently. If a team doesn't have specific settings configured, the system automatically falls back to the global `.env` configuration.

## Available Settings

### SMTP Configuration (Outgoing Email)

- **SMTP Host** (`mail_host`): SMTP server hostname (fallback to `MAIL_HOST`)
- **SMTP Port** (`mail_port`): SMTP server port (default: 587, fallback to `MAIL_PORT`) 
- **SMTP Username** (`mail_username`): Username for SMTP authentication (fallback to `MAIL_USERNAME`)
- **SMTP Password** (`mail_password`): Password for SMTP authentication (encrypted, fallback to `MAIL_PASSWORD`)
- **Encryption** (`mail_encryption`): Encryption method - TLS, SSL, or None (fallback to `MAIL_ENCRYPTION`)
- **From Email Address** (`mail_from_address`): Default sender email address (fallback to `MAIL_FROM_ADDRESS`)
- **From Name** (`mail_from_name`): Default sender name (fallback to `MAIL_FROM_NAME`)

### IMAP Configuration (Incoming Email)

- **IMAP Host** (`imap_host`): IMAP server hostname for incoming email
- **IMAP Port** (`imap_port`): IMAP server port (default: 993 for SSL)
- **IMAP Username** (`imap_username`): Username for IMAP authentication
- **IMAP Password** (`imap_password`): Password for IMAP authentication (encrypted)

## Configuration

### Via Web Interface

1. Navigate to Team Settings → Email Configuration
2. Fill in the fields you want to customize
3. Leave fields empty to use global `.env` defaults
4. Click "Save Changes"

### Programmatic Access

```php
// Get the current team's email configuration (with fallbacks)
$team = auth()->user()->currentTeam;

// Get outgoing email configuration
$outgoingConfig = $team->getOutgoingEmailConfig();
if ($team->hasOutgoingEmailConfig()) {
    // Use team-specific SMTP settings
    $host = $outgoingConfig['host'];
    $port = $outgoingConfig['port'];
    $username = $outgoingConfig['username'];
    $password = $outgoingConfig['password'];
    
    // Configure mail settings for this team
    config([
        'mail.mailers.smtp.host' => $host,
        'mail.mailers.smtp.port' => $port,
        'mail.mailers.smtp.username' => $username,
        'mail.mailers.smtp.password' => $password,
        'mail.mailers.smtp.encryption' => $outgoingConfig['encryption'],
        'mail.from.address' => $outgoingConfig['from_address'],
        'mail.from.name' => $outgoingConfig['from_name'],
    ]);
}

// Get incoming email configuration
$incomingConfig = $team->getIncomingEmailConfig();
if ($team->hasIncomingEmailConfig()) {
    // Configure IMAP settings
    $imapHost = $incomingConfig['host'];
    $imapPort = $incomingConfig['port'];
    $imapUsername = $incomingConfig['username'];
    $imapPassword = $incomingConfig['password'];
    $imapEncryption = $incomingConfig['encryption'];
}

// Access individual settings with fallbacks
$host = $team->getSetting('mail_host', env('MAIL_HOST'));
$username = $team->getSetting('mail_username', env('MAIL_USERNAME'));
$fromAddress = $team->getSetting('mail_from_address', env('MAIL_FROM_ADDRESS'));
```

## Helper Methods

The `Team` model provides convenient helper methods with descriptive names:

```php
// Get outgoing email settings (SMTP) with fallbacks to .env
$outgoingConfig = $team->getOutgoingEmailConfig();

// Get incoming email settings (IMAP)
$incomingConfig = $team->getIncomingEmailConfig();

// Check if outgoing email is properly configured
$hasOutgoing = $team->hasOutgoingEmailConfig();

// Check if incoming email is configured
$hasIncoming = $team->hasIncomingEmailConfig();

// Backwards compatibility methods (deprecated but still work)
$emailConfig = $team->getEmailConfig();        // Same as getOutgoingEmailConfig()
$imapConfig = $team->getImapConfig();          // Same as getIncomingEmailConfig()
$hasEmail = $team->hasEmailConfig();           // Same as hasOutgoingEmailConfig()
$hasImap = $team->hasImapConfig();             // Same as hasIncomingEmailConfig()
```

## Field Organization

The email configuration form is organized into logical sections:

### Outgoing Email (SMTP)
**Row 1:** Server Configuration  
- SMTP Host (50% width)
- SMTP Port (25% width)
- Encryption (25% width)

**Row 2:** Sender Information
- From Name (50% width)
- From Email Address (50% width)

**Row 3:** Authentication
- SMTP Username (50% width)
- SMTP Password (50% width)

### Incoming Email (IMAP)
**Row 1:** Server Configuration
- IMAP Host (50% width)
- IMAP Port (25% width)
- IMAP Encryption (25% width)

**Row 2:** Authentication
- IMAP Username (50% width)
- IMAP Password (50% width)

## Example Configuration Values

### OVH Mail Configuration
```php
// SMTP Settings
'host' => 'pro3.mail.ovh.net',
'port' => 587,
'username' => 'team@yourdomain.com',
'password' => 'your-password',
'encryption' => 'tls',
'from_address' => 'team@yourdomain.com',
'from_name' => 'Team Name',

// IMAP Settings  
'imap_host' => 'pro3.mail.ovh.net',
'imap_port' => 993,
'imap_username' => 'team@yourdomain.com',
'imap_password' => 'your-password',
'imap_encryption' => 'ssl',
```

### Gmail Configuration
```php
// SMTP Settings
'host' => 'smtp.gmail.com',
'port' => 587,
'username' => 'team@gmail.com',
'password' => 'app-password',
'encryption' => 'tls',
'from_address' => 'team@gmail.com',
'from_name' => 'Team Name',

// IMAP Settings
'imap_host' => 'imap.gmail.com',
'imap_port' => 993,
'imap_username' => 'team@gmail.com',
'imap_password' => 'app-password',
'imap_encryption' => 'ssl',
```

## Security

- SMTP and IMAP passwords are automatically encrypted when stored
- Only team members with appropriate permissions can view/edit settings
- Settings are isolated per team - teams cannot access each other's configurations
- Fallback to global `.env` settings ensures no interruption of service

## Migration from Global Settings

The system automatically falls back to environment variables if team-specific settings are not configured:

### No Migration Required

- Existing code will continue to work with `.env` settings
- Teams can gradually opt-in to custom email settings
- Empty team settings automatically use global `.env` values

### Optional Team Customization

Teams can override global settings by:

1. Navigate to Team Settings → Email Configuration
2. Fill in only the fields they want to customize
3. Leave other fields empty to keep using `.env` defaults

## Example Service Integration

```php
<?php

namespace App\Services;

use App\Models\Team;

class TeamMailService
{
    protected $team;

    public function __construct(Team $team)
    {
        $this->team = $team;
        $this->configureMailSettings();
    }

    protected function configureMailSettings()
    {
        if ($this->team->hasOutgoingEmailConfig()) {
            $config = $this->team->getOutgoingEmailConfig();
            
            // Override mail configuration for this team
            config([
                'mail.mailers.smtp.host' => $config['host'],
                'mail.mailers.smtp.port' => $config['port'],
                'mail.mailers.smtp.username' => $config['username'],
                'mail.mailers.smtp.password' => $config['password'],
                'mail.mailers.smtp.encryption' => $config['encryption'],
                'mail.from.address' => $config['from_address'],
                'mail.from.name' => $config['from_name'],
            ]);
        }
    }

    public function sendEmail($to, $subject, $content)
    {
        // Send email using team-specific configuration
        return \Mail::to($to)->send(new \App\Mail\TeamEmail($subject, $content));
    }

    public function getIncomingEmails()
    {
        if (!$this->team->hasIncomingEmailConfig()) {
            throw new \Exception('Incoming email not configured for this team');
        }

        $config = $this->team->getIncomingEmailConfig();
        
        // Connect to IMAP and fetch emails
        $encryption = $config['encryption'] === 'ssl' ? 'ssl' : 'tls';
        $mailbox = new \PhpImap\Mailbox(
            '{' . $config['host'] . ':' . $config['port'] . '/imap/' . $encryption . '}INBOX',
            $config['username'],
            $config['password']
        );

        return $mailbox->searchMailbox('UNSEEN');
    }
}
```

## Validation

The system validates:
- Host and username are required for email functionality
- Port numbers must be between 1-65535
- Email addresses must be valid format
- Encryption must be 'tls', 'ssl', or 'none'
- All fields are optional (nullable) to allow fallbacks

## Routes

- `GET /team/{team}/settings` - List all team settings
- `GET /team/{team}/settings/email` - Edit email settings form
- `PUT /team/{team}/settings` - Update email settings
