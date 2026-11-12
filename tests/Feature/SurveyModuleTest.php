<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Team;
use App\Models\User;
use App\Services\LegacySurveyImporter;
use App\Support\SurveySchema;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class SurveyModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);
    }

    public function test_admin_creates_a_survey_as_json_fields(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->post(route('survey.store'), [
                'title' => 'Conferencia SCI',
                'code' => 'SCI-IF',
                'is_active' => 1,
                'fields' => [
                    [
                        'label' => '¿Cómo calificarías la dinámica?',
                        'options_text' => "Excelente\nRegular",
                    ],
                ],
            ])
            ->assertRedirect();

        $survey = Survey::withoutGlobalScope('team')->where('title', 'Conferencia SCI')->first();

        $this->assertNotNull($survey);
        $this->assertSame('Conferencia SCI', $survey->schema_json['title']);
        $this->assertSame('radio', $survey->schema_json['fields'][0]['type']);
        $this->assertSame('Excelente', $survey->schema_json['fields'][0]['options'][0]['label']);
        $this->assertSame('1', $survey->schema_json['fields'][0]['options'][0]['value']);
    }

    public function test_results_show_percentages_and_hide_anonymous_names(): void
    {
        $user = $this->makeAdminUser();
        $survey = Survey::withoutGlobalScope('team')->create([
            'team_id' => $user->current_team_id,
            'title' => 'Conferencia',
            'is_active' => true,
            'schema_json' => SurveySchema::document('Conferencia', [
                SurveySchema::field('q10', '¿Cómo calificarías la dinámica?', [
                    ['label' => 'Excelente', 'value' => '1'],
                    ['label' => 'Regular', 'value' => '2'],
                ], true, true),
                SurveySchema::field('q11', '¿Cuál es la afirmación correcta?', [
                    ['label' => 'La A', 'value' => '8'],
                    ['label' => 'La B', 'value' => '9'],
                ], true, false),
            ]),
        ]);

        $respondent = User::factory()->create([
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
        ]);

        SurveyResponse::withoutGlobalScope('team')->create([
            'team_id' => $user->current_team_id,
            'survey_id' => $survey->id,
            'user_id' => $respondent->id,
            'payload_json' => ['answers' => ['q10' => '1', 'q11' => '8']],
        ]);

        $this->actingAs($user)
            ->get(route('survey.show', $survey))
            ->assertOk()
            ->assertSee('¿Cómo calificarías la dinámica?')
            ->assertSee('100%')
            ->assertSee('Ana Pérez')
            ->assertSee('Anónima');
    }

    public function test_legacy_rows_become_one_response_per_contact(): void
    {
        $user = $this->makeAdminUser();

        $result = app(LegacySurveyImporter::class)->importRows(
            (int) $user->current_team_id,
            [
                ['id' => 404, 'titulo' => 'Conferencia SCI', 'codigo' => 'SCI-IF', 'fecha_vencimiento' => '2026-12-01', 'estado' => 2, 'id_empresa' => 7437, 'subtitulo' => null],
            ],
            [
                ['id' => 10, 'id_evento' => 404, 'titulo' => '¿Cómo calificarías la dinámica?', 'subtitulo' => null, 'obligatoria' => 1, 'anonima' => 0, 'para_certificar' => 0, 'orden' => 1, 'estado' => 2],
            ],
            [
                ['id' => 8, 'id_pregunta' => 10, 'titulo' => 'Excelente', 'orden' => 1, 'estado' => 2],
                ['id' => 9, 'id_pregunta' => 10, 'titulo' => 'Regular', 'orden' => 2, 'estado' => 2],
            ],
            [
                ['id' => 50, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'ana@example.com'],
            ],
            [
                ['id_contacto' => 50, 'id_pregunta' => 10, 'id_respuesta' => 8],
            ],
        );

        $this->assertSame(['surveys' => 1, 'responses' => 1], $result);

        $survey = Survey::withoutGlobalScope('team')->where('code', 'SCI-IF')->first();
        $this->assertNotNull($survey);
        $this->assertTrue($survey->is_active);
        $this->assertFalse($survey->schema_json['fields'][0]['anonymous']);
        $this->assertSame('8', $survey->schema_json['fields'][0]['options'][0]['value']);

        $response = SurveyResponse::withoutGlobalScope('team')->where('survey_id', $survey->id)->first();
        $this->assertSame('Ana Pérez', $response->user->name);
        $this->assertSame('ana@example.com', $response->user->email);
        $this->assertTrue($response->user->teams()->where('teams.id', $user->current_team_id)->exists());
        $this->assertSame(['q10' => '8'], $response->payload_json['answers']);
        $this->assertNotNull(
            Contact::withoutGlobalScopes()
                ->where('team_id', $user->current_team_id)
                ->where('user_id', $response->user_id)
                ->whereRaw('LOWER(email) = ?', ['ana@example.com'])
                ->first(),
        );

        $results = $survey->fresh()->fieldResults();
        $this->assertSame(1, $results[0]['options'][0]['count']);
        $this->assertSame(['Ana Pérez'], $results[0]['options'][0]['voters']);
    }

    public function test_a_certified_person_downloads_the_certificate_with_their_email(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext'))
        {
            $this->markTestSkipped('GD is required.');
        }

        $image = imagecreatetruecolor(800, 500);
        imagefilledrectangle($image, 0, 0, 799, 499, imagecolorallocate($image, 255, 255, 255));
        $path = storage_path('app/survey-certificate-test.jpg');
        imagejpeg($image, $path);
        imagedestroy($image);

        $user = $this->makeAdminUser();
        $user->currentTeam->forceFill(['name' => 'Revision Alpha'])->save();
        $respondent = User::factory()->create([
            'name' => 'Alan Robert Rodriguez',
            'email' => 'alan.04rt@gmail.com',
        ]);
        $survey = Survey::withoutGlobalScope('team')->create([
            'team_id' => $user->current_team_id,
            'title' => 'Conferencia SCI',
            'is_active' => true,
            'certificate_url' => $path,
            'schema_json' => ['title' => 'Conferencia SCI', 'fields' => []],
        ]);
        SurveyResponse::withoutGlobalScope('team')->create([
            'team_id' => $user->current_team_id,
            'survey_id' => $survey->id,
            'user_id' => $respondent->id,
            'certified' => true,
            'payload_json' => ['answers' => []],
        ]);

        $this->get(route('survey.certificate', $survey))
            ->assertOk()
            ->assertSee('Descargar certificado')
            ->assertSee('Conferencia SCI')
            ->assertSee('Email con el que te inscribiste');

        $download = $this->post(route('survey.certificate.download', $survey), [
            'email' => 'Alan.04rt@gmail.com',
        ]);

        $download->assertOk();
        $this->assertSame('image/jpeg', $download->headers->get('content-type'));
        $this->assertStringStartsWith("\xFF\xD8", $download->getContent());
        $this->assertStringContainsString(
            'revision-alpha-conferencia-sci-alan-robert-rodriguez-',
            (string) $download->headers->get('content-disposition'),
        );
        $this->assertStringNotContainsString(
            'filename="certificado.jpg"',
            (string) $download->headers->get('content-disposition'),
        );

        $this->post(route('survey.certificate.download', $survey), [
            'email' => 'otra@example.com',
        ])->assertSessionHasErrors('email');

        @unlink($path);
    }

    public function test_legacy_link_marks_the_contact_as_certified_without_answers(): void
    {
        $user = $this->makeAdminUser();
        User::factory()->create([
            'name' => 'Alan Robert Rodriguez',
            'email' => 'alan.04rt@gmail.com',
        ]);

        app(LegacySurveyImporter::class)->importRows(
            (int) $user->current_team_id,
            [[
                'id' => 404,
                'titulo' => 'Conferencia SCI',
                'codigo' => 'SCI-IF',
                'estado' => 2,
                'id_empresa' => 7437,
                'certificate_url' => 'https://cms.revisionalpha.com/multimedia/502/7437/cert.jpg',
            ]],
            [],
            [],
            [['id' => 70, 'nombre' => 'Alan Robert', 'apellido' => 'Rodriguez', 'email' => 'alan.04rt@gmail.com']],
            [],
            [['id_evento' => 404, 'id_contacto' => 70, 'certificado' => 1]],
        );

        $response = SurveyResponse::withoutGlobalScope('team')->where('survey_id', Survey::withoutGlobalScope('team')->where('code', 'SCI-IF')->value('id'))->first();
        $this->assertNotNull($response);
        $this->assertTrue($response->certified);
        $this->assertSame('Alan Robert Rodriguez', $response->user->name);
        $this->assertTrue($response->user->teams()->where('teams.id', $user->current_team_id)->exists());
        $this->assertSame(1, User::query()->whereRaw('LOWER(email) = ?', ['alan.04rt@gmail.com'])->count());
        $this->assertSame([], $response->payload_json['answers']);
    }

    public function test_survey_import_targets_the_oba_team(): void
    {
        $this->artisan('import:surveys')
            ->expectsOutputToContain('Team OBA does not exist.')
            ->assertFailed();

        $team = Team::factory()->create(['name' => 'OBA']);

        $this->artisan('import:surveys', ['--team' => $team->id + 9999])
            ->expectsOutputToContain('Team '.($team->id + 9999).' does not exist.')
            ->assertFailed();
    }

    public function test_survey_import_filters_the_oba_company(): void
    {
        Team::factory()->create(['name' => 'OBA']);

        $command = new class extends \App\Console\Commands\ImportSurveysCommand
        {
            public array $filters = [];

            protected function legacyEvents(int $grupo, int $empresaId): Collection
            {
                $this->filters = ['grupo' => $grupo, 'empresa' => $empresaId];

                return collect();
            }

            protected function legacyContacts(int $grupo, int $empresaId): Collection
            {
                return collect();
            }
        };
        $command->setLaravel($this->app);

        $status = $command->run(new ArrayInput([]), new BufferedOutput);

        $this->assertSame(0, $status);
        $this->assertSame(502, $command->filters['grupo']);
        $this->assertSame(7437, $command->filters['empresa']);
    }

    public function test_company_contacts_become_users_and_crm_contacts_of_the_team(): void
    {
        $user = $this->makeAdminUser();

        $count = app(LegacySurveyImporter::class)->importCompanyUsers((int) $user->current_team_id, [
            ['nombre' => 'Marina', 'apellido' => 'Lizama', 'email' => 'marina@lizama.test', 'estado' => 2],
            ['nombre' => 'Inactivo', 'apellido' => 'Fuera', 'email' => 'fuera@lizama.test', 'estado' => -2],
        ]);

        $this->assertSame(1, $count);
        $member = User::query()->where('email', 'marina@lizama.test')->first();
        $this->assertNotNull($member);
        $this->assertSame('Marina Lizama', $member->name);
        $this->assertTrue($member->teams()->where('teams.id', $user->current_team_id)->exists());
        $this->assertNull(User::query()->where('email', 'fuera@lizama.test')->first());

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $user->current_team_id)
            ->whereRaw('LOWER(email) = ?', ['marina@lizama.test'])
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame($member->id, $contact->user_id);
        $this->assertSame('Marina', $contact->name);
        $this->assertSame('Lizama', $contact->surname);
        $this->assertSame(1, $contact->status_id);
        $this->assertNull(
            Contact::withoutGlobalScopes()
                ->where('team_id', $user->current_team_id)
                ->whereRaw('LOWER(email) = ?', ['fuera@lizama.test'])
                ->first(),
        );
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $this->enableTeamModules($team, ['surveys']);

        return $user->fresh();
    }
}
