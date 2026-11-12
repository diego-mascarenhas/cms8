<?php

namespace Tests\Feature\Api;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCertificateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_token_lists_and_downloads_a_certificate_for_that_email(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext'))
        {
            $this->markTestSkipped('GD is required.');
        }

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Revision Alpha',
        ]);
        $token = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $token), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $image = imagecreatetruecolor(800, 500);
        imagefilledrectangle($image, 0, 0, 799, 499, imagecolorallocate($image, 255, 255, 255));
        $path = storage_path('app/survey-certificate-api-test.jpg');
        imagejpeg($image, $path);
        imagedestroy($image);

        $person = User::factory()->create([
            'name' => 'Alan Robert Rodriguez',
            'email' => 'alan.04rt@gmail.com',
        ]);
        $survey = Survey::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'title' => 'Conferencia SCI',
            'is_active' => true,
            'certificate_url' => $path,
            'schema_json' => ['title' => 'Conferencia SCI', 'fields' => []],
        ]);
        SurveyResponse::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'survey_id' => $survey->id,
            'user_id' => $person->id,
            'certified' => true,
            'payload_json' => ['answers' => []],
        ]);

        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/team/certificates?email=Alan.04rt@gmail.com', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.survey_id', $survey->id)
            ->assertJsonPath('data.0.title', 'Conferencia SCI');

        $download = $this->get('/api/team/certificates/'.$survey->id.'?email=alan.04rt@gmail.com', $headers);

        $download->assertOk();
        $this->assertSame('image/jpeg', $download->headers->get('content-type'));
        $this->assertStringStartsWith("\xFF\xD8", $download->getContent());
        $this->assertStringContainsString(
            'revision-alpha-conferencia-sci-alan-robert-rodriguez-',
            (string) $download->headers->get('content-disposition'),
        );

        $this->getJson('/api/team/certificates?email=otra@example.com', $headers)
            ->assertOk()
            ->assertJsonPath('data', []);

        @unlink($path);
    }

    public function test_certificate_lookup_requires_the_team_token(): void
    {
        $this->getJson('/api/team/certificates?email=alan@example.com')->assertUnauthorized();
    }
}
