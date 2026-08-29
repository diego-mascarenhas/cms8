<?php

namespace App\Services\Mail;

use App\Enums\CampaignStatus;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Team;

class MessageCampaignActivationService
{
    /**
     * @return array{success: bool, message: string}
     */
    public function activate(Message $message, Team $team): array
    {
        try
        {
            $team->unsetRelation('settings');
            $team->load('settings');

            if (! $team->hasOutgoingEmailSenderConfigured())
            {
                return [
                    'success' => false,
                    'message' => __('app.email_sender_activation_blocked'),
                ];
            }

            if (! $team->canSendEmails(1))
            {
                if ($team->contacts()->count() > $team->getContactLimit())
                {
                    return [
                        'success' => false,
                        'message' => __('Alcanzaste el límite de suscriptores del plan. Actualizá el plan para enviar a más contactos.'),
                    ];
                }

                return [
                    'success' => false,
                    'message' => __('No quedan emails incluidos en el plan. Contratá Basic, Foundation o Scale.'),
                ];
            }

            $updateData = ['status_id' => 1];

            if (! $message->started_at)
            {
                $updateData['started_at'] = now();
            }

            $message->update($updateData);

            $message->load('campaigns');
            foreach ($message->campaigns as $campaign)
            {
                $campaignStatus = CampaignStatus::tryFrom($campaign->status);
                if ($campaignStatus !== CampaignStatus::Sent && $campaignStatus !== CampaignStatus::Scheduled)
                {
                    $campaign->update(['status' => CampaignStatus::Active->value]);
                }
            }

            $contactsCount = $message->audienceContactsQuery()->count();

            $pendingDeliveries = MessageDelivery::where('message_id', $message->id)
                ->where(function ($query)
                {
                    $query->whereNull('sent_at')
                        ->orWhere('sent_at', '>', now());
                })
                ->count();

            $responseMessage = 'Campaña activada exitosamente. ';

            if ($pendingDeliveries > 0)
            {
                $responseMessage .= "{$pendingDeliveries} envíos pendientes serán enviados por el programador.";
            } else
            {
                $responseMessage .= "{$contactsCount} contactos serán procesados por el programador.";
            }

            return [
                'success' => true,
                'message' => $responseMessage,
            ];
        } catch (\Exception $e)
        {
            return [
                'success' => false,
                'message' => 'Error al iniciar campaña: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function pause(Message $message): array
    {
        try
        {
            $message->update(['status_id' => 0]);

            return [
                'success' => true,
                'message' => 'Campaña pausada exitosamente',
            ];
        } catch (\Exception $e)
        {
            return [
                'success' => false,
                'message' => 'Error al pausar campaña: '.$e->getMessage(),
            ];
        }
    }
}
