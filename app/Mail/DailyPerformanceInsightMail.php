<?php

namespace App\Mail;

use App\Models\UserDailyPerformanceInsight;
use App\Services\DailyTeamDigestMetricsCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyPerformanceInsightMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserDailyPerformanceInsight $insight,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.performance_digest_email_subject', [
                'date' => $this->insight->insight_date->format('d/m/Y'),
            ]),
        );
    }

    public function content(): Content
    {
        $snapshot = $this->insight->context_snapshot ?? [];
        $highlights = $snapshot['highlights'] ?? [];
        $userActivity = $snapshot['user_activity'] ?? [];
        $dailyTasks = $snapshot['tasks']['daily_items'] ?? [];

        $insight = $this->insight->loadMissing(['team', 'user']);
        $team = $insight->team;
        $user = $insight->user;

        if ($team !== null && $user !== null && ($dailyTasks === [] || ($snapshot['digest_version'] ?? 0) < DailyTeamDigestMetricsCollector::DIGEST_VERSION))
        {
            $freshDigest = app(DailyTeamDigestMetricsCollector::class)->collect(
                $user,
                $team,
                $insight->insight_date,
            );
            $highlights = $freshDigest['highlights'] ?? $highlights;
            $userActivity = $freshDigest['user_activity'] ?? $userActivity;
            $dailyTasks = $freshDigest['tasks']['daily_items'] ?? $dailyTasks;
        }

        return new Content(
            markdown: 'mail.daily-performance-insight',
            with: [
                'insight' => $insight,
                'highlights' => $highlights,
                'userActivity' => $userActivity,
                'dailyTasks' => $dailyTasks,
                'tasksModuleEnabled' => $team?->hasModule('tasks') ?? false,
            ],
        );
    }
}
