<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketPriorityRequest extends FormRequest
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
            'priority' => 'required|in:low,medium,high,urgent',
        ];
    }
}
