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
        $selectedType = $request->string('type')->toString();
        $selectedTitle = $request->string('title')->toString();
        $selectedTemplateId = $request->integer('template_id');

        return view('campaigns.classic-editor', [
            'selectedTypeLabel' => $selectedType === 'sequences' ? 'Secuencia de correo' : 'Difusión por correo',
            'selectedTitle' => $selectedTitle,
            'selectedTemplateId' => $selectedTemplateId,
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
        ]);
    }
}
