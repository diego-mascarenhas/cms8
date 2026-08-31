<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WhatsAppSessionWindowClosedException;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\TeamWhatsAppConnectionSync;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppCustomerServiceWindow;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Support\WhatsAppSendExceptionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeamWhatsAppController extends Controller
{
    /**
     * Send a WhatsApp message using the team's configured provider (local or Twilio).
     */
    public function send(Request $request): JsonResponse
    {
        /** @var Team|null $team */
        $team = $request->attributes->get('team');
        if (! $team)
        {
            return response()->json(['success' => false, 'message' => 'Team not found'], 401);
        }

        $validated = $request->validate([
            'to' => 'required|string|max:20',
            'message' => 'required|string|max:4096',
        ]);

        $digits = preg_replace('/[^0-9]/', '', $validated['to']);
        if (strlen($digits) < 10 || strlen($digits) > 15)
        {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number (expected 10–15 digits).',
            ], 422);
        }

        if ($team->usesLocalWhatsApp())
        {
            $baseUrl = $team->getWhatsAppServiceBaseUrl();
            if ($baseUrl !== '')
            {
                $gateway = new LocalWhatsAppGateway(
                    $baseUrl,
                    (string) config('whatsapp.local.webhook_secret'),
                    $team->id,
                );
                $connectionStatus = $gateway->getConnectionStatus();
                if (is_array($connectionStatus))
                {
                    TeamWhatsAppConnectionSync::syncLinkedNumberFromGatewayStatus($team, $connectionStatus);
                }
                $status = is_array($connectionStatus) ? (string) ($connectionStatus['status'] ?? '') : '';
                if (! in_array($status, ['connected', 'open'], true))
                {
                    return response()->json([
                        'success' => false,
                        'error' => __('whatsapp.send.error.not_connected'),
                    ], 503);
                }
            }
        }

        try
        {
            app(WhatsAppCustomerServiceWindow::class)->assertOpen($digits);
        } catch (WhatsAppSessionWindowClosedException $e)
        {
            return response()->json([
                'success' => false,
                'error' => WhatsAppSendExceptionPresenter::messageForUser($e),
            ], 422);
        }

        try
        {
            $service = new WhatsAppMessageService($team);
            $service->sendWhatsApp($digits, $validated['message'], ['source' => 'api_team']);

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp message sent',
                'to' => $digits,
            ]);
        } catch (\Throwable $e)
        {
            Log::warning('Team API WhatsApp send failed', [
                'team_id' => $team->id,
                'to' => $digits,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => WhatsAppSendExceptionPresenter::messageForUser($e),
            ], 500);
        }
    }
}
