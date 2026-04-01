<?php

namespace Database\Factories;

use App\Enums\ContactInteractionType;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContactInteraction>
 */
class ContactInteractionFactory extends Factory
{
    protected $model = ContactInteraction::class;

    public function definition(): array
    {
        $contact = Contact::factory()->create();

        return [
            'contact_id' => $contact->id,
            'user_id' => User::query()->first()?->id,
            'relatable_type' => null,
            'relatable_id' => null,
            'type' => ContactInteractionType::Note,
            'subject' => $this->faker->optional()->sentence(),
            'body' => $this->faker->paragraph(),
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
