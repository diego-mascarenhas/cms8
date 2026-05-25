<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageAudienceContactsQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
    }

    #[Test]
    public function null_contact_status_includes_all_statuses_with_email(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->ownedTeams()->first()->id;

        $leadStatusId = (int) ContactStatus::where('name', 'Lead')->value('id');
        $clientStatusId = (int) ContactStatus::where('name', 'Cliente')->value('id');

        Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
            'email' => 'lead@company.test',
        ]);

        Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $clientStatusId,
            'email' => 'client@company.test',
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'All statuses',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'contact_status_id' => null,
        ]);

        $this->assertSame(2, $message->audienceContactsQuery()->count());
    }

    #[Test]
    public function specific_contact_status_filters_audience(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->ownedTeams()->first()->id;

        $leadStatusId = (int) ContactStatus::where('name', 'Lead')->value('id');
        $clientStatusId = (int) ContactStatus::where('name', 'Cliente')->value('id');

        Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
            'email' => 'lead-only@company.test',
        ]);

        Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $clientStatusId,
            'email' => 'client-only@company.test',
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Leads only',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'contact_status_id' => $leadStatusId,
        ]);

        $emails = $message->audienceContactsQuery()->pluck('email')->all();

        $this->assertSame(['lead-only@company.test'], $emails);
    }

    #[Test]
    public function category_audience_returns_builder_and_filters_by_category(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->ownedTeams()->first()->id;

        $category = Category::factory()->create(['team_id' => $teamId]);

        $inCategory = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'in-category@company.test',
        ]);

        Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'outside@company.test',
        ]);

        $category->contacts()->attach($inCategory->id);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Category audience',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'contact_status_id' => null,
        ]);
        $message->syncMessageCategories([$category->id]);

        $query = $message->audienceContactsQuery();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertSame(['in-category@company.test'], $query->pluck('email')->all());
    }

    #[Test]
    public function multiple_categories_union_deduplicates_contacts(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->ownedTeams()->first()->id;

        $categoryA = Category::factory()->create(['team_id' => $teamId, 'name' => 'Segment A']);
        $categoryB = Category::factory()->create(['team_id' => $teamId, 'name' => 'Segment B']);

        $shared = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'shared@company.test',
        ]);

        $onlyB = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'only-b@company.test',
        ]);

        $categoryA->contacts()->attach($shared->id);
        $categoryB->contacts()->attach([$shared->id, $onlyB->id]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Multi category',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
        ]);
        $message->syncMessageCategories([$categoryA->id, $categoryB->id]);

        $emails = $message->audienceContactsQuery()->orderBy('email')->pluck('email')->all();

        $this->assertSame(['only-b@company.test', 'shared@company.test'], $emails);
        $this->assertSame(2, $message->audienceContactsQuery()->count());
    }
}
