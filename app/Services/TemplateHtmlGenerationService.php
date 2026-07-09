<?php

namespace App\Services;

use App\Models\Team;
use App\Support\AiTasks;

use function Laravel\Ai\agent;

/**
 * Generates email template HTML from a natural language prompt using Laravel AI.
 * Output is suitable for loading into GrapesJS via setComponents().
 */
class TemplateHtmlGenerationService
{
    /** Placeholder replaced at render time with the app logo URL. Do not change. */
    public const LOGO_URL_PLACEHOLDER = '{{APP_LOGO_URL}}';

    private const SYSTEM_INSTRUCTIONS = <<<'TEXT'
You are an email template HTML generator. The output will be loaded into GrapesJS (a visual block editor), so keep the structure clean and avoid unnecessary wrappers that would make editing harder.

Your response must be ONLY valid HTML suitable for email clients.

Rules:
- Output ONLY the HTML fragment (no <html>, <head>, or <body> tags).
- Use table-based layout for compatibility with email clients.
- Use inline styles (style="...") for all visual properties.
- Use semantic structure: tables for layout, cells for sections. Prefer simple, editable blocks (e.g. one table per section) so GrapesJS can parse and edit them easily.
- Common elements: logo area, headline, paragraph text, CTA button (styled <a>), footer.
- Do not wrap the output in markdown code blocks or add any explanation. Return raw HTML only.
- IMAGES: Do NOT use external image URLs (no placeholder.com, picsum.photos, or any other external domain). For logos, use exactly this placeholder in the img src: {{APP_LOGO_URL}} (the application will replace it with the real logo). For other images, use a styled div with placeholder text (e.g. "Image") or the same {{APP_LOGO_URL}} if a generic image is needed; never use external URLs.
TEXT;

    /**
     * Build company/team context string for the AI from team settings.
     */
    public static function buildCompanyContext(?Team $team): string
    {
        if (! $team)
        {
            return '';
        }

        $parts = ['Team/Company: '.$team->name];

        $config = $team->getSetting('business_config', []);
        if (is_string($config))
        {
            $config = json_decode($config, true) ?: [];
        }
        $keys = ['business_name', 'business_industry', 'business_tagline', 'business_description', 'business_website'];
        foreach ($keys as $key)
        {
            $value = $config[$key] ?? null;
            if ($value !== null && $value !== '')
            {
                $label = str_replace('_', ' ', ucfirst($key));
                $parts[] = $label.': '.(is_string($value) ? trim($value) : json_encode($value));
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Generate email template HTML from a user prompt.
     * Optionally pass the current team so the AI can use company context (name, business config) in the template.
     *
     * @return array{success: bool, html?: string, error?: string}
     */
    public function generate(string $prompt, ?Team $team = null): array
    {
        try
        {
            $companyContext = self::buildCompanyContext($team);
            $userPrompt = $prompt;
            if ($companyContext !== '')
            {
                $userPrompt = "Company context (use this to personalize the template):\n".$companyContext."\n\nUser request: ".$prompt;
            }

            $agent = agent(
                instructions: self::SYSTEM_INSTRUCTIONS,
                messages: [],
                tools: [],
            );

            $response = $agent->prompt($userPrompt, [], AiTasks::provider('template'));

            if ($team !== null)
            {
                TokenUsageLogService::logFromAiResponse(
                    teamId: (int) $team->id,
                    service: 'TemplateHtmlGenerationService',
                    usage: $response->usage ?? null,
                    moduleKey: 'templates',
                    inputSize: strlen($userPrompt),
                );
            }

            $text = trim($response->text ?? '');

            if ($text === '')
            {
                return ['success' => false, 'error' => 'AI returned empty response.'];
            }

            $html = $this->extractHtml($text);

            if ($html === '')
            {
                return ['success' => false, 'error' => 'Could not extract HTML from AI response.'];
            }

            return ['success' => true, 'html' => $html];
        } catch (\Throwable $e)
        {
            return [
                'success' => false,
                'error' => 'Generation failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Extract HTML from response, stripping markdown code blocks if present.
     * Uses simple string search when regex is not needed, to avoid pattern errors.
     */
    private function extractHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '')
        {
            return '';
        }

        $start = strpos($text, '```');
        if ($start !== false)
        {
            $afterFence = substr($text, $start + 3);
            $labelEnd = strpos($afterFence, "\n");
            if ($labelEnd !== false)
            {
                $afterFence = substr($afterFence, $labelEnd + 1);
            }
            $end = strpos($afterFence, '```');
            if ($end !== false)
            {
                return trim(substr($afterFence, 0, $end));
            }
        }

        return $text;
    }
}
