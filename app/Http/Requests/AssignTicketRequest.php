<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = Ticket::find($this->route('id'));

        return $ticket && $this->user()->can('update', $ticket);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => 'nullable|exists:users,id',
        ];
    }
}
