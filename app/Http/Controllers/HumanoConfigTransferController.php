<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\Prompt;
use App\Services\HumanoConfigExporter;
use App\Services\HumanoConfigImporter;
use App\Support\TeamModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class HumanoConfigTransferController extends Controller
{
    public function exportFunnel(Automation $automation, HumanoConfigExporter $exporter): Response
    {
        $this->authorize('view', $automation);
        TeamModuleAccess::abortUnless('automations');
        abort_unless($automation->isFunnel(), 404);

        return $this->downloadJson(
            $exporter->exportFunnel($automation),
            'funnel-'.$automation->slug,
        );
    }

    public function exportAction(Automation $automation, HumanoConfigExporter $exporter): Response
    {
        $this->authorize('view', $automation);
        TeamModuleAccess::abortUnless('automations');
        abort_unless($automation->isAction(), 404);

        return $this->downloadJson(
            $exporter->exportAction($automation),
            'automation-'.$automation->slug,
        );
    }

    public function exportPrompt(Prompt $prompt, HumanoConfigExporter $exporter): Response
    {
        $this->authorize('view', $prompt);
        TeamModuleAccess::abortUnless('prompts');

        $prompt->loadMissing('module');
        $fileStem = ($prompt->module?->key ?: 'prompt').'-'.$prompt->section_key;

        return $this->downloadJson(
            $exporter->exportPrompt($prompt),
            $fileStem,
        );
    }

    public function importForm(): View
    {
        $this->authorizeImportAccess();

        return view('humano-transfer.import');
    }

    public function importStore(Request $request, HumanoConfigImporter $importer): RedirectResponse
    {
        $this->authorizeImportAccess();

        $validated = $request->validate([
            'json' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:json,txt', 'max:2048'],
        ], [
            'file.mimes' => __('El archivo debe ser JSON.'),
        ]);

        $raw = trim((string) ($validated['json'] ?? ''));
        if ($request->hasFile('file'))
        {
            $raw = (string) file_get_contents($request->file('file')->getRealPath());
        }

        if ($raw === '')
        {
            return back()->withErrors(['json' => __('Pegá o subí un JSON de export Humano.')])->withInput();
        }

        try
        {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable)
        {
            return back()->withErrors(['json' => __('El contenido no es un JSON válido.')])->withInput();
        }

        if (! is_array($decoded))
        {
            return back()->withErrors(['json' => __('El JSON debe ser un objeto.')])->withInput();
        }

        $type = (string) data_get($decoded, 'humano_export.type', '');
        $team = auth()->user()->currentTeam;

        if (in_array($type, [HumanoConfigExporter::TYPE_FUNNEL, HumanoConfigExporter::TYPE_ACTION], true))
        {
            TeamModuleAccess::abortUnless('automations', $team);
            $this->authorize('create', Automation::class);
        } elseif ($type === HumanoConfigExporter::TYPE_PROMPT)
        {
            TeamModuleAccess::abortUnless('prompts', $team);
            $this->authorize('create', Prompt::class);
        } else
        {
            return back()->withErrors(['json' => __('Tipo de export desconocido o cabecera humano_export ausente.')])->withInput();
        }

        try
        {
            $result = $importer->import($decoded, $team);
        } catch (InvalidArgumentException $e)
        {
            return back()->withErrors(['json' => $e->getMessage()])->withInput();
        }

        $label = (string) data_get(
            $decoded,
            'humano_export.label',
            data_get($decoded, 'humano_export.belongs_to', $type),
        );

        return redirect()
            ->route($result['redirect_route'], $result['redirect_params'])
            ->with('success', __('Importación de :label completada.', ['label' => $label]));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    protected function downloadJson(array $document, string $fileStem): Response
    {
        $filename = Str::slug($fileStem).'-'.now()->format('Ymd-His').'.json';
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function authorizeImportAccess(): void
    {
        $user = auth()->user();
        $team = $user?->currentTeam;
        abort_unless($team, 403);
        TeamModuleAccess::abortUnlessAny(['automations', 'prompts'], $team);
        abort_unless(
            $user->can('create', Automation::class) || $user->can('create', Prompt::class),
            403,
        );
    }
}
