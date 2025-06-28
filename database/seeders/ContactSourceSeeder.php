<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactSource;
use App\Models\Source;
use Illuminate\Database\Seeder;

class ContactSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Contact::withoutGlobalScope('team');

        $contacts = Contact::all();
        $sources = Source::all();

        if ($contacts->isEmpty() || $sources->isEmpty()) {
            $this->command->info('Make sure there are contacts and sources in the database before running this seeder.');

            return;
        }

        foreach ($contacts as $contact) {
            $randomSources = $sources->random(rand(1, 3));

            foreach ($randomSources as $source) {
                ContactSource::create([
                    'contact_id' => $contact->id,
                    'source_id' => $source->id,
                    'value' => $this->generateRandomValue($source->name),
                ]);
            }
        }
    }

    /**
     * Genera un valor aleatorio basado en el tipo de fuente.
     */
    private function generateRandomValue(string $sourceName): string
    {
        switch ($sourceName) {
            case 'Phone':
                return rand(1, 99) . rand(100000000, 999999999);
            case 'Email':
                return 'usuario' . rand(1, 1000) . '@example.com';
            case 'WhatsApp':
                return '+' . rand(1, 99) . rand(1000000000, 9999999999);
            default:
                return 'usuario' . rand(1, 1000);
        }
    }
}
