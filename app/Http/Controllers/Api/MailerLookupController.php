<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailerLookupController extends Controller
{
    use ChecksTeamModule;

    public function index(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $contactsModuleId = Module::query()->where('key', 'contacts')->value('id');

        $categories = Category::getOptions($team->id, null, $contactsModuleId)
            ->values()
            ->all();

        $contactStatuses = ContactStatus::query()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (ContactStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
            ])
            ->values()
            ->all();

        $templates = Template::query()
            ->where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Template $template): array => [
                'id' => $template->id,
                'name' => $template->name,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'contact_statuses' => $contactStatuses,
                'templates' => $templates,
            ],
        ]);
    }
}
