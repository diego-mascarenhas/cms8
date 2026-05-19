<?php

namespace App\Services;

use App\Helpers\GrapesJsHelper;
use App\Models\Template;
use Illuminate\Support\Facades\DB;

class MessageFormTemplateResolver
{
    public function resolveForForm(?int $preferredTemplateId, int $teamId, bool $autoPickWhenMissing = true): ?Template
    {
        if ($preferredTemplateId !== null && $preferredTemplateId > 0)
        {
            $preferred = $this->queryForTeam($teamId)->whereKey($preferredTemplateId)->first();
            if ($preferred instanceof Template)
            {
                return $preferred;
            }
        }

        if (! $autoPickWhenMissing)
        {
            return null;
        }

        $existing = $this->queryForTeam($teamId)->orderBy('id')->first();
        if ($existing instanceof Template)
        {
            return $existing;
        }

        return $this->createDefaultForTeam($teamId);
    }

    public function createDefaultForTeam(int $teamId): Template
    {
        return DB::transaction(function () use ($teamId): Template
        {
            $existing = $this->queryForTeam($teamId)->lockForUpdate()->orderBy('id')->first();
            if ($existing instanceof Template)
            {
                return $existing;
            }

            $template = Template::withoutGlobalScopes()->create([
                'name' => __('app.message_default_email_template_name'),
                'team_id' => $teamId,
                'status_id' => 1,
                'gjs_data' => [
                    'css' => '* { box-sizing: border-box; } body { margin: 0; font-family: Arial, sans-serif; line-height: 1.5; color: #333; }',
                    'html' => '<body><h1>'.e(__('app.message_default_email_template_heading')).'</h1><p>'.e(__('app.message_default_email_template_body')).'</p></body>',
                    'styles' => '[]',
                    'components' => '[]',
                ],
            ]);

            GrapesJsHelper::fixTemplateStructure($template);

            return $template->fresh();
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Template>
     */
    private function queryForTeam(int $teamId)
    {
        return Template::withoutGlobalScopes()->where('team_id', $teamId);
    }
}
