<?php

namespace Tests\Feature;

use App\DataTables\MessageDataTable;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageDataTableQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_datatable_query_eager_loads_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $dataTable = app(MessageDataTable::class);
        $eagerLoads = $dataTable->query(new Message)->getEagerLoads();

        $this->assertArrayHasKey('deliveries', $eagerLoads);
        $this->assertArrayHasKey('category', $eagerLoads);
        $this->assertArrayHasKey('contactStatus', $eagerLoads);
    }
}
