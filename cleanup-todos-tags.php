<?php

/**
 * Script para limpiar los tags "Todos" de la base de datos
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Tags\Tag;

echo "🔍 Buscando tags 'Todos' en la base de datos...\n\n";

// Buscar tags que contengan "Todos" en su nombre
$todosTagsGeneral = Tag::where('type', 'general')
    ->where(function ($query)
    {
        $query->where('name->es', 'Todos')
            ->orWhere('name', 'Todos')
            ->orWhere('name', 'todos')
            ->orWhere('name', 'like', '%Todos%');
    })
    ->get();

$todosTagsGallery = Tag::where('type', 'gallery')
    ->where(function ($query)
    {
        $query->where('name->es', 'Todos')
            ->orWhere('name', 'Todos')
            ->orWhere('name', 'todos')
            ->orWhere('name', 'like', '%Todos%');
    })
    ->get();

$totalGeneral = $todosTagsGeneral->count();
$totalGallery = $todosTagsGallery->count();

echo "📊 Resultados:\n";
echo "   - Tags 'Todos' tipo 'general': {$totalGeneral}\n";
echo "   - Tags 'Todos' tipo 'gallery': {$totalGallery}\n\n";

if ($totalGeneral === 0 && $totalGallery === 0)
{
    echo "✅ No se encontraron tags 'Todos' para eliminar.\n";
    exit(0);
}

echo "🗑️  Eliminando tags 'Todos'...\n\n";

DB::beginTransaction();

try
{
    foreach ($todosTagsGeneral as $tag)
    {
        echo "   Eliminando tag general: ID={$tag->id}, name={$tag->name}\n";
        $tag->delete();
    }

    foreach ($todosTagsGallery as $tag)
    {
        echo "   Eliminando tag gallery: ID={$tag->id}, name={$tag->name}\n";
        $tag->delete();
    }

    DB::commit();

    echo "\n✅ Tags 'Todos' eliminados correctamente.\n";
    echo '   Total eliminados: '.($totalGeneral + $totalGallery)."\n";
} catch (\Exception $e)
{
    DB::rollBack();
    echo "\n❌ Error al eliminar tags: ".$e->getMessage()."\n";
    exit(1);
}
