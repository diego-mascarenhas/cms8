<?php

namespace App\Console\Commands;

use App\Jobs\SendMessageCampaignJob;
use App\Models\Message;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;

class SendAllPendingNow extends Command
{
    protected $signature = 'emails:send-all-now {--dry-run : Show what would be sent without actually sending} {--limit=1000 : Maximum number of emails to send} {--message-id= : Send only deliveries for specific message ID}';

    protected $description = 'Send ALL pending message deliveries immediately without delays. Use --message-id to send only specific campaign.';

    public function handle()
    {
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');
        $messageId = $this->option('message-id');

        // Validate message ID exists if provided
        if ($messageId)
        {
            $message = Message::find($messageId);
            if (! $message)
            {
                $this->error("❌ Message ID {$messageId} not found.");

                return 1;
            }
            $this->info("🚀 Checking for pending deliveries for Message ID: {$messageId}...");
            $this->comment("   📝 Campaign: {$message->name}");
        } else
        {
            $this->info('🚀 Checking for pending message deliveries...');
        }

        // Get all pending deliveries (sent_at is null and status is pending)
        $query = MessageDelivery::whereNull('sent_at')
            ->where('status_id', 1) // pending status
            ->with(['contact', 'message.template', 'team']);

        // Filter by specific message ID if provided
        if ($messageId)
        {
            $query->where('message_id', $messageId);
        }

        $pendings = $query->limit($limit)->get();

        if ($pendings->isEmpty())
        {
            if ($messageId)
            {
                $this->info("📭 No pending deliveries found for Message ID: {$messageId}.");
            } else
            {
                $this->info('📭 No pending deliveries found.');
            }

            return 0;
        }

        if ($messageId)
        {
            $this->info("📧 Found {$pendings->count()} pending deliveries for Message ID: {$messageId}");
        } else
        {
            $this->info("📧 Found {$pendings->count()} pending deliveries");
        }

        if ($dryRun)
        {
            $this->info('🔍 DRY RUN MODE - Showing what would be sent:');

            // Show Message ID column only when not filtering by specific message
            $headers = $messageId
                ? ['ID', 'Contact Email', 'Message', 'Team']
                : ['ID', 'Contact Email', 'Msg ID', 'Message', 'Team'];

            $this->table(
                $headers,
                $pendings->take(20)->map(function ($delivery) use ($messageId)
                {
                    $row = [
                        $delivery->id,
                        $delivery->contact->email ?? 'NO EMAIL',
                    ];

                    if (! $messageId)
                    {
                        $row[] = $delivery->message->id ?? 'N/A';
                    }

                    $row[] = $delivery->message->name ?? 'NO MESSAGE';
                    $row[] = $delivery->team->name ?? 'NO TEAM';

                    return $row;
                })->toArray(),
            );

            if ($pendings->count() > 20)
            {
                $this->info('... and '.($pendings->count() - 20).' more deliveries');
            }

            return 0;
        }

        // Confirm before mass sending
        $confirmMessage = $messageId
            ? "⚠️  Are you sure you want to send {$pendings->count()} emails from Message ID {$messageId} immediately?"
            : "⚠️  Are you sure you want to send {$pendings->count()} emails immediately?";

        if (! $this->confirm($confirmMessage))
        {
            $this->info('❌ Operation cancelled.');

            return 1;
        }

        $sent = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($pendings->count());
        $bar->start();

        foreach ($pendings as $delivery)
        {
            // Validation checks
            if (! $delivery->contact || ! $delivery->contact->email)
            {
                $this->newLine();
                $this->warn("⚠️  No email for delivery ID: {$delivery->id}");
                $errors++;
                $bar->advance();

                continue;
            }

            // Check that the message is active
            if (! $delivery->message || $delivery->message->status_id != 1)
            {
                $this->newLine();
                $this->warn("⚠️  Inactive message for delivery ID: {$delivery->id}");
                $errors++;
                $bar->advance();

                continue;
            }

            // Check that team exists
            if (! $delivery->team)
            {
                $this->newLine();
                $this->warn("⚠️  No team for delivery ID: {$delivery->id}");
                $errors++;
                $bar->advance();

                continue;
            }

            // Check email limits for team
            if (! $delivery->team->canSendEmails(1))
            {
                $remaining = $delivery->team->getRemainingEmails();
                $this->newLine();
                $this->warn("⚠️  Team '{$delivery->team->name}' has reached email limits:");
                $this->warn("    Monthly: {$remaining['monthly_used']}/{$remaining['monthly_limit']}");
                if ($remaining['daily_limit'])
                {
                    $this->warn("    Daily: {$remaining['daily_used']}/{$remaining['daily_limit']}");
                }
                $errors++;
                $bar->advance();

                continue;
            }

            try
            {
                // 🚀 Dispatch job WITHOUT delay - send immediately!
                SendMessageCampaignJob::dispatch($delivery)
                    ->onQueue('mailer');

                // Increment team's email usage counter
                $delivery->team->incrementEmailUsage(1);

                $sent++;
            } catch (\Exception $e)
            {
                $this->newLine();
                $this->error("❌ Error queueing job for {$delivery->contact->email}: {$e->getMessage()}");
                $delivery->markAsError($e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('✅ Summary:');
        $this->info("📤 Jobs queued for immediate sending: {$sent}");
        $this->info("❌ Errors: {$errors}");

        if ($sent > 0)
        {
            $this->info('🔄 Jobs are now in the queue. Check queue worker status:');
            $this->comment("   ssh forge@server 'ps aux | grep queue:work'");
        }

        return 0;
    }
}
