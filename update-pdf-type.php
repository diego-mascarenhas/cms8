<?php

/**
 * Script para actualizar el tipo de archivos PDF en la base de datos
 * Cambia de 'document' a 'pdf' para una mejor visualización
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Multimedia;
use Illuminate\Support\Facades\DB;

echo "🔍 Buscando archivos PDF en la base de datos...\n\n";

// Buscar multimedia de tipo 'document' que tengan archivos PDF
$pdfMultimedia = Multimedia::where('type', 'document')
    ->get()
    ->filter(function ($item) {
        $media = $item->getFirstMedia('media');
        if (!$media) {
            return false;
        }
        return $media->mime_type === 'application/pdf';
    });

$total = $pdfMultimedia->count();

echo "📊 Resultados:\n";
echo "   - Archivos PDF encontrados: {$total}\n\n";

if ($total === 0) {
    echo "✅ No se encontraron PDFs para actualizar.\n";
    exit(0);
}

echo "🔄 Actualizando tipo de 'document' a 'pdf'...\n\n";

DB::beginTransaction();

try {
    $updated = 0;
    
    foreach ($pdfMultimedia as $item) {
        $media = $item->getFirstMedia('media');
        echo "   Actualizando: ID={$item->id}, title={$item->title}, mime={$media->mime_type}\n";
        $item->update(['type' => 'pdf']);
        $updated++;
    }
    
    DB::commit();
    
    echo "\n✅ Archivos PDF actualizados correctamente.\n";
    echo "   Total actualizados: {$updated}\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ Error al actualizar PDFs: " . $e->getMessage() . "\n";
    exit(1);
}
