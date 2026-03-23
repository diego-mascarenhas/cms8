<?php

namespace App\Jobs;

use App\Helpers\GrapesJsHelper;
use App\Models\Team;
use App\Models\Template;
use App\Services\TemplateHtmlGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class GenerateTemplateHtmlJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    private const CACHE_KEY_PREFIX = 'template_gen_result_';

    public const CACHE_TTL_SECONDS = 600;

    public static function cacheKey(string $resultToken): string
    {
        return self::CACHE_KEY_PREFIX.$resultToken;
    }

    public function __construct(
        public string $prompt,
        public ?int $teamId,
        public string $resultToken,
        public ?int $templateId = null,
    ) {}

    public function handle(TemplateHtmlGenerationService $service): void
    {
        $team = $this->teamId ? Team::find($this->teamId) : null;
        $result = $service->generate($this->prompt, $team);

        if ($result['success'] && ! empty($result['html']))
        {
            $payload = ['status' => 'completed', 'html' => $result['html']];
            if ($this->resultToken !== '')
            {
                Cache::put(self::cacheKey($this->resultToken), $payload, self::CACHE_TTL_SECONDS);
            }
            if ($this->templateId !== null)
            {
                $template = Template::withoutGlobalScopes()->find($this->templateId);
                if ($template)
                {
                    $template->update([
                        'gjs_data' => ['html' => $result['html'], 'css' => ''],
                    ]);
                    GrapesJsHelper::fixTemplateStructure($template->fresh());
                }
            }
        } else
        {
            $payload = ['status' => 'failed', 'error' => $result['error'] ?? 'Generation failed.'];
            if ($this->resultToken !== '')
            {
                Cache::put(self::cacheKey($this->resultToken), $payload, self::CACHE_TTL_SECONDS);
            }
        }
    }

    public static function getResult(string $resultToken): ?array
    {
        return Cache::get(self::cacheKey($resultToken));
    }
}
