<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition()
    {
        $users = User::all();

        return [
            'team_id' => 1,
            'name' => $this->faker->company,
            'creator_id' => $this->faker->boolean(70) ? $users->random()->id : $this->faker->randomElement($users)->id,
            'responsible_id' => $this->faker->boolean(70) ? $users->random()->id : $this->faker->randomElement($users)->id,
            'status_id' => ContactStatus::inRandomOrder()->first()->id,
            // 'source_id' => $this->faker->numberBetween(1, 3),
            'birthday' => $this->faker->date(),
            'profile' => $this->faker->paragraph(),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Contact $contact) {
            $sources = Source::where('id', '<=', 3)->inRandomOrder()->get();

            if ($primarySource = $sources->first()) {
                $contact->source_id = $primarySource->id;
                $contact->save();
            }
        });
    }
}
