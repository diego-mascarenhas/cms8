<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactInteractionRequest;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Opportunity;
use Illuminate\Http\RedirectResponse;

class ContactInteractionController extends Controller
{
    public function store(StoreContactInteractionRequest $request, string $id): RedirectResponse
    {
        $contact = Contact::query()->findOrFail($id);

        $this->authorize('logInteraction', $contact);

        $validated = $request->validated();
        $opportunityId = $validated['opportunity_id'] ?? null;
        unset($validated['opportunity_id']);

        $interaction = new ContactInteraction($validated);
        $interaction->contact_id = $contact->id;
        $interaction->user_id = auth()->id();

        if ($opportunityId)
        {
            $opportunity = Opportunity::withoutGlobalScopes()->findOrFail($opportunityId);
            if ((int) $opportunity->contact_id !== (int) $contact->id)
            {
                abort(403);
            }
            if ((int) $opportunity->team_id !== (int) auth()->user()->currentTeam->id)
            {
                abort(403);
            }
            $this->authorize('view', $opportunity);
            $interaction->relatable()->associate($opportunity);
        }

        $interaction->save();

        return redirect()
            ->route('contact.show', $contact->id)
            ->with('success', __('Interaction recorded.'));
    }
}
