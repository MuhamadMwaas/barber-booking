<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_image_update_returns_a_new_url_and_current_profile_fields(): void
    {
        Storage::fake('public');

        $user = User::create([
            'first_name' => 'Image',
            'last_name' => 'Tester',
            'email' => 'image-tester@example.com',
            'phone' => '123456789',
            'password' => 'Password@123',
            'address' => 'Old address',
            'city' => 'Old city',
            'registration_method' => 'email',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $firstResponse = $this->post('/api/profile', [
            'first_name' => 'Image',
            'address' => 'New address',
            'city' => 'Berlin',
            'image' => UploadedFile::fake()->image('avatar.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $firstResponse
            ->assertOk()
            ->assertJsonPath('data.address', 'New address')
            ->assertJsonPath('data.city', 'Berlin');

        $firstUrl = $firstResponse->json('data.profile_image_url');
        $firstPath = File::query()->sole()->path;

        Storage::disk('public')->assertExists($firstPath);

        $secondResponse = $this->post('/api/profile', [
            'image' => UploadedFile::fake()->image('avatar.jpg', 320, 320),
        ], [
            'Accept' => 'application/json',
        ]);

        $secondResponse
            ->assertOk()
            ->assertJsonPath('data.avatar_url', $secondResponse->json('data.profile_image_url'));

        $secondUrl = $secondResponse->json('data.profile_image_url');
        $secondPath = File::query()->sole()->path;

        $this->assertNotSame($firstUrl, $secondUrl, 'Profile image URL should change after replacing the file.');
        $this->assertNotSame($firstPath, $secondPath, 'Stored profile image path should change after replacing the file.');

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertDatabaseCount('files', 1);
    }
}
