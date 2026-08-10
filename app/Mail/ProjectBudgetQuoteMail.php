<?php

namespace App\Mail;

use App\Helpers\Helpers;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProjectBudgetQuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public string $recipientName,
        public string $previewUrl,
        public string $trackingToken,
    ) {}

    public function build(): self
    {
        $projectName = trim((string) ($this->project->real_name ?: $this->project->name));
        $trackedPreviewUrl = route('project.budget-email.track-click', $this->trackingToken);
        $trackingPixelUrl = route('project.budget-email.track-open', $this->trackingToken);

        return $this->subject(__('Your project quote is ready: :project', ['project' => $projectName]))
            ->view('emails.project-budget-quote', [
                'recipientName' => $this->recipientName,
                'projectName' => $projectName,
                'enterpriseName' => trim((string) (optional($this->project->enterprise)->name ?? '')),
                'previewUrl' => $trackedPreviewUrl,
                'trackingPixelUrl' => $trackingPixelUrl,
                'logoUrl' => url(Helpers::logoAsset('dark')),
                'appName' => (string) config('app.name'),
            ]);
    }
}
