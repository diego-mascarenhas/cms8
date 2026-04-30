<?php

namespace App\Http\Controllers;

use App\Enums\CampaignType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CampaignsController extends Controller
{
    public function index(): View
    {
        return view('campaigns.index', [
            'campaignTypes' => CampaignType::cases(),
        ]);
    }

    public function edit(string $campaign): View
    {
        return view('campaigns.edit', ['campaign' => $campaign]);
    }

    public function selectTemplate(Request $request): View
    {
        $selectedType = $request->string('type')->toString();
        $selectedTitle = $request->string('title')->toString();

        return view('campaigns.templates-select', [
            'selectedType' => $selectedType,
            'selectedTypeLabel' => $selectedType === 'sequences' ? 'Secuencia de correo' : 'Difusión por correo',
            'selectedTitle' => $selectedTitle,
            'customTemplates' => [
                [
                    'id' => 1,
                    'name' => 'PRUEBA',
                    'description' => 'BOH BOH',
                    'preview' => 'https://placehold.co/700x900/f8f9fa/adb5bd?text=Plantilla+Personalizada',
                    'full_preview' => 'https://placehold.co/1300x1800/f8f9fa/adb5bd?text=Plantilla+Personalizada',
                ],
                [
                    'id' => 2,
                    'name' => 'PRUEBA SANDRA',
                    'description' => 'Plantilla base para secuencias.',
                    'preview' => 'https://placehold.co/700x900/f8f9fa/adb5bd?text=Plantilla+Sandra',
                    'full_preview' => 'https://placehold.co/1300x1800/f8f9fa/adb5bd?text=Plantilla+Sandra',
                ],
            ],
            'kajabiTemplates' => [
                [
                    'id' => 101,
                    'name' => 'Squiggle',
                    'description' => 'Let your copy shine with this uncomplicated template.',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_squiggle/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_squiggle/full.jpg',
                ],
                [
                    'id' => 102,
                    'name' => 'Slice',
                    'description' => "Who says templates can't be playful? Use this one when you want to welcome new subscribers with a fun twist.",
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_slice/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_slice/full.jpg',
                ],
                [
                    'id' => 103,
                    'name' => 'Timber',
                    'description' => 'A minimal, earthy template perfect for newsletter updates.',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_timber/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_timber/full.jpg',
                ],
                [
                    'id' => 104,
                    'name' => 'Brush',
                    'description' => "This template's prominent header helps you showcase your message with style.",
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_brush/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_brush/full.jpg',
                ],
                [
                    'id' => 105,
                    'name' => 'Mocha',
                    'description' => "Make a splash with this clean, simple email template that's perfect for sending content updates.",
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_mocha/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_mocha/full.jpg',
                ],
                [
                    'id' => 106,
                    'name' => 'Strum',
                    'description' => 'This minimal, image-focused template is perfect for sending promotions.',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/full.jpg',
                ],
                [
                    'id' => 107,
                    'name' => 'Bridge',
                    'description' => 'Give your audience a warm welcome with this simple yet refined signup confirmation template.',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/full.jpg',
                ],
                [
                    'id' => 108,
                    'name' => 'Boardwell',
                    'description' => 'Send your latest interviews, courses, blog posts and other content in a beautiful, attractive template.',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/full.jpg',
                ],
                [
                    'id' => 109,
                    'name' => 'Ballast',
                    'description' => 'A great template to use when you need to grab attention with striking visuals and video.',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_ballast/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_ballast/full.jpg',
                ],
                [
                    'id' => 110,
                    'name' => 'Stem',
                    'description' => 'Use this lively, image-based template to keep your fans in the loop.',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_stem/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_stem/full.jpg',
                ],
                [
                    'id' => 111,
                    'name' => 'Myriad',
                    'description' => 'A quick and bright template that you can craft to fit any purpose.',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_myriad/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_myriad/full.jpg',
                ],
                [
                    'id' => 112,
                    'name' => 'Climb',
                    'description' => 'Customize this highly-versatile template to suit any need for your growing business.',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_climb/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_climb/full.jpg',
                ],
                [
                    'id' => 113,
                    'name' => 'Make a Referral',
                    'description' => 'For Kajabi Partners, making referrals is as easy as personalize, add affiliate link, and send!',
                    'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_referral/thumbnail.jpg',
                    'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_referral/full.jpg',
                ],
            ],
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
        $templatesById = [
            1 => ['name' => 'PRUEBA', 'hero' => 'https://placehold.co/1300x1800/f8f9fa/adb5bd?text=Plantilla+Personalizada'],
            2 => ['name' => 'PRUEBA SANDRA', 'hero' => 'https://placehold.co/1300x1800/f8f9fa/adb5bd?text=Plantilla+Sandra'],
            101 => ['name' => 'Squiggle', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_squiggle/full.jpg'],
            102 => ['name' => 'Slice', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_slice/full.jpg'],
            103 => ['name' => 'Timber', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_timber/full.jpg'],
            104 => ['name' => 'Brush', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_brush/full.jpg'],
            105 => ['name' => 'Mocha', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_mocha/full.jpg'],
            106 => ['name' => 'Strum', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/full.jpg'],
            107 => ['name' => 'Bridge', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/full.jpg'],
            108 => ['name' => 'Boardwell', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/full.jpg'],
            109 => ['name' => 'Ballast', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_ballast/full.jpg'],
            110 => ['name' => 'Stem', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_stem/full.jpg'],
            111 => ['name' => 'Myriad', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_myriad/full.jpg'],
            112 => ['name' => 'Climb', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_climb/full.jpg'],
            113 => ['name' => 'Make a Referral', 'hero' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_referral/full.jpg'],
        ];

        $template = $templatesById[$selectedTemplateId] ?? [
            'name' => 'Plantilla personalizada',
            'hero' => 'https://placehold.co/1300x1800/f8f9fa/adb5bd?text=Plantilla+Email',
        ];

        $campaignHeadline = $selectedTitle !== '' ? $selectedTitle : 'Tu próxima campaña';
        $defaultSubject = $selectedTitle !== '' ? 'Actualización: '.$selectedTitle : 'Asunto';
        $defaultPreviewText = 'Descubre los detalles y próximos pasos de esta campaña.';
        $defaultBodyContent = <<<HTML
<p style="margin:0 0 12px;color:#4b5563;font-size:16px;line-height:1.6;">Hola {{first_name}},</p>
<p style="margin:0 0 12px;color:#4b5563;font-size:16px;line-height:1.6;">Gracias por estar aquí. Este correo se creó con la plantilla <strong>{$template['name']}</strong> para que puedas comenzar a personalizarlo de inmediato.</p>
<p style="margin:0 0 22px;color:#4b5563;font-size:16px;line-height:1.6;">Reemplaza este contenido por tu mensaje, agrega enlaces y deja listo tu envío.</p>
<a href="#" style="display:inline-block;background:#7367f0;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:600;">Ver más</a>
HTML;

        $defaultBodyTemplate = <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;padding:24px 0;font-family:Arial,sans-serif;">
  <tr>
    <td align="center">
      <table width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;">
        <tr>
          <td>
            <img src="{$template['hero']}" alt="{$template['name']}" width="640" style="display:block;width:100%;height:auto;">
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            <p style="margin:0 0 12px;color:#6c757d;font-size:14px;">{$template['name']}</p>
            <h1 style="margin:0 0 16px;color:#1f2430;font-size:28px;line-height:1.2;">{$campaignHeadline}</h1>
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

        $defaultBody = str_replace('__EMAIL_BODY__', $defaultBodyContent, $defaultBodyTemplate);

        return [
            'selectedType' => $selectedType,
            'selectedTypeLabel' => $selectedType === 'sequences' ? 'Secuencia de correo' : 'Difusión por correo',
            'selectedTitle' => $selectedTitle,
            'selectedTemplateId' => $selectedTemplateId,
            'grapesEditorUrl' => 'https://humano.test/template/eyJpdiI6Im1vQld3OGVIU20vbW1ENGhRSkxvWFE9PSIsInZhbHVlIjoiaFZVcnI2NWUyUXNLWk1PZHlUOWdXQT09IiwibWFjIjoiMDkwMWMzN2UxZjY3MDE1NzczN2Y0YjFiZTExNDBmMWEyMWY5NzFkNjIyODgyNWNlNDFhMDg4NjI3MzYxMzE4MSIsInRhZyI6IiJ9/editor',
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
}
