<?php

namespace Tests\Feature\Homes;

use App\Support\CmsHomeAsset;
use App\Support\GuidePresentation;
use App\Support\SlashHomeAsset;
use Tests\TestCase;

class CmsLandingTest extends TestCase
{
    public function test_guest_can_view_cms_landing(): void
    {
        $this->get('/cms')
            ->assertOk()
            ->assertSee('cms-page', false)
            ->assertSee('id="empezar"', false)
            ->assertSee(__('cms_landing.hero.title'), false)
            ->assertSee(__('cms_landing.roles.admin_title'), false)
            ->assertSee(__('cms_landing.roles.user_title'), false)
            ->assertSee(__('cms_landing.features.title'), false)
            ->assertSee(CmsHomeAsset::url('css/landing.css'), false)
            ->assertSee(GuidePresentation::url('cms-wordpress'), false)
            ->assertSee(route('cms.lead.store'), false)
            ->assertSee('data-slash-lead-form', false)
            ->assertSee(__('slash_landing.hero.note'), false)
            ->assertSee(SlashHomeAsset::url('js/landing.js'), false)
            ->assertSee(route('cms.newsletter'), false)
            ->assertSee(route('login'), false)
            ->assertDontSee(route('register'), false)
            ->assertSee('humano-brand-footer', false)
            ->assertSee('slash-footer-bottom', false)
            ->assertSee('https://www.idoneo.dev', false)
            ->assertSee('https://revisionalpha.com', false)
            ->assertSee(asset('assets/logo-revision-alpha.svg'), false)
            ->assertSee(__('slash_landing.footer.brand_name'), false);
    }

    public function test_guest_can_view_cms_newsletter_preview(): void
    {
        $this->get('/cms/newsletter')
            ->assertOk()
            ->assertSee(__('cms_landing.newsletter.page_title'), false)
            ->assertSee(__('cms_landing.newsletter.subject'), false)
            ->assertSee(__('cms_landing.newsletter.headline'), false)
            ->assertSee(__('cms_landing.newsletter.admin_title'), false)
            ->assertSee(__('cms_landing.newsletter.user_title'), false)
            ->assertSee(route('cms.landing'), false)
            ->assertSee(GuidePresentation::url('cms-wordpress'), false)
            ->assertSee('newsletter-campaign.html', false)
            ->assertSee('slash-footer-bottom', false)
            ->assertSee('https://www.idoneo.dev', false);
    }
}
