<?php

namespace Tests\Unit\Services\ControlPanel;

use App\Models\Domain;
use App\Models\Server;
use App\Services\ControlPanel\CpanelConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CpanelMxRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_mx_records_uses_list_mxs_and_maps_entries(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => [
                    'status' => 1,
                    'data' => [
                        [
                            'domain' => 'example.test',
                            'entries' => [
                                [
                                    'priority' => '10',
                                    'mx' => 'ALT.ASPMX.L.GOOGLE.COM.',
                                ],
                                [
                                    'priority' => '1',
                                    'mx' => 'ASPMX.L.GOOGLE.COM',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        [$server, $domain] = $this->createServerAndDomain();

        $result = app(CpanelConnector::class)->getMxRecords($server, $domain);

        $this->assertTrue($result['success']);
        $this->assertSame([
            [
                'line' => null,
                'priority' => 1,
                'target' => 'ASPMX.L.GOOGLE.COM',
            ],
            [
                'line' => null,
                'priority' => 10,
                'target' => 'ALT.ASPMX.L.GOOGLE.COM',
            ],
        ], $result['records']);

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), '/json-api/cpanel')
                && ($request['cpanel_jsonapi_module'] ?? null) === 'Email'
                && ($request['cpanel_jsonapi_func'] ?? null) === 'list_mxs'
                && ($request['domain'] ?? null) === 'example.test';
        });
    }

    public function test_update_mx_records_deletes_existing_and_adds_new(): void
    {
        Http::fake(function ($request)
        {
            $func = $request['cpanel_jsonapi_func'] ?? null;

            if ($func === 'list_mxs')
            {
                return Http::response([
                    'result' => [
                        'status' => 1,
                        'data' => [
                            [
                                'domain' => 'example.test',
                                'entries' => [
                                    [
                                        'priority' => '5',
                                        'mx' => 'old.mail.test',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response([
                'result' => [
                    'status' => 1,
                ],
            ]);
        });

        [$server, $domain] = $this->createServerAndDomain();

        $result = app(CpanelConnector::class)->updateMxRecords($server, $domain, [
            [
                'priority' => 10,
                'target' => 'mail.example.test',
            ],
        ]);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request)
        {
            return ($request['cpanel_jsonapi_func'] ?? null) === 'delete_mx'
                && ($request['exchanger'] ?? null) === 'old.mail.test'
                && (int) ($request['priority'] ?? -1) === 5;
        });

        Http::assertSent(function ($request)
        {
            return ($request['cpanel_jsonapi_func'] ?? null) === 'add_mx'
                && ($request['exchanger'] ?? null) === 'mail.example.test'
                && (int) ($request['priority'] ?? -1) === 10;
        });
    }

    /**
     * @return array{0: Server, 1: Domain}
     */
    private function createServerAndDomain(): array
    {
        $user = \App\Models\User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'cpanel.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'example.test',
            'server_id' => $server->id,
            'username' => 'siteuser',
            'suspended' => false,
        ]);

        return [$server, $domain];
    }
}
