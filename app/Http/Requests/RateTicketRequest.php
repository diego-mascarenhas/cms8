<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use App\Models\TicketRating;
use Illuminate\Foundation\Http\FormRequest;

class RateTicketRequest extends FormRequest
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
        return TicketRating::getValidationRules();
    }
}
