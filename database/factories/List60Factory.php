<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\List60;
use Illuminate\Database\Eloquent\Factories\Factory;

class List60Factory extends Factory
{
    protected $model = List60::class;

    /**
     * @var array
     */
    protected static $usedContactIds = [];

    public function definition()
    {
        return [
            'contact_id' => $this->uniqueContactId(),
            'type_id' => $this->faker->numberBetween(1, 2),
            'date_next' => $this->faker->dateTimeBetween('now', '+1 month'),
            'notes' => $this->faker->sentence(),
            'responsible_id' => $this->faker->numberBetween(3, 5),
            'status_id' => $this->faker->numberBetween(1, 5),
        ];
    }

    /**
     * Get a unique contact ID.
     *
     * @return int
     */
    protected function uniqueContactId()
    {
        $contactIds = Contact::pluck('id')->toArray();
        $availableIds = array_diff($contactIds, static::$usedContactIds);

        if (empty($availableIds)) {
            throw new \RuntimeException('No more unique contact IDs available.');
        }

        $contactId = $this->faker->randomElement($availableIds);
        static::$usedContactIds[] = $contactId;

        return $contactId;
    }
}
