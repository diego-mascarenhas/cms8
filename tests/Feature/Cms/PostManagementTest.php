<?php

namespace Tests\Feature\Cms;

use App\Models\Post;
use App\Models\Team;
use App\Models\Term;
use App\Models\TermTaxonomy;
use App\Models\User;
use Database\Seeders\PostTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithTeam(): User
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('admin');

        PostTypeSeeder::seedForTeam($team);

        return $user->refresh();
    }

    public function test_index_renders_for_post_type(): void
    {
        $user = $this->adminWithTeam();

        $this->actingAs($user)
            ->get(route('cms.pages.index'))
            ->assertOk()
            ->assertSee('Páginas');
    }

    public function test_store_creates_post_and_generates_slug(): void
    {
        $user = $this->adminWithTeam();

        $response = $this->actingAs($user)->post(route('cms.posts.store'), [
            'post_type' => 'page',
            'post_title' => 'About Us',
            'post_content' => '<p>Hi</p>',
            'post_status' => 'publish',
        ]);

        $response->assertRedirect(route('cms.pages.index'));
        $this->assertDatabaseHas('posts', [
            'team_id' => $user->current_team_id,
            'post_type' => 'page',
            'post_title' => 'About Us',
            'post_name' => 'about-us',
            'post_status' => 'publish',
        ]);
    }

    public function test_update_changes_post(): void
    {
        $user = $this->adminWithTeam();
        $post = Post::create([
            'team_id' => $user->current_team_id,
            'post_type' => 'page',
            'post_title' => 'Old',
            'post_status' => 'draft',
        ]);

        $this->actingAs($user)->put(route('cms.posts.update', $post->id), [
            'post_type' => 'page',
            'post_title' => 'New title',
            'post_status' => 'publish',
        ])->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'post_title' => 'New title',
            'post_status' => 'publish',
        ]);
    }

    public function test_destroy_soft_deletes_post(): void
    {
        $user = $this->adminWithTeam();
        $post = Post::create([
            'team_id' => $user->current_team_id,
            'post_type' => 'page',
            'post_title' => 'Bye',
            'post_status' => 'publish',
        ]);

        $this->actingAs($user)->delete(route('cms.posts.destroy', $post->id))->assertRedirect();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_taxonomy_terms_are_synced(): void
    {
        $user = $this->adminWithTeam();
        $term = Term::create([
            'team_id' => $user->current_team_id,
            'name' => 'News',
            'slug' => 'news',
        ]);
        $termTaxonomy = TermTaxonomy::create([
            'team_id' => $user->current_team_id,
            'term_id' => $term->id,
            'taxonomy' => TermTaxonomy::TAXONOMY_CATEGORY,
        ]);

        $post = Post::create([
            'team_id' => $user->current_team_id,
            'post_type' => 'page',
            'post_title' => 'Tagged',
            'post_status' => 'publish',
        ]);

        $this->actingAs($user)->put(route('cms.posts.update', $post->id), [
            'post_type' => 'page',
            'post_title' => 'Tagged',
            'post_status' => 'publish',
            'terms' => [$termTaxonomy->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('term_relationships', [
            'object_id' => $post->id,
            'term_taxonomy_id' => $termTaxonomy->id,
        ]);
    }

    public function test_global_scope_limits_posts_to_current_team(): void
    {
        $user = $this->adminWithTeam();

        $otherUser = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $otherUser->id]);
        Post::withoutGlobalScopes()->create([
            'team_id' => $otherTeam->id,
            'post_type' => 'page',
            'post_title' => 'Foreign',
            'post_status' => 'publish',
        ]);

        $this->actingAs($user);
        $this->assertSame(0, Post::query()->count());
    }
}
