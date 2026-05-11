<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;

class DebugMessageContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message:debug-contacts {messageId} {--contact-email=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug why contacts are or aren\'t selected for a message campaign';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $messageId = $this->argument('messageId');
        $contactEmail = $this->option('contact-email');

        $message = Message::with('category')->find($messageId);

        if (! $message)
        {
            $this->error("❌ Message with ID {$messageId} not found.");

            return 1;
        }

        $this->info("📧 Debugging Message: {$message->name}");
        $this->info("   ID: {$message->id}");
        $this->info('   Status: '.($message->status_id ? 'Active' : 'Inactive'));
        $this->info('   Category: '.($message->category ? $message->category->name : 'None (All contacts)'));
        $this->info('   Contact Status Filter: '.($message->contact_status_id ?: '1 (Active)'));
        $this->info('   Min Hours Between Emails: '.($message->min_hours_between_emails ?: 48));
        $this->newLine();

        // Get contacts that would be selected
        $contacts = $this->getContactsForMessage($message);

        $this->info("✅ Contacts that WILL receive this message: {$contacts->count()}");
        $this->newLine();

        if ($contactEmail)
        {
            // Debug specific contact
            $contact = Contact::where('email', $contactEmail)
                ->where('team_id', $message->team_id)
                ->first();

            if (! $contact)
            {
                $this->error("❌ Contact with email {$contactEmail} not found in this team.");

                return 1;
            }

            $this->info("🔍 Debugging specific contact: {$contact->email}");
            $this->debugContact($contact, $message);
        } else
        {
            // Show first 10 contacts
            $this->info('📋 First 10 contacts that will receive this message:');
            foreach ($contacts->take(10) as $contact)
            {
                $this->info("   ✓ {$contact->email} ({$contact->name})");
            }
        }

        return 0;
    }

    private function debugContact($contact, $message)
    {
        $this->newLine();
        $this->info('Contact Details:');
        $this->info("   Email: {$contact->email}");
        $this->info("   Name: {$contact->name}");
        $this->info("   Status ID: {$contact->status_id}");
        $this->newLine();

        // Check 1: Category
        if ($message->category)
        {
            $inCategory = $message->category->contacts()->where('contact_id', $contact->id)->exists();
            if ($inCategory)
            {
                $this->info("✅ Contact IS in category: {$message->category->name}");
            } else
            {
                $this->error("❌ Contact is NOT in category: {$message->category->name}");
                $this->info('   This contact will NOT receive this message.');

                return;
            }
        } else
        {
            $this->info('✅ No category filter (all contacts eligible)');
        }

        // Check 2: Contact Status
        $expectedStatus = $message->contact_status_id ?: 1;
        if ($contact->status_id == $expectedStatus)
        {
            $this->info("✅ Contact status matches: {$contact->status_id}");
        } else
        {
            $this->error('❌ Contact status does NOT match.');
            $this->info("   Expected: {$expectedStatus}");
            $this->info("   Actual: {$contact->status_id}");
            $this->info('   This contact will NOT receive this message.');

            return;
        }

        // Check 3: Email domain
        $testDomains = [
            '@example.org',
            '@example.net',
            '@example.com',
            '@demo.com',
            '@test.com',
            '@localhost',
            '@testing.com',
            '@dummy.com',
            '@fake.com',
        ];

        $isTestEmail = false;
        foreach ($testDomains as $domain)
        {
            if (stripos($contact->email, $domain) !== false)
            {
                $isTestEmail = true;
                $this->error("❌ Email contains test domain: {$domain}");
                $this->info('   This contact will NOT receive this message.');

                return;
            }
        }
        $this->info('✅ Email is not a test domain');

        // Check 4: Minimum hours between emails
        $canSend = $message->canSendToContact($contact);
        if ($canSend)
        {
            $this->info('✅ Can send to contact (respects min_hours_between_emails)');
        } else
        {
            $nextAvailable = $message->getNextAvailableTimeForContact($contact);
            $this->warn('⏰ Cannot send yet (min_hours_between_emails not met)');
            $this->info("   Next available time: {$nextAvailable->format('Y-m-d H:i:s')}");
            $this->info('   This contact will be scheduled for later.');
        }

        // Check 5: Existing deliveries
        $existingDelivery = MessageDelivery::where('message_id', $message->id)
            ->whereNull('campaign_id')
            ->where('contact_id', $contact->id)
            ->first();

        if ($existingDelivery)
        {
            $this->warn('⚠️  Delivery already exists for this contact');
            $this->info('   Status: '.($existingDelivery->status_id == 1 ? 'Pending' : 'Sent'));
            $this->info('   Scheduled for: '.($existingDelivery->sent_at ? $existingDelivery->sent_at->format('Y-m-d H:i:s') : 'N/A'));
            if ($existingDelivery->delivered_at)
            {
                $this->info("   Delivered at: {$existingDelivery->delivered_at->format('Y-m-d H:i:s')}");
            }
        } else
        {
            $this->info('✅ No existing delivery - will be created when campaign starts');
        }

        $this->newLine();
        $this->info('🎯 CONCLUSION: This contact WILL receive this message');
    }

    /**
     * Get contacts for a message based on its category
     */
    private function getContactsForMessage(Message $message)
    {
        $query = null;

        if ($message->category)
        {
            $query = $message->category->contacts();

            // Filter by contact status - use message's contact_status_id or default to active (1)
            $statusId = $message->contact_status_id ?: 1;
            $query->where('status_id', $statusId);
        } else
        {
            // If no category, get all contacts from the team
            $query = Contact::where('team_id', $message->team_id)
                ->whereNotNull('email');

            // Filter by contact status - use message's contact_status_id or default to active (1)
            $statusId = $message->contact_status_id ?: 1;
            $query->where('status_id', $statusId);
        }

        // Exclude test/demo email addresses
        $testDomains = [
            '@example.org',
            '@example.net',
            '@example.com',
            '@demo.com',
            '@test.com',
            '@localhost',
            '@testing.com',
            '@dummy.com',
            '@fake.com',
        ];

        foreach ($testDomains as $domain)
        {
            $query->where('email', 'not like', '%'.$domain);
        }

        return $query->get();
    }
}
