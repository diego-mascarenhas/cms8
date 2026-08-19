<?php

namespace Tests\Feature\Multimedia;

use App\Enums\MultimediaStatus;
use App\Enums\MultimediaVisibility;
use App\Models\Category;
use App\Models\Module;
use App\Models\Multimedia;
use App\Models\MultimediaGalleryItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Tags\Tag;
use Tests\TestCase;

class MultimediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_multimedia_store_creates_items_with_tags_and_gallery(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithRole('collaborator');
        $category = $this->createMultimediaCategory($user->currentTeam);

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($user)->post(route('multimedia.store'), [
            'title' => 'Sample Media',
            'description' => 'Sample description',
            'status' => MultimediaStatus::ACTIVE->value,
            'visibility' => MultimediaVisibility::PUBLIC->value,
            'category_id' => $category->id,
            'files' => [$file],
            'tags' => ['summer'],
            'galleries' => ['homepage'],
        ]);

        $response->assertRedirect(route('multimedia.index'));

        $this->assertDatabaseHas('multimedia', [
            'title' => 'Sample Media',
            'category_id' => $category->id,
            'status' => MultimediaStatus::ACTIVE->value,
            'visibility' => MultimediaVisibility::PUBLIC->value,
        ]);

        $multimedia = Multimedia::first();
        $this->assertNotNull($multimedia);
        $this->assertNotNull($multimedia->getFirstMedia('media'));

        $galleryTag = Tag::getWithType('gallery')->first();
        $this->assertNotNull($galleryTag);

        $this->assertDatabaseHas('multimedia_gallery_items', [
            'multimedia_id' => $multimedia->id,
            'tag_id' => $galleryTag->id,
        ]);
    }

    public function test_cards_search_respects_filters(): void
    {
        $user = $this->createUserWithRole('collaborator');
        $category = $this->createMultimediaCategory($user->currentTeam);

        $matching = Multimedia::factory()->create([
            'team_id' => $user->currentTeam->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'title' => 'Summer Campaign',
            'status' => MultimediaStatus::ACTIVE->value,
            'visibility' => MultimediaVisibility::PUBLIC->value,
        ]);

        $differentStatus = Multimedia::factory()->create([
            'team_id' => $user->currentTeam->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'title' => 'Summer Internal',
            'status' => MultimediaStatus::INACTIVE->value,
            'visibility' => MultimediaVisibility::PUBLIC->value,
        ]);

        $differentTitle = Multimedia::factory()->create([
            'team_id' => $user->currentTeam->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'title' => 'Winter Campaign',
            'status' => MultimediaStatus::ACTIVE->value,
            'visibility' => MultimediaVisibility::PUBLIC->value,
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson(route('multimedia.index', [
                'view' => 'cards',
                'search' => 'Summer',
                'status' => MultimediaStatus::ACTIVE->value,
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'cards')
            ->assertJsonFragment(['id' => $matching->id])
            ->assertJsonMissing(['id' => $differentStatus->id])
            ->assertJsonMissing(['id' => $differentTitle->id]);
    }

    public function test_cards_tag_and_gallery_filters(): void
    {
        $user = $this->createUserWithRole('collaborator');
        $category = $this->createMultimediaCategory($user->currentTeam);

        $matching = Multimedia::factory()->create([
            'team_id' => $user->currentTeam->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $matching->syncTagsWithType(['Featured'], 'general');
        $matching->syncTagsWithType(['Homepage'], 'gallery');

        $wrongTag = Multimedia::factory()->create([
            'team_id' => $user->currentTeam->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $wrongTag->syncTagsWithType(['Other'], 'general');
        $wrongTag->syncTagsWithType(['Homepage'], 'gallery');

        $wrongGallery = Multimedia::factory()->create([
            'team_id' => $user->currentTeam->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $wrongGallery->syncTagsWithType(['Featured'], 'general');
        $wrongGallery->syncTagsWithType(['OtherGallery'], 'gallery');

        $response = $this->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson(route('multimedia.index', [
                'view' => 'cards',
                'tags' => ['Featured'],
                'galleries' => ['Homepage'],
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'cards')
            ->assertJsonFragment(['id' => $matching->id])
            ->assertJsonMissing(['id' => $wrongTag->id])
            ->assertJsonMissing(['id' => $wrongGallery->id]);
    }

    public function test_multimedia_update_replaces_media_and_syncs_tags(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithRole('collaborator');
        $category = $this->createMultimediaCategory($user->currentTeam);

        $multimedia = Multimedia::factory()->create([
            'team_id' => $user->currentTeam->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $multimedia->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('media');
        $multimedia->syncTagsWithType(['old-tag'], 'general');
        $multimedia->syncTagsWithType(['old-gallery'], 'gallery');
        MultimediaGalleryItem::create([
            'multimedia_id' => $multimedia->id,
            'tag_id' => Tag::getWithType('gallery')->first()->id,
            'order' => 0,
        ]);

        $response = $this->actingAs($user)->put(route('multimedia.update', $multimedia->id), [
            'title' => 'Updated Media',
            'description' => 'Updated description',
            'status' => MultimediaStatus::ACTIVE->value,
            'visibility' => MultimediaVisibility::PRIVATE->value,
            'category_id' => $category->id,
            'tags' => ['new-tag'],
            'galleries' => ['new-gallery'],
            'media' => UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect(route('multimedia.index'));

        $multimedia->refresh();

        $this->assertSame('Updated Media', $multimedia->title);
        $this->assertSame('document', $multimedia->type);
        $this->assertTrue($multimedia->tags->where('type', 'general')->contains('name', 'new-tag'));
        $this->assertFalse($multimedia->tags->where('type', 'general')->contains('name', 'old-tag'));
    }

    public function test_gallery_order_endpoint_updates_order(): void
    {
        $user = $this->createUserWithRole('collaborator');
        $category = $this->createMultimediaCategory($user->currentTeam);
        $galleryTag = Tag::findOrCreate('Landing Gallery', 'gallery');

        $first = Multimedia::factory()->create([
            'team_id' => $user->currentTeam->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $second = Multimedia::factory()->create([
            'team_id' => $user->currentTeam->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        MultimediaGalleryItem::create([
            'multimedia_id' => $first->id,
            'tag_id' => $galleryTag->id,
            'order' => 0,
        ]);

        MultimediaGalleryItem::create([
            'multimedia_id' => $second->id,
            'tag_id' => $galleryTag->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->postJson(route('multimedia.gallery.order'), [
            'gallery_tag_id' => $galleryTag->id,
            'items' => [
                ['id' => $second->id, 'order' => 0],
                ['id' => $first->id, 'order' => 1],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('multimedia_gallery_items', [
            'multimedia_id' => $second->id,
            'tag_id' => $galleryTag->id,
            'order' => 0,
        ]);
    }

    public function test_non_authorized_user_cannot_create_multimedia(): void
    {
        $user = $this->createUserWithRole('client');

        $response = $this->actingAs($user)->post(route('multimedia.store'), [
            'status' => MultimediaStatus::ACTIVE->value,
            'visibility' => MultimediaVisibility::PUBLIC->value,
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $response->assertDeniedForBrowser();
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    private function createMultimediaCategory(Team $team): Category
    {
        $module = Module::firstOrCreate(
            ['key' => 'multimedia'],
            [
                'name' => 'Multimedia',
                'icon' => 'photo',
                'description' => 'Multimedia module',
                'status' => 1,
            ],
        );

        return Category::factory()->create([
            'module_id' => $module->id,
            'team_id' => $team->id,
        ]);
    }
}
