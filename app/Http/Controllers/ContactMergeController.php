<?php

namespace App\Http\Controllers;

use App\Exceptions\ContactMergeException;
use App\Http\Requests\MergeContactRequest;
use App\Models\Contact;
use App\Services\ContactMergeService;
use App\Support\SearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMergeController extends Controller
{
    public function candidates(Request $request, string $id): JsonResponse
    {
        $contact = $this->contactForUpdate($id);
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2)
        {
            return response()->json(['contacts' => []]);
        }

        $query = Contact::query()
            ->where('team_id', $contact->team_id)
            ->where('id', '!=', $contact->id)
            ->with(['enterprises:id,name']);
        SearchNormalizer::applyContactNavbarConditions($query, $term);

        $contacts = $query
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'surname', 'email', 'phone']);

        return response()->json([
            'contacts' => $contacts->map(function (Contact $row): array
            {
                $companies = $row->enterprises
                    ->map(function ($enterprise): string
                    {
                        $position = trim((string) ($enterprise->pivot->position ?? ''));

                        return $position !== ''
                            ? $enterprise->name.' ('.$position.')'
                            : $enterprise->name;
                    })
                    ->implode(', ');

                return [
                    'id' => $row->id,
                    'name' => trim($row->name.' '.($row->surname ?? '')),
                    'subtitle' => $companies !== ''
                        ? $companies
                        : ($row->email ? (string) $row->email : (string) ($row->phone ?? '')),
                ];
            })->values(),
        ]);
    }

    public function preview(Request $request, string $id, ContactMergeService $merge): JsonResponse
    {
        $current = $this->contactForUpdate($id);
        $other = $this->otherContact($current, (int) $request->query('contact_id'));

        return response()->json($merge->preview($current, $other));
    }

    public function store(MergeContactRequest $request, string $id, ContactMergeService $merge): JsonResponse
    {
        $current = $this->contactForUpdate($id);
        $other = $this->otherContact($current, (int) $request->validated('contact_id'));

        $preview = $merge->preview($current, $other);
        if ($preview['blocked'])
        {
            return response()->json([
                'success' => false,
                'message' => $preview['message'],
            ], 422);
        }

        try
        {
            $survivor = $merge->merge($current, $other);
        } catch (ContactMergeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $preview['message'],
            'lines' => $preview['lines'],
            'redirect' => route('contact.show', $survivor->id),
        ]);
    }

    private function contactForUpdate(string $id): Contact
    {
        $contact = Contact::query()
            ->where('id', $id)
            ->where('team_id', auth()->user()->currentTeam->id)
            ->firstOrFail();

        $this->authorize('update', $contact);

        return $contact;
    }

    private function otherContact(Contact $current, int $otherId): Contact
    {
        $other = Contact::query()
            ->where('id', $otherId)
            ->where('team_id', $current->team_id)
            ->firstOrFail();

        $this->authorize('update', $other);

        return $other;
    }
}
