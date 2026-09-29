<?php

namespace Tests\Feature;

use App\Enums\TeamBillingProduct;
use App\Models\TeamBillingRate;
use App\Models\TeamFile;
use App\Models\User;
use App\Services\TeamBillingUsageSummaryService;
use App\Services\TeamStorageUsageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StorageUsageBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_few_megabytes_show_on_the_invoice(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        $this->assertNotNull($team);

        $file = TeamFile::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $this->attachMedia(TeamFile::class, (int) $file->id, 11 * (1024 ** 2));

        $stats = TeamStorageUsageStatsService::forTeam($team);
        $this->assertSame(22, $stats['our_amount_cents']);
        $this->assertSame('11,0 MB', $stats['formatted_size']);
    }

    public function test_current_storage_is_billed_per_megabyte_and_shown_per_team(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        $this->assertNotNull($team);

        $file = TeamFile::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $this->attachMedia(TeamFile::class, (int) $file->id, 1024 ** 3);

        $removed = TeamFile::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $this->attachMedia(TeamFile::class, (int) $removed->id, 5 * (1024 ** 3));
        $removed->delete();

        $other = User::factory()->withPersonalTeam()->create();
        $otherFile = TeamFile::factory()->create([
            'team_id' => $other->currentTeam->id,
            'created_by' => $other->id,
            'updated_by' => $other->id,
        ]);
        $this->attachMedia(TeamFile::class, (int) $otherFile->id, 2 * (1024 ** 3));

        $stats = TeamStorageUsageStatsService::forTeam($team);
        $this->assertSame(1024 ** 3, $stats['bytes']);
        $this->assertSame(2048, $stats['our_amount_cents']);
        $this->assertSame('1,00 GB', $stats['formatted_size']);

        $usage = app(TeamBillingUsageSummaryService::class)->currentMonth($team);
        $this->assertSame(1024 ** 3, $usage['storage_bytes']);
        $this->assertSame(2048, $usage['storage_billed_cents']);
        $this->assertSame(2048, $usage['billed_cents']);
        $this->assertSame('1,00 GB / 20,48 EUR', $usage['formatted']['storage']);

        $lines = app(TeamBillingUsageSummaryService::class)->billableLines($usage, $usage['period_label']);
        $storage = collect($lines)->firstWhere('kind', 'storage');
        $this->assertNotNull($storage);
        $this->assertSame(2048, $storage['amount_cents']);
        $this->assertSame('1,00 GB', $storage['detail']);

        $past = app(TeamBillingUsageSummaryService::class)->forMonth($team, now()->subMonth());
        $this->assertSame(0, $past['storage_bytes']);
        $this->assertSame(0, $past['storage_billed_cents']);

        $closed = app(TeamBillingUsageSummaryService::class)->forClosedWindow(
            $team,
            now()->startOfMonth(),
            now()->addMonth()->startOfMonth(),
            \App\Enums\TeamBillingFrequency::Monthly,
        );
        $this->assertSame(2048, $closed['storage_billed_cents']);

        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::StorageMegabyte, 0.1);
        $repriced = TeamStorageUsageStatsService::forTeam($team->fresh());
        $this->assertSame(10240, $repriced['our_amount_cents']);

        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);
        $root = User::factory()->create();
        $root->assignRole('root');

        $response = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountQuery()));

        $response->assertOk();
        $rows = collect($response->json('data'));
        $this->assertTrue($rows->contains(fn (array $row): bool => ($row['storage'] ?? '') === '1,00 GB'));
        $this->assertTrue($rows->contains(fn (array $row): bool => ($row['storage'] ?? '') === '2,00 GB'));
    }

    private function userWithTeam(): User
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        return User::factory()->withPersonalTeam()->create();
    }

    private function attachMedia(string $modelClass, int $modelId, int $bytes): void
    {
        DB::table('media')->insert([
            'model_type' => $modelClass,
            'model_id' => $modelId,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'file',
            'name' => 'file',
            'file_name' => 'file.bin',
            'mime_type' => 'application/octet-stream',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => $bytes,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
            'order_column' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountQuery(): array
    {
        $columns = [];
        foreach ([
            ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'owner_name', 'name' => 'owner_name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'renews_at', 'name' => 'renews_at', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'usage_billed', 'name' => 'usage_billed', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'storage', 'name' => 'storage', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
        ] as $def)
        {
            $columns[] = array_merge($def, [
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 3, 'dir' => 'asc']],
            'columns' => $columns,
        ];
    }
}
