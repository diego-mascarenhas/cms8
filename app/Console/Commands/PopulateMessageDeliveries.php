<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class PopulateMessageDeliveries extends Command
{
    protected $signature = 'messages:populate-deliveries';
    protected $description = 'Populate message_deliveries table for all active messages and their category contacts in blocks of 5.';

    public function handle()
    {
        $messages = Message::where('status_id', 1)->get();
        $totalCreated = 0;

        foreach ($messages as $message) {
            if (!$message->category_id) {
                $this->warn("Message ID {$message->id} has no category, skipping.");
                continue;
            }
            // Obtener contactos de la categoría
            $contacts = DB::table('category_contact')
                ->where('category_id', $message->category_id)
                ->pluck('contact_id');
            $contactChunks = $contacts->chunk(5);
            foreach ($contactChunks as $chunk) {
                $toInsert = [];
                foreach ($chunk as $contactId) {
                    // Verificar si ya existe
                    $exists = MessageDelivery::where('message_id', $message->id)
                        ->where('contact_id', $contactId)
                        ->exists();
                    if (!$exists) {
                        $toInsert[] = [
                            'team_id' => $message->team_id,
                            'message_id' => $message->id,
                            'contact_id' => $contactId,
                            'status' => 'pending',
                            'sent_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($toInsert)) {
                    MessageDelivery::insert($toInsert);
                    $totalCreated += count($toInsert);
                    $this->info("Inserted ".count($toInsert)." deliveries for message {$message->id} (block of 5)");
                }
            }
        }
        $this->info("Done. Total new deliveries created: $totalCreated");
    }
}
