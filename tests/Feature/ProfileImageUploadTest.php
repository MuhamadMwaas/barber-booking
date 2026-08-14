<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        foreach (['admin', 'customer'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    protected function makeUser(string $role): User
    {
        $user = User::create([
            'first_name' => 'Ava',
            'last_name' => 'Tester',
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'locale' => 'en',
        ]);

        $user->assignRole($role);

        return $user;
    }

    public function test_uploading_an_avatar_moves_it_out_of_the_temp_directory(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $customer->getRouteKey()])
            ->fillForm([
                'profile_image_file' => [UploadedFile::fake()->image('new-avatar.png')],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $file = $customer->fresh()->profile_image;

        $this->assertNotNull($file, 'No File record was created for the avatar.');
        $this->assertStringStartsWith("users/profile_images/{$customer->id}/", $file->path);
        $this->assertStringNotContainsString('temp/uploads', $file->path);
        $this->assertTrue(
            Storage::disk('public')->exists($file->path),
            "Avatar is recorded at {$file->path} but that file does not exist on disk."
        );
    }

    public function test_replacing_an_existing_avatar_picks_the_new_upload(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');

        $this->actingAs($admin);

        // First upload, so the record already holds an avatar.
        $customer->updateProfileImage(UploadedFile::fake()->image('first.png'));
        $originalPath = $customer->fresh()->profile_image->path;

        Livewire::test(EditUser::class, ['record' => $customer->getRouteKey()])
            ->fillForm([
                'profile_image_file' => [UploadedFile::fake()->image('second.png')],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $newPath = $customer->fresh()->profile_image->path;

        $this->assertNotSame($originalPath, $newPath, 'The avatar was not replaced by the new upload.');
        $this->assertTrue(Storage::disk('public')->exists($newPath));
    }

    public function test_saving_without_touching_the_avatar_keeps_the_existing_one(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');

        $this->actingAs($admin);

        $customer->updateProfileImage(UploadedFile::fake()->image('kept.png'));
        $originalPath = $customer->fresh()->profile_image->path;

        Livewire::test(EditUser::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['first_name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($originalPath, $customer->fresh()->profile_image->path);
        $this->assertSame('Renamed', $customer->fresh()->first_name);
    }
}
