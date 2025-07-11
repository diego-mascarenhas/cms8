<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Verificando códigos de idioma en la base de datos:\n";
echo "===========================================\n\n";

// Códigos que vimos en los ejemplos
$testCodes = ['es-ES', 'en-GB', 'fr-FR', 'de-DE', 'pt-PT', 'GL'];

foreach ($testCodes as $code) {
    $variant = App\Models\LanguageVariant::where('code', $code)->first();
    if ($variant) {
        echo "✅ {$code} -> {$variant->name}\n";
    } else {
        echo "❌ {$code} -> NO EXISTE\n";
    }
}

echo "\n";

// Obtener algunos contactos con raw_data
echo "Verificando contactos con raw_data:\n";
echo "=================================\n\n";

$contacts = App\Models\Contact::whereRaw('JSON_EXTRACT(data, "$.combinaciones.raw_data") IS NOT NULL')
    ->limit(3)
    ->get();

foreach ($contacts as $contact) {
    echo "Contacto: {$contact->name}\n";
    
    $data = json_decode(json_encode($contact->data), true);
    $rawData = $data['combinaciones']['raw_data'];
    
    // Procesar igual que en el seeder
    $cleanData = $rawData;
    $cleanData = str_replace('\\"', '"', $cleanData);
    $cleanData = str_replace('\\\\', '\\', $cleanData);
    $cleanData = stripslashes($cleanData);
    
    $combinations = json_decode($cleanData, true);
    if ($combinations) {
        echo "  Combinaciones: ";
        foreach ($combinations as $combination) {
            if (is_array($combination) && count($combination) === 2) {
                echo "{$combination[0]}->{$combination[1]} ";
            }
        }
        echo "\n";
    } else {
        echo "  Error al decodificar JSON\n";
    }
    
    // Verificar si ya tiene combinaciones
    $existingCombinations = $contact->languageVariants()->count();
    echo "  Combinaciones existentes: {$existingCombinations}\n\n";
}

echo "\nTotal de language_variants: " . App\Models\LanguageVariant::count() . "\n";
echo "Total de contactos con raw_data: " . App\Models\Contact::whereRaw('JSON_EXTRACT(data, "$.combinaciones.raw_data") IS NOT NULL')->count() . "\n";
echo "Total de contactos con combinaciones: " . App\Models\Contact::has('languageVariants')->count() . "\n"; 