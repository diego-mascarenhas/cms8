<?php

namespace App\Http\Controllers;

use App\DataTables\TicketDataTable;
use App\Http\Requests\AddTicketResponseRequest;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\RateTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketPriorityRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Ticket;
use App\Models\TicketRating;
use App\Models\TicketResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(TicketDataTable $dataTable): mixed
    {
        $this->authorize('viewAny', Ticket::class);

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        return $dataTable->render('ticket.index');
    }

    public function create()
    {
        $this->authorize('create', Ticket::class);

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        return view('ticket.create');
    }

    public function store(StoreTicketRequest $request)
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $ticket = Ticket::create([
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
            'subject' => $request->subject,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => 'open',
        ]);

        if ($request->hasFile('attachments'))
        {
            foreach ($request->file('attachments') as $file)
            {
                $ticket->addMedia($file)->toMediaCollection('attachments');
            }
        }

        return redirect()->route('ticket.show', $ticket->id)
            ->with('success', __('tickets.Ticket created successfully.'));
    }

    public function show(int $id)
    {
        $ticket = Ticket::with(['responses.user', 'user', 'assignedTo', 'rating'])
            ->findOrFail($id);

        $this->authorize('view', $ticket);

        $teamUsers = auth()->user()->currentTeam?->allUsers() ?? collect();

        return view('ticket.show', compact('ticket', 'teamUsers'));
    }

    public function addResponse(AddTicketResponseRequest $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);

        $isInternalNote = $request->boolean('is_internal_note');

        $response = TicketResponse::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_internal_note' => $isInternalNote,
        ]);

        if ($request->hasFile('attachments'))
        {
            foreach ($request->file('attachments') as $file)
            {
                $response->addMedia($file)->toMediaCollection('attachments');
            }
        }

        if (! $isInternalNote)
        {
            if (in_array($ticket->status, ['open', 'waiting_client']))
            {
                $ticket->update(['status' => 'in_progress']);
            } elseif ($ticket->status === 'in_progress')
            {
                $ticket->update(['status' => 'waiting_client']);
            }
        }

        return back()->with('success', $isInternalNote ? __('tickets.Internal note added.') : __('tickets.Response added successfully.'));
    }

    public function updateStatus(UpdateTicketStatusRequest $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);

        $data = ['status' => $request->status];
        if ($request->status === 'closed')
        {
            $data['closed_at'] = now();
        }

        $ticket->update($data);

        return back()->with('success', __('tickets.Status updated.'));
    }

    public function assign(AssignTicketRequest $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);

        $ticket->update([
            'assigned_to' => $request->assigned_to,
            'status' => $request->assigned_to ? 'in_progress' : 'open',
        ]);

        return back()->with('success', __('tickets.Ticket assigned.'));
    }

    public function updatePriority(UpdateTicketPriorityRequest $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->update(['priority' => $request->priority]);

        return back()->with('success', __('tickets.Priority updated.'));
    }

    public function close(int $id)
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('update', $ticket);

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return back()->with('success', __('tickets.Ticket closed.'));
    }

    public function rate(RateTicketRequest $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);

        if ($ticket->status !== 'closed')
        {
            return back()->with('error', __('tickets.Only closed tickets can be rated.'));
        }

        if ($ticket->rating)
        {
            return back()->with('error', __('tickets.This ticket has already been rated.'));
        }

        TicketRating::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'tiempo_respuesta' => $request->tiempo_respuesta,
            'atencion' => $request->atencion,
            'solucion' => $request->solucion,
            'comentarios' => $request->comentarios,
        ]);

        return back()->with('success', __('tickets.Thank you for your rating.'));
    }

    public function destroy(int $id)
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('delete', $ticket);
        $ticket->delete();

        return redirect()->route('ticket.index')->with('success', __('tickets.Ticket deleted.'));
    }

    public function downloadAttachment(Request $request, int $id, int $mediaId)
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('view', $ticket);

        $media = $ticket->getMedia('attachments')->firstWhere('id', $mediaId);

        if (! $media)
        {
            foreach ($ticket->responses as $response)
            {
                $media = $response->getMedia('attachments')->firstWhere('id', $mediaId);
                if ($media)
                {
                    break;
                }
            }
        }

        if (! $media)
        {
            abort(404, __('tickets.File not found'));
        }

        if (str_starts_with($media->mime_type, 'image/'))
        {
            return response()->file($media->getPath());
        }

        return response()->download($media->getPath(), $media->file_name);
    }
}
