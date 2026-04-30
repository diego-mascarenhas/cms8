<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CampaignClassicEditorPersistence
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{campaign_id: int, message_id: int}
     */
    public function persist(User $user, array $validated): array
    {
        $teamId = (int) $user->current_team_id;

        return DB::transaction(function () use ($validated, $teamId): array
        {
            $type = CampaignType::tryFrom((string) ($validated['type'] ?? '')) ?? CampaignType::Broadcasts;

            $campaignTitle = trim((string) ($validated['title'] ?? ''));
            if ($campaignTitle === '')
            {
                $campaignTitle = trim((string) ($validated['internal_title'] ?? ''));
            }
            if ($campaignTitle === '')
            {
                $campaignTitle = __('Campaña sin título');
            }

            $campaignId = (int) ($validated['campaign_id'] ?? 0);
            if ($campaignId > 0)
            {
                $campaign = Campaign::withoutGlobalScopes()
                    ->where('team_id', $teamId)
                    ->where('id', $campaignId)
                    ->first();
                if (! $campaign)
                {
                    throw ValidationException::withMessages([
                        'campaign_id' => __('La campaña no existe o no pertenece a tu equipo.'),
                    ]);
                }
            } else
            {
                $campaign = Campaign::withoutGlobalScopes()->firstOrCreate(
                    [
                        'team_id' => $teamId,
                        'name' => $campaignTitle,
                        'type' => $type->value,
                    ],
                    [
                        'status' => CampaignStatus::Paused->value,
                        'summary' => Str::limit((string) ($validated['subject'] ?? ''), 180),
                    ],
                );
            }

            $subject = trim((string) ($validated['subject'] ?? ''));
            if ($subject !== '')
            {
                $campaign->summary = Str::limit($subject, 180);
                $campaign->save();
            }

            $templateId = (int) ($validated['template_id'] ?? 0);
            $template = null;
            if ($templateId > 0)
            {
                $template = Template::withoutGlobalScopes()
                    ->where('team_id', $teamId)
                    ->where('id', $templateId)
                    ->first();
                if (! $template)
                {
                    throw ValidationException::withMessages([
                        'template_id' => __('La plantilla no existe o no pertenece a tu equipo.'),
                    ]);
                }

                $body = (string) ($validated['body'] ?? '');
                if ($body !== '')
                {
                    $gjsData = is_array($template->gjs_data) ? $template->gjs_data : [];
                    $gjsData['html'] = $body;
                    $template->update(['gjs_data' => $gjsData]);
                }
            }

            $internalTitle = trim((string) ($validated['internal_title'] ?? ''));
            if ($internalTitle === '')
            {
                $internalTitle = $campaignTitle;
            }

            $previewText = trim((string) ($validated['preview_text'] ?? ''));
            $messageText = $previewText !== '' ? $previewText : ($subject !== '' ? $subject : ' ');

            $messageId = (int) ($validated['message_id'] ?? 0);
            $message = null;
            if ($messageId > 0)
            {
                $message = Message::withoutGlobalScopes()
                    ->where('team_id', $teamId)
                    ->where('id', $messageId)
                    ->first();
                if (! $message)
                {
                    throw ValidationException::withMessages([
                        'message_id' => __('El mensaje no existe o no pertenece a tu equipo.'),
                    ]);
                }

                $message->update([
                    'name' => $internalTitle,
                    'text' => $messageText,
                    'template_id' => $template?->id ?? $message->template_id,
                ]);
            } else
            {
                DB::table('message_type')->updateOrInsert(
                    ['id' => 1],
                    ['name' => 'Mailer', 'status' => 1],
                );

                $message = Message::withoutGlobalScopes()->create([
                    'name' => $internalTitle,
                    'type_id' => 1,
                    'text' => $messageText,
                    'team_id' => $teamId,
                    'template_id' => $template?->id,
                    'status_id' => false,
                ]);
            }

            $campaign->messages()->syncWithoutDetaching([$message->id]);

            return [
                'campaign_id' => (int) $campaign->id,
                'message_id' => (int) $message->id,
            ];
        });
    }
}
