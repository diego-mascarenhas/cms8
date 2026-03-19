<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamDemoProductsSeeder extends Seeder
{
    /**
     * Demo catalogue for Team Demo: enables the products module, creates categories, and ~30 products.
     */
    public function run(): void
    {
        $team = Team::query()->where('name', "Demo's Team")->first() ?? Team::query()->find(1);

        if (! $team)
        {
            $this->command?->warn('TeamDemoProductsSeeder: Demo team not found (expected name "Demo\'s Team" or id 1). Skipping.');

            return;
        }

        $productsModule = Module::query()->where('key', 'products')->first();

        if (! $productsModule)
        {
            $this->command?->error('TeamDemoProductsSeeder: products module is missing. Run ModuleSeeder first.');

            return;
        }

        $this->command?->info('🛒 Seeding demo products for team: '.$team->name.' (id '.$team->id.')');

        $now = now();
        DB::table('module_team')->updateOrInsert(
            [
                'module_id' => $productsModule->id,
                'team_id' => $team->id,
            ],
            [
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $currencyId = Currency::query()->where('code', 'EUR')->value('id')
            ?? Currency::query()->value('id');

        if (! $currencyId)
        {
            $this->command?->error('TeamDemoProductsSeeder: no currency found. Run CurrencySeeder first.');

            return;
        }

        $categoryDefinitions = [
            ['name' => 'Hosting & domains', 'description' => 'Hosting plans, DNS, and domain registration'],
            ['name' => 'Web development', 'description' => 'Websites, landing pages, and custom builds'],
            ['name' => 'Support & maintenance', 'description' => 'SLAs, updates, and monitoring'],
            ['name' => 'Consulting', 'description' => 'Audits, architecture, and technical advisory'],
            ['name' => 'Security & compliance', 'description' => 'SSL, backups, GDPR tooling'],
        ];

        $categoryIds = [];
        foreach ($categoryDefinitions as $order => $definition)
        {
            $category = Category::query()->updateOrCreate(
                [
                    'name' => $definition['name'],
                    'module_id' => $productsModule->id,
                    'team_id' => $team->id,
                ],
                [
                    'description' => $definition['description'],
                    'parent_id' => null,
                    'status' => true,
                    'order' => $order,
                ],
            );
            $categoryIds[] = $category->id;
        }

        Product::withoutGlobalScope('team')->where('team_id', $team->id)->delete();

        $catalogue = [
            ['name' => 'Shared hosting — Starter', 'description' => '10 GB SSD, weekly backups, one domain.', 'price' => 9.99],
            ['name' => 'Shared hosting — Business', 'description' => '50 GB SSD, daily backups, unlimited domains.', 'price' => 24.99],
            ['name' => 'Managed VPS — S', 'description' => '2 vCPU, 4 GB RAM, managed OS patches.', 'price' => 49.00],
            ['name' => 'Managed VPS — M', 'description' => '4 vCPU, 8 GB RAM, priority support.', 'price' => 89.00],
            ['name' => '.com domain (1 year)', 'description' => 'Registration and DNS management.', 'price' => 12.99],
            ['name' => 'Wildcard SSL certificate', 'description' => 'Annual SSL for primary domain and subdomains.', 'price' => 79.00],
            ['name' => 'Landing page — template', 'description' => 'Responsive landing, contact form, analytics hook.', 'price' => 450.00],
            ['name' => 'Corporate website — 5 pages', 'description' => 'Design system, CMS training, SEO basics.', 'price' => 2200.00],
            ['name' => 'E-commerce starter', 'description' => 'Woo-ready shop, payments, up to 50 SKUs.', 'price' => 3200.00],
            ['name' => 'Custom Laravel module', 'description' => 'Scoped feature module with tests and docs.', 'price' => 1800.00],
            ['name' => 'API integration (per endpoint)', 'description' => 'Auth, mapping, error handling, logging.', 'price' => 350.00],
            ['name' => 'Performance audit', 'description' => 'Lighthouse, server tuning recommendations.', 'price' => 600.00],
            ['name' => 'Accessibility review', 'description' => 'WCAG-oriented report and fixes list.', 'price' => 550.00],
            ['name' => 'Support retainer — 5h/month', 'description' => 'Tickets, small changes, advisory.', 'price' => 299.00],
            ['name' => 'Support retainer — 15h/month', 'description' => 'Includes staging deploys and monitoring.', 'price' => 749.00],
            ['name' => 'Emergency incident response', 'description' => '4h critical response window (business hours).', 'price' => 199.00],
            ['name' => 'Monthly maintenance pack', 'description' => 'Updates, plugin checks, uptime ping.', 'price' => 99.00],
            ['name' => 'Backup verification service', 'description' => 'Monthly restore test and report.', 'price' => 45.00],
            ['name' => 'Architecture workshop (half day)', 'description' => 'Whiteboard session + written summary.', 'price' => 650.00],
            ['name' => 'Security hardening session', 'description' => 'Server and app checklist implementation.', 'price' => 900.00],
            ['name' => 'GDPR data mapping starter', 'description' => 'Inventory templates and processing register draft.', 'price' => 1200.00],
            ['name' => 'Pen-test remediation bundle', 'description' => 'Up to 20 findings, patch and retest.', 'price' => 2500.00],
            ['name' => 'Email deliverability setup', 'description' => 'SPF, DKIM, DMARC, warm-up guidance.', 'price' => 280.00],
            ['name' => 'CDN + cache configuration', 'description' => 'Edge rules and browser caching policy.', 'price' => 320.00],
            ['name' => 'Database migration service', 'description' => 'Planned cutover, validation scripts.', 'price' => 1100.00],
            ['name' => 'Logo + brand mini kit', 'description' => 'Logo variants, colour tokens, typography.', 'price' => 890.00],
            ['name' => 'Newsletter template pack', 'description' => 'Three responsive modules for your ESP.', 'price' => 420.00],
            ['name' => 'Chatbot flow — FAQ (10 intents)', 'description' => 'Structured answers, handoff to human.', 'price' => 750.00],
            ['name' => 'Inventory CSV import', 'description' => 'One-time import mapping and validation.', 'price' => 275.00],
            ['name' => 'Multi-language site setup', 'description' => 'Two locales, hreflang, translation workflow.', 'price' => 980.00],
        ];

        $categoryCount = count($categoryIds);
        foreach ($catalogue as $index => $row)
        {
            Product::withoutGlobalScope('team')->create([
                'team_id' => $team->id,
                'name' => $row['name'],
                'description' => $row['description'],
                'price' => $row['price'],
                'currency_id' => $currencyId,
                'category_id' => $categoryIds[$index % $categoryCount],
                'status' => true,
                'whatsapp_enabled' => $index % 4 !== 0,
            ]);
        }

        $this->command?->info('✅ Demo products seeded: '.count($catalogue).' products in '.$categoryCount.' categories (products module enabled for demo team).');
    }
}
