<?php

namespace App\Console\Commands;

use App\Mail\TeamConfigurationReport;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestTeamConfigurations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'team:test-configurations
							{--team= : Test specific team ID only}
							{--report-email= : Send report to specific email}
							{--no-email : Do not send email report}
							{--failures-only : Only report failures}
							{--admin-summary : Send admin summary to notification email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test all team configurations (SMTP, IMAP, Stripe, Twilio) and generate report';

    protected $results = [];

    protected $totalTests = 0;

    protected $failedTests = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Starting Team Configuration Test...');
        $this->info('');

        $teams = $this->getTeamsToTest();

        if ($teams->isEmpty())
        {
            $this->error('No teams found to test.');

            return 1;
        }

        $this->info("📊 Testing {$teams->count()} team(s)...");
        $this->newLine();

        foreach ($teams as $team)
        {
            $this->testTeamConfiguration($team);
        }

        $this->displaySummary();
        $this->logResults();

        if (! $this->option('no-email'))
        {
            $this->sendTeamOwnerReports();

            if ($this->option('admin-summary'))
            {
                $this->sendAdminSummaryReport();
            }
        }

        return $this->failedTests > 0 ? 1 : 0;
    }

    protected function getTeamsToTest()
    {
        if ($teamId = $this->option('team'))
        {
            return Team::where('id', $teamId)->get();
        }

        return Team::all();
    }

    protected function testTeamConfiguration(Team $team)
    {
        $this->info("🏢 Testing Team: {$team->name} (ID: {$team->id})");

        $teamResults = [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'tests' => [],
            'summary' => ['passed' => 0, 'failed' => 0, 'skipped' => 0],
        ];

        // Test SMTP
        $smtpResult = $this->testSmtp($team);
        $teamResults['tests']['smtp'] = $smtpResult;
        $teamResults['summary'][$smtpResult['status']]++;

        // Test IMAP
        $imapResult = $this->testImap($team);
        $teamResults['tests']['imap'] = $imapResult;
        $teamResults['summary'][$imapResult['status']]++;

        // Test Stripe
        $stripeResult = $this->testStripe($team);
        $teamResults['tests']['stripe'] = $stripeResult;
        $teamResults['summary'][$stripeResult['status']]++;

        // Test Twilio
        $twilioResult = $this->testTwilio($team);
        $teamResults['tests']['twilio'] = $twilioResult;
        $teamResults['summary'][$twilioResult['status']]++;

        $this->results[] = $teamResults;
        $this->totalTests += 4;
        $this->failedTests += $teamResults['summary']['failed'];

        $this->displayTeamSummary($teamResults);
        $this->newLine();
    }

    protected function testSmtp(Team $team): array
    {
        try
        {
            $config = $team->getOutgoingEmailConfig();

            if (empty($config['host']) || empty($config['username']))
            {
                return [
                    'service' => 'SMTP',
                    'status' => 'skipped',
                    'message' => 'Configuration incomplete',
                    'details' => 'Missing host or username',
                ];
            }

            // Test socket connection
            $socket = @fsockopen($config['host'], $config['port'] ?? 587, $errno, $errstr, 10);
            if (! $socket)
            {
                return [
                    'service' => 'SMTP',
                    'status' => 'failed',
                    'message' => 'Connection failed',
                    'details' => "{$config['host']}:{$config['port']} - {$errstr} ({$errno})",
                ];
            }
            fclose($socket);

            return [
                'service' => 'SMTP',
                'status' => 'passed',
                'message' => 'Connection successful',
                'details' => "{$config['host']}:{$config['port']}",
            ];
        } catch (\Exception $e)
        {
            return [
                'service' => 'SMTP',
                'status' => 'failed',
                'message' => 'Test error',
                'details' => $e->getMessage(),
            ];
        }
    }

    protected function testImap(Team $team): array
    {
        try
        {
            $config = $team->getIncomingEmailConfig();

            if (empty($config['host']) || empty($config['username']))
            {
                return [
                    'service' => 'IMAP',
                    'status' => 'skipped',
                    'message' => 'Configuration incomplete',
                    'details' => 'Missing host or username',
                ];
            }

            $connectionString = "{{$config['host']}:{$config['port']}/imap";
            if ($config['encryption'] === 'ssl')
            {
                $connectionString .= '/ssl';
            } elseif ($config['encryption'] === 'tls')
            {
                $connectionString .= '/tls';
            }
            $connectionString .= '/novalidate-cert}';

            $connection = @imap_open($connectionString, $config['username'], $config['password'] ?? '');

            if ($connection)
            {
                imap_close($connection);

                return [
                    'service' => 'IMAP',
                    'status' => 'passed',
                    'message' => 'Connection successful',
                    'details' => "{$config['host']}:{$config['port']}",
                ];
            } else
            {
                return [
                    'service' => 'IMAP',
                    'status' => 'failed',
                    'message' => 'Connection failed',
                    'details' => imap_last_error() ?: 'Unknown error',
                ];
            }
        } catch (\Exception $e)
        {
            return [
                'service' => 'IMAP',
                'status' => 'failed',
                'message' => 'Test error',
                'details' => $e->getMessage(),
            ];
        }
    }

    protected function testStripe(Team $team): array
    {
        try
        {
            $publicKey = $team->getSetting('stripe_public');
            $secretKey = $team->getSetting('stripe_secret');

            if (empty($publicKey) || empty($secretKey))
            {
                return [
                    'service' => 'Stripe',
                    'status' => 'skipped',
                    'message' => 'Configuration incomplete',
                    'details' => 'Missing public or secret key',
                ];
            }

            if (! str_starts_with($publicKey, 'pk_') || ! str_starts_with($secretKey, 'sk_'))
            {
                return [
                    'service' => 'Stripe',
                    'status' => 'failed',
                    'message' => 'Invalid key format',
                    'details' => 'Keys must start with pk_ and sk_',
                ];
            }

            \Stripe\Stripe::setApiKey($secretKey);
            $account = \Stripe\Account::retrieve();

            return [
                'service' => 'Stripe',
                'status' => 'passed',
                'message' => 'Connection successful',
                'details' => ($account->display_name ?? $account->business_profile->name ?? 'Account')." ({$account->country})",
            ];
        } catch (\Stripe\Exception\AuthenticationException $e)
        {
            return [
                'service' => 'Stripe',
                'status' => 'failed',
                'message' => 'Authentication failed',
                'details' => 'Invalid API keys',
            ];
        } catch (\Exception $e)
        {
            return [
                'service' => 'Stripe',
                'status' => 'failed',
                'message' => 'Test error',
                'details' => $e->getMessage(),
            ];
        }
    }

    protected function testTwilio(Team $team): array
    {
        try
        {
            $config = $team->getTwilioConfig();

            if (empty($config['sid']) || empty($config['token']))
            {
                return [
                    'service' => 'Twilio',
                    'status' => 'skipped',
                    'message' => 'Configuration incomplete',
                    'details' => 'Missing SID or token',
                ];
            }

            if (! str_starts_with($config['sid'], 'AC'))
            {
                return [
                    'service' => 'Twilio',
                    'status' => 'failed',
                    'message' => 'Invalid SID format',
                    'details' => 'SID must start with AC',
                ];
            }

            $twilio = new \Twilio\Rest\Client($config['sid'], $config['token']);
            $account = $twilio->api->v2010->account->fetch();

            if ($account->status !== 'active')
            {
                return [
                    'service' => 'Twilio',
                    'status' => 'failed',
                    'message' => 'Account not active',
                    'details' => "Status: {$account->status}",
                ];
            }

            return [
                'service' => 'Twilio',
                'status' => 'passed',
                'message' => 'Connection successful',
                'details' => "{$account->friendlyName} ({$account->status})",
            ];
        } catch (\Twilio\Exceptions\RestException $e)
        {
            return [
                'service' => 'Twilio',
                'status' => 'failed',
                'message' => 'API error',
                'details' => $e->getMessage(),
            ];
        } catch (\Exception $e)
        {
            return [
                'service' => 'Twilio',
                'status' => 'failed',
                'message' => 'Test error',
                'details' => $e->getMessage(),
            ];
        }
    }

    protected function displayTeamSummary($teamResults)
    {
        $summary = $teamResults['summary'];

        foreach ($teamResults['tests'] as $test)
        {
            $icon = $test['status'] === 'passed' ? '✅' : ($test['status'] === 'failed' ? '❌' : '⏭️');
            $status = strtoupper($test['status']);

            $this->line("  {$icon} {$test['service']}: {$status} - {$test['message']}");

            if ($test['status'] === 'failed' || $this->option('verbose'))
            {
                $this->line("	📝 {$test['details']}");
            }
        }

        $this->line("  📊 Summary: {$summary['passed']} passed, {$summary['failed']} failed, {$summary['skipped']} skipped");
    }

    protected function displaySummary()
    {
        $this->newLine();
        $this->info('📈 FINAL SUMMARY');
        $this->info('===============');

        $totalPassed = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($this->results as $teamResult)
        {
            $totalPassed += $teamResult['summary']['passed'];
            $totalFailed += $teamResult['summary']['failed'];
            $totalSkipped += $teamResult['summary']['skipped'];
        }

        $this->info('Teams tested: '.count($this->results));
        $this->info("Total tests: {$this->totalTests}");
        $this->line("✅ Passed: {$totalPassed}");
        $this->line("❌ Failed: {$totalFailed}");
        $this->line("⏭️ Skipped: {$totalSkipped}");

        if ($totalFailed > 0)
        {
            $this->error("\n⚠️  {$totalFailed} configuration(s) need attention!");
        } else
        {
            $this->info("\n🎉 All configured services are working correctly!");
        }
    }

    protected function logResults()
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'total_teams' => count($this->results),
            'total_tests' => $this->totalTests,
            'failed_tests' => $this->failedTests,
            'results' => $this->results,
        ];

        Log::channel('daily')->info('Team Configuration Test Results', $logData);

        // Also log failures separately for alerting
        if ($this->failedTests > 0)
        {
            $failures = [];
            foreach ($this->results as $teamResult)
            {
                foreach ($teamResult['tests'] as $test)
                {
                    if ($test['status'] === 'failed')
                    {
                        $failures[] = [
                            'team' => $teamResult['team_name'],
                            'service' => $test['service'],
                            'message' => $test['message'],
                            'details' => $test['details'],
                        ];
                    }
                }
            }

            Log::channel('daily')->error('Team Configuration Failures Detected', [
                'failures' => $failures,
                'total_failures' => $this->failedTests,
            ]);
        }
    }

    protected function sendTeamOwnerReports()
    {
        $this->info('📧 Sending individual reports to team owners...');

        foreach ($this->results as $teamResult)
        {
            try
            {
                $teamId = $teamResult['team_id'];
                $team = Team::find($teamId);

                if (! $team || ! $team->owner)
                {
                    $this->warn("⚠️  Team {$teamId} has no owner. Skipping individual report.");

                    continue;
                }

                $ownerEmail = $team->owner->email;
                $onlyFailures = $this->option('failures-only');

                // Skip if only-failures is set but this team has no failures
                if ($onlyFailures && $teamResult['summary']['failed'] === 0)
                {
                    continue;
                }

                // Use custom email if provided, otherwise use team owner's email
                $recipientEmail = $this->option('report-email') ?: $ownerEmail;

                $this->sendIndividualTeamReport($teamResult, $recipientEmail);
            } catch (\Exception $e)
            {
                $this->error("Failed to send report for team {$teamResult['team_name']}: ".$e->getMessage());
                Log::error('Failed to send individual team report', [
                    'team_id' => $teamResult['team_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function sendIndividualTeamReport($teamResult, $recipientEmail)
    {
        $teamName = $teamResult['team_name'];
        $summary = $teamResult['summary'];

        try
        {
            // Send the actual email
            Mail::to($recipientEmail)->send(new TeamConfigurationReport(
                $teamResult,
                $this->option('failures-only'),
            ));

            $this->info("📧 Report for '{$teamName}' sent successfully to: {$recipientEmail}");

            Log::info('Individual team configuration report sent', [
                'team_id' => $teamResult['team_id'],
                'team_name' => $teamName,
                'recipient' => $recipientEmail,
                'passed' => $summary['passed'],
                'failed' => $summary['failed'],
                'skipped' => $summary['skipped'],
                'status' => 'sent',
            ]);
        } catch (\Exception $e)
        {
            $this->error("Failed to send email to {$recipientEmail}: ".$e->getMessage());

            Log::error('Failed to send individual team report email', [
                'team_id' => $teamResult['team_id'],
                'team_name' => $teamName,
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendAdminSummaryReport()
    {
        try
        {
            $adminEmail = config('app.notification_email');

            if (! $adminEmail)
            {
                $this->warn('No notification email configured. Skipping admin summary.');

                return;
            }

            $totalTeams = count($this->results);
            $teamsWithFailures = collect($this->results)->filter(function ($result)
            {
                return $result['summary']['failed'] > 0;
            })->count();

            $this->info("📧 Admin summary would be sent to: {$adminEmail}");

            Log::info('Admin team configuration summary prepared', [
                'recipient' => $adminEmail,
                'total_teams' => $totalTeams,
                'teams_with_failures' => $teamsWithFailures,
                'total_tests' => $this->totalTests,
                'total_failures' => $this->failedTests,
                'summary' => $this->results,
            ]);

            // Here you would implement the actual admin email sending
            /*
            Mail::to($adminEmail)->send(new AdminConfigurationSummary([
                'results' => $this->results,
                'total_teams' => $totalTeams,
                'teams_with_failures' => $teamsWithFailures,
                'total_failures' => $this->failedTests
            ]));
            */
        } catch (\Exception $e)
        {
            $this->error('Failed to send admin summary: '.$e->getMessage());
            Log::error('Failed to send admin configuration summary', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
