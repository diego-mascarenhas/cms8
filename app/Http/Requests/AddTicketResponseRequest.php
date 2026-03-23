<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class AddTicketResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = Ticket::find($this->route('id'));

        return $ticket && $this->user()->can('view', $ticket);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string',
            'is_internal_note' => 'nullable|boolean',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,zip,txt,doc,docx|max:10240',
        ];
    }
}
