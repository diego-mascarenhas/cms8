<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\ContactLanguageVariant;
use App\Models\LanguageVariant;

class DebugLanguageVariants extends Command
{
    protected $signature = 'debug:language-variants {contactId?}';
    protected $description = 'Debug language variants for a contact';

    public function handle()
    {
        $contactId = $this->argument('contactId') ?? 141;
        
        $this->info("Debugging language variants for contact ID: $contactId");
        
        // Verificar si el contacto existe
        $contact = Contact::find($contactId);
        if (!$contact) {
            $this->error("Contact ID $contactId not found");
            return 1;
        }
        
        $this->info("Contact: {$contact->name} {$contact->surname}");
        
        // Verificar las variantes de idioma directamente desde la base de datos
        $variants = ContactLanguageVariant::where('contact_id', $contactId)->get();
        $this->info("Found " . $variants->count() . " language variants in database");
        
        foreach ($variants as $variant) {
            $this->info("Variant ID: {$variant->id}");
            $this->info("  Source: {$variant->source_language_code}");
            $this->info("  Target: {$variant->target_language_code}");
            
            // Verificar si existen los idiomas
            $sourceLanguage = LanguageVariant::where('code', $variant->source_language_code)->first();
            $targetLanguage = LanguageVariant::where('code', $variant->target_language_code)->first();
            
            $this->info("  Source language exists: " . ($sourceLanguage ? 'Yes - ' . $sourceLanguage->name : 'No'));
            $this->info("  Target language exists: " . ($targetLanguage ? 'Yes - ' . $targetLanguage->name : 'No'));
        }
        
        // Verificar usando las relaciones
        $this->info("\nUsing relationships:");
        $contactWithRelations = Contact::with(['languageVariants.sourceLanguage', 'languageVariants.targetLanguage'])->find($contactId);
        $this->info("Found " . $contactWithRelations->languageVariants->count() . " language variants using relationships");
        
        foreach ($contactWithRelations->languageVariants as $variant) {
            $this->info("Variant ID: {$variant->id}");
            $this->info("  Source code: {$variant->source_language_code}");
            $this->info("  Target code: {$variant->target_language_code}");
            $this->info("  Source language: " . ($variant->sourceLanguage ? $variant->sourceLanguage->name : 'null'));
            $this->info("  Target language: " . ($variant->targetLanguage ? $variant->targetLanguage->name : 'null'));
        }
        
        return 0;
    }
} 