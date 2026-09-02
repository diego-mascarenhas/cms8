<?php

namespace App\Mail;

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
        $this->previewUrl = route('project.budget-email.track-click', $this->trackingToken);
        $trackingPixelUrl = route('project.budget-email.track-open', $this->trackingToken);

        return $this->subject(__('Quote: :project', ['project' => $projectName]))
            ->view('emails.project-budget-quote', [
                'recipientName' => $this->recipientName,
                'projectName' => $projectName,
                'requestSummary' => $this->requestSummary(),
                'sections' => $this->quoteSections(),
                'previewUrl' => $this->previewUrl,
                'trackingPixelUrl' => $trackingPixelUrl,
            ]);
    }

    private function requestSummary(): string
    {
        $data = is_array($this->project->data) ? $this->project->data : [];
        $candidates = [
            data_get($data, 'ai_interpretation', ''),
            strip_tags((string) ($this->project->description ?? '')),
            data_get($data, 'budget_given', ''),
            data_get($data, 'intake.scope', ''),
        ];

        foreach ($candidates as $candidate)
        {
            $text = $this->generalizeSummary($this->firstParagraph((string) $candidate));
            if ($text !== '')
            {
                return $this->limitSummary($text);
            }
        }

        return '';
    }

    private function generalizeSummary(string $text): string
    {
        $general = preg_replace(
            '/^(?:el\s+cliente\s+|the\s+client\s+|se\s+)?(?:solicita|pide|requiere|necesita|quiere|busca|requests|needs|wants|asks(?:\s+for)?|is\s+looking\s+for)\s*:?\s+/iu',
            '',
            $text,
            1,
        );
        $general = trim((string) $general);
        if ($general === '' || $general === $text)
        {
            return $text;
        }

        return mb_strtoupper(mb_substr($general, 0, 1)).mb_substr($general, 1);
    }

    private function firstParagraph(string $text): string
    {
        $clean = trim(html_entity_decode(strip_tags($text)));
        $paragraphs = preg_split("/\r\n|\n|\r/", $clean) ?: [];
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

        return $paragraphs[0] ?? '';
    }

    private function limitSummary(string $text, int $max = 420): string
    {
        if (mb_strlen($text) <= $max)
        {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        if (preg_match('/^(.*[\.!?])\s/u', $cut, $matches) && mb_strlen($matches[1]) >= 80)
        {
            return $matches[1];
        }

        return rtrim($cut).'…';
    }

    /**
     * @return list<string>
     */
    private function splitParagraphs(string $text): array
    {
        $blocks = preg_split("/\r\n|\n|\r/", trim($text)) ?: [];
        $blocks = array_values(array_filter(array_map('trim', $blocks)));
        $paragraphs = [];

        foreach ($blocks as $block)
        {
            foreach ($this->splitParagraphBlock($block) as $part)
            {
                $paragraphs[] = $part;
            }
        }

        return $paragraphs;
    }

    /**
     * @return list<string>
     */
    private function splitParagraphBlock(string $text): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($normalized === '')
        {
            return [];
        }

        $byPhase = preg_split('/(?=\bFase\s+\d+)/iu', $normalized) ?: [];
        $byPhase = array_values(array_filter(array_map('trim', $byPhase)));
        if (count($byPhase) > 1)
        {
            return $byPhase;
        }

        $sentences = preg_split('/(?<=[.!?])\s+(?=\p{Lu}|\d)/u', $normalized) ?: [];

        return array_values(array_filter(array_map('trim', $sentences)));
    }

    /**
     * @return list<array{title: string, paragraphs: list<string>}>
     */
    private function quoteSections(): array
    {
        $data = is_array($this->project->data) ? $this->project->data : [];
        $sections = [];

        foreach ([
            [__('Dimension'), data_get($data, 'dimension', '')],
            [__('Times'), data_get($data, 'estimated_times', '')],
            [__('Resources'), data_get($data, 'resources', '')],
        ] as [$title, $text])
        {
            $paragraphs = $this->splitParagraphs((string) $text);
            if ($paragraphs === [])
            {
                continue;
            }

            $sections[] = [
                'title' => $title,
                'paragraphs' => $paragraphs,
            ];
        }

        return $sections;
    }
}
