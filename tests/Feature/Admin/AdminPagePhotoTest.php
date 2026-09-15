<?php

namespace Tests\Feature\Admin;

use App\Enums\PagePhotoCollection;
use App\Enums\Role;
use App\Models\PagePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPagePhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);
    }

    public function test_customer_cannot_manage_page_photos(): void
    {
        $this->actingAs($this->customer)
            ->get(route('admin.photos.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_photo_manager(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.photos.index'))
            ->assertOk()
            ->assertSee('Gallery')
            ->assertDontSee('Cafe & Restobar');
    }

    public function test_admin_can_upload_gallery_photos(): void
    {
        $previousCount = PagePhoto::count();
        $this->actingAs($this->admin)->post(route('admin.photos.store'), [
            'collection' => PagePhotoCollection::Gallery->value,
            'images' => [UploadedFile::fake()->image('pool.jpg', 800, 600)],
            'title' => 'Pool by day',
            'caption' => 'Nature-framed swimming for all ages.',
        ])->assertRedirect(route('admin.photos.index', ['collection' => 'gallery']));

        $this->assertDatabaseHas('page_photos', [
            'collection' => 'gallery',
            'title' => 'Pool by day',
            'caption' => 'Nature-framed swimming for all ages.',
        ]);

        $this->assertSame($previousCount + 1, PagePhoto::count());
        $photo = PagePhoto::query()->where('title', 'Pool by day')->latest('id')->first();
        $this->assertNotNull($photo);
        $this->assertStringEndsWith('.webp', $photo->image_path);
        $this->assertTrue(Storage::disk('public')->exists($photo->image_path));
        $this->assertStringStartsWith('/storage/', $photo->url());

        $this->get(route('gallery'))
            ->assertOk()
            ->assertSee('Pool by day')
            ->assertSee('Nature-framed swimming for all ages.')
            ->assertSee($photo->url(), false);
    }

    public function test_admin_cannot_upload_to_removed_dining_collection(): void
    {
        $this->actingAs($this->admin)->post(route('admin.photos.store'), [
            'collection' => 'dining',
            'images' => [UploadedFile::fake()->image('cafe.jpg', 800, 600)],
            'title' => 'Cafe interior',
        ])->assertSessionHasErrors('collection');
    }

    public function test_admin_can_update_and_delete_a_photo(): void
    {
        $photo = PagePhoto::factory()->create([
            'collection' => PagePhotoCollection::Gallery,
            'image_path' => 'pages/gallery/keep.jpg',
            'title' => 'Old title',
        ]);

        Storage::disk('public')->put($photo->image_path, 'fake-image');

        $this->actingAs($this->admin)->patch(route('admin.photos.update', $photo), [
            'title' => 'Updated title',
            'caption' => 'Updated caption',
            'sort_order' => 3,
        ])->assertRedirect(route('admin.photos.index', ['collection' => 'gallery']));

        $this->assertDatabaseHas('page_photos', [
            'id' => $photo->id,
            'title' => 'Updated title',
            'caption' => 'Updated caption',
            'sort_order' => 3,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.photos.destroy', $photo))
            ->assertRedirect(route('admin.photos.index', ['collection' => 'gallery']));

        $this->assertDatabaseMissing('page_photos', ['id' => $photo->id]);
        $this->assertFalse(Storage::disk('public')->exists('pages/gallery/keep.jpg'));
    }

    public function test_gallery_page_falls_back_to_placeholders_without_uploads(): void
    {
        PagePhoto::query()->delete();

        $this->get(route('gallery'))
            ->assertOk()
            ->assertSee('Function hall')
            ->assertDontSee('Cafe & restobar');
    }
}
