<?php

namespace Tests\Unit;

use App\Support\LocaleTranslationFiller;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocaleTranslationFillerTest extends TestCase
{
    public function test_it_fills_missing_json_and_php_keys_from_source_locale(): void
    {
        $tempRoot = storage_path('framework/testing/locale-filler-'.uniqid());
        File::ensureDirectoryExists($tempRoot.'/es_ES');
        File::ensureDirectoryExists($tempRoot.'/fr');

        File::put($tempRoot.'/es_ES.json', json_encode([
            'Dashboard' => 'Panel',
            'Messages' => 'Mensajes',
        ], JSON_THROW_ON_ERROR));

        File::put($tempRoot.'/fr.json', json_encode([
            'Dashboard' => 'Tableau de bord',
        ], JSON_THROW_ON_ERROR));

        File::put($tempRoot.'/es_ES/app.php', <<<'PHP'
<?php

return [
    'welcome' => 'Bienvenido',
    'nested' => [
        'child' => 'Hijo',
    ],
];
PHP);

        File::put($tempRoot.'/fr/app.php', <<<'PHP'
<?php

return [
    'welcome' => 'Bonjour',
];
PHP);

        $originalLangPath = lang_path();

        try
        {
            app()->useLangPath($tempRoot);

            $summary = (new LocaleTranslationFiller)->fillFromLocale('es_ES', ['fr']);

            $this->assertSame(1, $summary['fr']['json_added']);
            $this->assertSame(0, $summary['fr']['php_created']);
            $this->assertSame(1, $summary['fr']['php_updated']);

            /** @var array<string, string> $frJson */
            $frJson = json_decode((string) file_get_contents($tempRoot.'/fr.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('Mensajes', $frJson['Messages']);

            /** @var array<string, mixed> $frApp */
            $frApp = require $tempRoot.'/fr/app.php';
            $this->assertSame('Bonjour', $frApp['welcome']);
            $this->assertSame('Hijo', $frApp['nested']['child']);
        } finally
        {
            app()->useLangPath($originalLangPath);
            File::deleteDirectory($tempRoot);
        }
    }
}
