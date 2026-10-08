<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\ApolloService;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\Prospecting\InsufficientProspectCredits;
use App\Services\Prospecting\ProspectContactImporter;
use App\Support\HumanoPricingCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailerProspectController extends Controller
{
    use ChecksTeamModule;

    public function show(Request $request): JsonResponse
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

        return response()->json($this->accessPayload($team));
    }

    public function search(Request $request, ApolloService $apollo, ProspectContactImporter $importer): JsonResponse
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

        if ($locked = $this->lockedResponse($team))
        {
            return $locked;
        }

        $validated = $request->validate([
            'person_titles' => 'nullable|array',
            'person_titles.*' => 'string|max:255',
            'person_locations' => 'nullable|array',
            'person_locations.*' => 'string|max:255',
            'person_seniorities' => 'nullable|array',
            'person_seniorities.*' => 'string|max:50',
            'organization_locations' => 'nullable|array',
            'organization_locations.*' => 'string|max:255',
            'q_organization_domains_list' => 'nullable|array',
            'q_organization_domains_list.*' => 'string|max:255',
            'q_keywords' => 'nullable|string|max:500',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:25',
        ]);

        $filters = array_filter([
            'person_titles' => $validated['person_titles'] ?? null,
            'person_locations' => $validated['person_locations'] ?? null,
            'person_seniorities' => $validated['person_seniorities'] ?? null,
            'organization_locations' => $validated['organization_locations'] ?? null,
            'q_organization_domains_list' => $validated['q_organization_domains_list'] ?? null,
            'q_keywords' => $validated['q_keywords'] ?? null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        if ($filters === [])
        {
            return response()->json([
                'message' => 'Indicá al menos un filtro para buscar.',
            ], 422);
        }

        try
        {
            $result = $apollo->searchPeople(
                $filters,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 25),
            );
        } catch (\RuntimeException $exception)
        {
            $status = $exception->getCode() >= 400 && $exception->getCode() < 600 ? (int) $exception->getCode() : 502;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        $people = array_map(function (array $person) use ($importer): array
        {
            $raw = is_array($person['apollo_raw'] ?? null) ? $person['apollo_raw'] : $person;
            $person['credits'] = $importer->creditsCost($raw);

            return $person;
        }, $result['people'] ?? []);

        return response()->json([
            'people' => $people,
            'total_entries' => (int) ($result['total_entries'] ?? 0),
            'page' => (int) ($result['page'] ?? 1),
            'per_page' => (int) ($result['per_page'] ?? 25),
            'credits' => $team->fresh()->getRemainingProspectCredits(),
        ]);
    }

    public function import(Request $request, ProspectContactImporter $importer): JsonResponse
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

        if ($locked = $this->lockedResponse($team))
        {
            return $locked;
        }

        $validated = $request->validate([
            'people' => 'required|array|min:1|max:25',
            'people.*.id' => 'required|string|max:100',
            'people.*.first_name' => 'nullable|string|max:255',
            'people.*.last_name' => 'nullable|string|max:255',
            'people.*.last_name_obfuscated' => 'nullable|string|max:255',
            'people.*.title' => 'nullable|string|max:500',
            'people.*.organization_name' => 'nullable|string|max:500',
            'people.*.apollo_raw' => 'nullable|array',
            'category_id' => 'nullable|integer',
        ]);

        $imported = [];
        $skipped = 0;
        $message = null;
        $userId = (int) $request->user()->id;
        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;

        foreach ($validated['people'] as $person)
        {
            $raw = is_array($person['apollo_raw'] ?? null) ? $person['apollo_raw'] : null;

            try
            {
                $contact = $importer->import(
                    $team,
                    $userId,
                    (string) $person['id'],
                    trim((string) ($person['first_name'] ?? '')) ?: 'Contact',
                    $person['last_name'] ?? null,
                    $person['last_name_obfuscated'] ?? null,
                    $person['title'] ?? null,
                    $person['organization_name'] ?? null,
                    $raw,
                    $categoryId,
                );
            } catch (InsufficientProspectCredits $exception)
            {
                $message = $exception->getMessage();
                break;
            }

            $imported[] = [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
            ];
        }

        if ($message !== null)
        {
            $skipped = count($validated['people']) - count($imported);
        }

        if ($imported === [])
        {
            return response()->json([
                'message' => $message ?? 'No se importó ningún contacto.',
                'imported' => [],
                'skipped' => $skipped,
                'credits' => $team->fresh()->getRemainingProspectCredits(),
            ], 402);
        }

        return response()->json([
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'credits' => $team->fresh()->getRemainingProspectCredits(),
        ], 201);
    }

    /**
     * @return array{enabled: bool, reason: string|null, credits: int}
     */
    private function accessPayload(Team $team): array
    {
        $enabled = $this->prospectingEnabled($team);

        return [
            'enabled' => $enabled,
            'reason' => $enabled ? null : 'La prospección se habilita al contratar el plan.',
            'credits' => $team->getRemainingProspectCredits(),
        ];
    }

    private function lockedResponse(Team $team): ?JsonResponse
    {
        $access = $this->accessPayload($team);
        if ($access['enabled'])
        {
            return null;
        }

        return response()->json([
            'message' => $access['reason'],
            'enabled' => false,
            'credits' => $access['credits'],
        ], 403);
    }

    private function prospectingEnabled(Team $team): bool
    {
        $access = app(AssistantSubscriptionService::class)->accessForCatalog($team, HumanoPricingCatalog::MAILER);

        return ($access['status'] ?? null) === 'paid';
    }
}
