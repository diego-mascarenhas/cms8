<?php

namespace App\Http\Controllers;

use App\DataTables\EmailCampaignDataTable;
use App\Enums\CampaignType;
use App\Models\EmailCampaign;
use App\Models\Template;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignsController extends Controller
{
    public function index(EmailCampaignDataTable $dataTable): View|RedirectResponse
    {
        if (! auth()->user()?->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        return $dataTable->render('campaigns.index', [
            'campaignTypes' => CampaignType::cases(),
        ]);
    }

    public function edit(EmailCampaign $campaign): View
    {
        return view('campaigns.edit', ['campaign' => $campaign]);
    }

    public function selectTemplate(Request $request): View
    {
        $selectedType = $request->string('type')->toString();
        $selectedTitle = $request->string('title')->toString();
        $templateDefinitions = $this->getCampaignTemplateDefinitions();
        $templatesByLegacyId = $this->syncCampaignTemplatesToDatabase($templateDefinitions);

        return view('campaigns.templates-select', [
            'selectedType' => $selectedType,
            'selectedTypeLabel' => $selectedType === 'sequences' ? 'Secuencia de correo' : 'Difusión por correo',
            'selectedTitle' => $selectedTitle,
            'customTemplates' => array_values(array_map(function (array $definition) use ($templatesByLegacyId): array
            {
                $template = $templatesByLegacyId[$definition['legacy_id']] ?? null;

                return [
                    'id' => $template?->id,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'preview' => $definition['preview'],
                    'full_preview' => $definition['full_preview'],
                ];
            }, array_filter($templateDefinitions, fn (array $item): bool => $item['group'] === 'custom'))),
            'kajabiTemplates' => array_values(array_map(function (array $definition) use ($templatesByLegacyId): array
            {
                $template = $templatesByLegacyId[$definition['legacy_id']] ?? null;

                return [
                    'id' => $template?->id,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'preview' => $definition['preview'],
                    'full_preview' => $definition['full_preview'],
                ];
            }, array_filter($templateDefinitions, fn (array $item): bool => $item['group'] === 'kajabi'))),
        ]);
    }

    public function classicEditor(Request $request): View
    {
        return view('campaigns.classic-editor', $this->buildClassicEditorData($request));
    }

    public function classicEditorGrapes(Request $request): View
    {
        return view('campaigns.classic-editor-grapes', $this->buildClassicEditorData($request));
    }

    private function buildClassicEditorData(Request $request): array
    {
        $selectedType = $request->string('type')->toString();
        $selectedTitle = $request->string('title')->toString();
        $selectedTemplateId = $request->integer('template_id');
        $defaultInternalTitle = $selectedTitle !== '' ? $selectedTitle : 'Correo de secuencia';
        $templateDefinitions = $this->getCampaignTemplateDefinitions();
        $templatesByLegacyId = $this->syncCampaignTemplatesToDatabase($templateDefinitions);
        $selectedTemplate = Template::withoutGlobalScopes()->find($selectedTemplateId);
        $selectedDefinition = collect($templateDefinitions)->first(function (array $definition) use ($selectedTemplate): bool
        {
            return $selectedTemplate instanceof Template && $definition['name'] === $selectedTemplate->name;
        });
        if (! is_array($selectedDefinition))
        {
            $selectedDefinition = [
                'name' => $selectedTemplate?->name ?? 'Plantilla personalizada',
                'full_preview' => 'https://placehold.co/1300x1800/f8f9fa/adb5bd?text=Plantilla+Email',
            ];
        }

        $defaultSubject = $selectedTitle !== '' ? 'Actualización: '.$selectedTitle : 'Asunto';
        $defaultPreviewText = 'Descubre los detalles y próximos pasos de esta campaña.';
        $defaultBodyShell = $this->buildTemplateHtmlShell($selectedDefinition);
        $defaultBodyContent = $this->defaultEmailCanvasInnerHtml();
        $defaultBodyTemplate = $defaultBodyShell;
        $defaultBody = str_replace('__EMAIL_BODY__', $defaultBodyContent, $defaultBodyShell);
        if ($selectedTemplate instanceof Template)
        {
            $selectedTemplate = $this->ensureTemplateHasGjsStructure($selectedTemplate, $defaultBody);
        }
        $storedBody = is_array($selectedTemplate?->gjs_data) ? ($selectedTemplate->gjs_data['html'] ?? null) : null;
        if (is_string($storedBody) && $storedBody !== '')
        {
            $defaultBody = $storedBody;
            $defaultBodyContent = $this->extractEditableRegionFromMergedTemplate($storedBody);
        }

        $grapesEditorUrl = '#';
        if ($selectedTemplate instanceof Template)
        {
            $grapesEditorUrl = route('template.editor', $selectedTemplate->getHashedId());
        } elseif ($selectedTemplateId > 0)
        {
            foreach ($templatesByLegacyId as $legacyTemplate)
            {
                if ($legacyTemplate->id === $selectedTemplateId)
                {
                    $grapesEditorUrl = route('template.editor', $legacyTemplate->getHashedId());
                    break;
                }
            }
        }

        return [
            'selectedType' => $selectedType,
            'selectedTypeLabel' => $selectedType === 'sequences' ? 'Secuencia de correo' : 'Difusión por correo',
            'selectedTitle' => $selectedTitle,
            'selectedTemplateId' => $selectedTemplateId,
            'grapesEditorUrl' => $grapesEditorUrl,
            'defaultInternalTitle' => $defaultInternalTitle,
            'defaultSubject' => $defaultSubject,
            'defaultPreviewText' => $defaultPreviewText,
            'defaultBodyContent' => $defaultBodyContent,
            'defaultBodyTemplate' => $defaultBodyTemplate,
            'defaultBody' => $defaultBody,
            'sendTimes' => [
                '0' => '12:00 AM',
                '60' => '01:00 AM',
                '120' => '02:00 AM',
                '180' => '03:00 AM',
                '240' => '04:00 AM',
                '300' => '05:00 AM',
                '360' => '06:00 AM',
                '420' => '07:00 AM',
                '480' => '08:00 AM',
                '540' => '09:00 AM',
                '600' => '10:00 AM',
                '660' => '11:00 AM',
                '720' => '12:00 PM',
                '780' => '01:00 PM',
                '840' => '02:00 PM',
                '900' => '03:00 PM',
                '960' => '04:00 PM',
                '1020' => '05:00 PM',
                '1080' => '06:00 PM',
                '1140' => '07:00 PM',
                '1200' => '08:00 PM',
                '1260' => '09:00 PM',
                '1320' => '10:00 PM',
                '1380' => '11:00 PM',
            ],
        ];
    }

    private function getCampaignTemplateDefinitions(): array
    {
        return [
            [
                'legacy_id' => 1,
                'group' => 'custom',
                'name' => 'Nova Bienvenida',
                'description' => 'Onboarding y primer contacto con estilo editorial limpio; perfecta para altas y confirmaciones que generan confianza.',
                'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/thumbnail.jpg',
                'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/full.jpg',
            ],
            [
                'legacy_id' => 2,
                'group' => 'custom',
                'name' => 'Pulse Editorial',
                'description' => 'Boletines y actualizaciones frecuentes con cabecera que destaca contenido largo entrevistas, cursos y artículos.',
                'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/thumbnail.jpg',
                'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/full.jpg',
            ],
            [
                'legacy_id' => 3,
                'group' => 'custom',
                'name' => 'Stripe Promo',
                'description' => 'Lanzamientos rápidos y ofertas con foco visual; ideal para flash sales y promos donde la imagen manda.',
                'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/thumbnail.jpg',
                'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/full.jpg',
            ],
            ['legacy_id' => 101, 'group' => 'kajabi', 'name' => 'Squiggle', 'description' => 'Let your copy shine with this uncomplicated template.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_squiggle/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_squiggle/full.jpg'],
            ['legacy_id' => 102, 'group' => 'kajabi', 'name' => 'Slice', 'description' => "Who says templates can't be playful? Use this one when you want to welcome new subscribers with a fun twist.", 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_slice/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_slice/full.jpg'],
            ['legacy_id' => 103, 'group' => 'kajabi', 'name' => 'Timber', 'description' => 'A minimal, earthy template perfect for newsletter updates.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_timber/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_timber/full.jpg'],
            ['legacy_id' => 104, 'group' => 'kajabi', 'name' => 'Brush', 'description' => "This template's prominent header helps you showcase your message with style.", 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_brush/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_brush/full.jpg'],
            ['legacy_id' => 105, 'group' => 'kajabi', 'name' => 'Mocha', 'description' => "Make a splash with this clean, simple email template that's perfect for sending content updates.", 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_mocha/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_mocha/full.jpg'],
            ['legacy_id' => 106, 'group' => 'kajabi', 'name' => 'Strum', 'description' => 'This minimal, image-focused template is perfect for sending promotions.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/full.jpg'],
            ['legacy_id' => 107, 'group' => 'kajabi', 'name' => 'Bridge', 'description' => 'Give your audience a warm welcome with this simple yet refined signup confirmation template.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/full.jpg'],
            ['legacy_id' => 108, 'group' => 'kajabi', 'name' => 'Boardwell', 'description' => 'Send your latest interviews, courses, blog posts and other content in a beautiful, attractive template.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/full.jpg'],
            ['legacy_id' => 109, 'group' => 'kajabi', 'name' => 'Ballast', 'description' => 'A great template to use when you need to grab attention with striking visuals and video.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_ballast/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_ballast/full.jpg'],
            ['legacy_id' => 110, 'group' => 'kajabi', 'name' => 'Stem', 'description' => 'Use this lively, image-based template to keep your fans in the loop.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_stem/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_stem/full.jpg'],
            ['legacy_id' => 111, 'group' => 'kajabi', 'name' => 'Myriad', 'description' => 'A quick and bright template that you can craft to fit any purpose.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_myriad/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_myriad/full.jpg'],
            ['legacy_id' => 112, 'group' => 'kajabi', 'name' => 'Climb', 'description' => 'Customize this highly-versatile template to suit any need for your growing business.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_climb/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_climb/full.jpg'],
            ['legacy_id' => 113, 'group' => 'kajabi', 'name' => 'Make a Referral', 'description' => 'Personaliza, enlaza tu invitación o beneficio para referidos y envía en segundos.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_referral/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_referral/full.jpg'],
        ];
    }

    private function syncCampaignTemplatesToDatabase(array $definitions): array
    {
        $templatesByLegacyId = [];

        foreach ($definitions as $definition)
        {
            $initialHtml = $this->buildTemplateHtmlFromDefinition($definition);
            $teamId = auth()->check() ? auth()->user()?->currentTeam?->id : null;
            $template = Template::withoutGlobalScopes()->where('name', $definition['name'])->first();
            if (! $template)
            {
                $template = Template::withoutEvents(function () use ($definition, $teamId, $initialHtml)
                {
                    return Template::withoutGlobalScopes()->create([
                        'name' => $definition['name'],
                        'team_id' => $teamId,
                        'status_id' => 1,
                        'gjs_data' => $this->buildDefaultGjsData($initialHtml),
                    ]);
                });
            }

            $template = $this->ensureTemplateHasGjsStructure($template, $initialHtml);

            $templatesByLegacyId[$definition['legacy_id']] = $template;
        }

        return $templatesByLegacyId;
    }

    private function defaultEmailCanvasInnerHtml(): string
    {
        return '<p style="margin:0;color:#777777;font-size:14px;line-height:1.5;font-weight:300;">&nbsp;</p>';
    }

    /**
     * Email layout shell: hero image + editable region marker + footer. No campaign copy or template branding.
     */
    private function buildTemplateHtmlShell(array $definition): string
    {
        $fullPreview = $definition['full_preview'] ?? '';
        $nameForAlt = htmlspecialchars($definition['name'] ?? 'Email', ENT_QUOTES | ENT_HTML5);

        return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;padding:24px 0;font-family:Arial,sans-serif;">
  <tr>
    <td align="center">
      <table width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;">
        <tr>
          <td>
            <img src="{$fullPreview}" alt="{$nameForAlt}" width="640" style="display:block;width:100%;height:auto;">
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            __EMAIL_BODY__
            <hr style="border:none;border-top:1px solid #eceef2;margin:26px 0;">
            <p style="margin:0;color:#6c757d;font-size:13px;">Equipo Humano</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
HTML;
    }

    private function buildTemplateHtmlFromDefinition(array $definition): string
    {
        return str_replace(
            '__EMAIL_BODY__',
            $this->defaultEmailCanvasInnerHtml(),
            $this->buildTemplateHtmlShell($definition),
        );
    }

    private function buildDefaultGjsData(string $html): array
    {
        return [
            'components' => $this->htmlToGrapesComponents($html),
            'styles' => '[]',
            'css' => '* { box-sizing: border-box; } body { margin: 0; }',
            'html' => $html,
        ];
    }

    private function ensureTemplateHasGjsStructure(Template $template, string $fallbackHtml): Template
    {
        $current = is_array($template->gjs_data) ? $template->gjs_data : [];
        $currentHtml = $current['html'] ?? $fallbackHtml;
        $components = $current['components'] ?? null;
        $styles = $current['styles'] ?? null;
        $css = $current['css'] ?? null;

        $isTriviallyEmptyHtml = is_string($currentHtml)
            && (str_contains($currentHtml, '<body></body>') || str_contains($currentHtml, '<body> </body>'));
        $isTriviallyEmptyComponents = is_string($components)
            && (str_contains($components, '<body></body>') || str_contains($components, '<body> </body>'));
        $isHtmlInsteadOfComponents = is_string($components) && str_contains($components, '<table');

        $hasLegacyCampaignStarterMarkup = is_string($currentHtml) && (
            str_contains($currentHtml, 'Este correo se creó con la plantilla')
            || str_contains($currentHtml, 'Reemplaza este contenido por tu mensaje')
        );

        $needsUpdate = ! is_string($components) || trim($components) === '' || trim($components) === '[]';
        $needsUpdate = $needsUpdate || ! is_string($currentHtml) || trim($currentHtml) === '';
        $needsUpdate = $needsUpdate || ! is_string($styles) || trim($styles) === '';
        $needsUpdate = $needsUpdate || ! is_string($css) || trim($css) === '';
        $needsUpdate = $needsUpdate || $isTriviallyEmptyHtml || $isTriviallyEmptyComponents;
        $needsUpdate = $needsUpdate || $isHtmlInsteadOfComponents;
        $needsUpdate = $needsUpdate || $hasLegacyCampaignStarterMarkup;

        if (! $needsUpdate)
        {
            return $template;
        }

        $useFallbackMarkup = $isTriviallyEmptyHtml || $hasLegacyCampaignStarterMarkup;
        $normalizedHtml = $useFallbackMarkup ? $fallbackHtml : $currentHtml;
        $normalizedComponents = ($isTriviallyEmptyComponents || $hasLegacyCampaignStarterMarkup)
            ? $fallbackHtml
            : $components;

        $template->update([
            'gjs_data' => [
                'components' => $this->normalizeComponentsValue($normalizedComponents, $normalizedHtml),
                'styles' => is_string($styles) && trim($styles) !== '' ? $styles : '[]',
                'css' => is_string($css) && trim($css) !== '' ? $css : '* { box-sizing: border-box; } body { margin: 0; }',
                'html' => $normalizedHtml,
            ],
        ]);

        return $template->fresh();
    }

    private function normalizeComponentsValue(mixed $components, string $fallbackHtml): string
    {
        if (is_string($components) && trim($components) !== '')
        {
            $decoded = json_decode($components, true);
            if (is_array($decoded))
            {
                return $components;
            }
        }

        if (is_array($components))
        {
            return json_encode($components) ?: '[]';
        }

        return $this->htmlToGrapesComponents($fallbackHtml);
    }

    private function htmlToGrapesComponents(string $html): string
    {
        $normalizedHtml = trim($html) !== '' ? $html : '<table><tr><td></td></tr></table>';
        $components = [
            [
                'type' => 'wrapper',
                'components' => [
                    [
                        'type' => 'text',
                        'content' => $normalizedHtml,
                    ],
                ],
            ],
        ];

        return json_encode($components) ?: '[]';
    }

    private function extractEditableRegionFromMergedTemplate(string $mergedHtml): string
    {
        if (preg_match(
            '/<td\s+style="padding:28px;">\s*(.+?)\s*<hr\b/is',
            $mergedHtml,
            $matches,
        ))
        {
            return trim($matches[1]);
        }

        return $this->defaultEmailCanvasInnerHtml();
    }
}
