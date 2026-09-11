<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use App\Support\ImageUploadRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AUTH-06 — guards the profile image whitelist on POST /api/profile.
 *
 * The rule that used to sit here (`image|max:2048`) bounded only the bytes that
 * arrive. These tests pin the two things it did not bound: which formats are
 * accepted, and how many pixels the accepted file is allowed to declare.
 */
class ProfileImageValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsCustomer(): User
    {
        $user = User::create([
            'first_name' => 'Ava',
            'last_name' => 'Tester',
            'email' => 'avatar-' . uniqid() . '@example.com',
            'password' => 'Password@123',
            'registration_method' => 'email',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    protected function upload(UploadedFile $image)
    {
        return $this->post('/api/profile', ['image' => $image], ['Accept' => 'application/json']);
    }

    public function test_an_ordinary_jpeg_avatar_is_still_accepted(): void
    {
        Storage::fake('public');
        $user = $this->actingAsCustomer();

        $this->upload(UploadedFile::fake()->image('avatar.jpg', 512, 512))->assertOk();

        Storage::disk('public')->assertExists($user->fresh()->profile_image->path);
    }

    public function test_a_real_webp_avatar_is_accepted(): void
    {
        Storage::fake('public');
        $user = $this->actingAsCustomer();

        $this->upload($this->fakeWebp())->assertOk();

        $this->assertSame('webp', $user->fresh()->profile_image->extension);
    }

    /**
     * A GIF passes Laravel's bare `image` rule — its whitelist is jpg, jpeg,
     * png, gif, bmp and webp. It has no business being an avatar, and an
     * animated one is an unbounded number of frames in a small file.
     */
    public function test_a_gif_is_rejected_even_though_the_image_rule_allows_it(): void
    {
        Storage::fake('public');
        $this->actingAsCustomer();

        $this->upload(UploadedFile::fake()->image('animated.gif', 64, 64))
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');

        $this->assertSame(0, File::query()->count());
    }

    public function test_an_svg_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsCustomer();

        $svg = UploadedFile::fake()->createWithContent(
            'avatar.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>'
        );

        $this->upload($svg)->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertSame(0, File::query()->count());
    }

    /**
     * The decompression bomb: a small file that declares a huge canvas. The
     * dimensions rule reads the header only, so rejecting it is cheap — which
     * is the whole point, since decoding it is what would kill the worker.
     */
    public function test_an_image_wider_than_the_ceiling_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsCustomer();

        $tooWide = config('uploads.profile_image.max_width') + 1;

        $this->upload(UploadedFile::fake()->image('wide.png', $tooWide, 8))
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');

        $this->assertSame(0, File::query()->count());
    }

    public function test_an_image_taller_than_the_ceiling_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsCustomer();

        $tooTall = config('uploads.profile_image.max_height') + 1;

        $this->upload(UploadedFile::fake()->image('tall.png', 8, $tooTall))
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');

        $this->assertSame(0, File::query()->count());
    }

    public function test_a_file_over_the_size_ceiling_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsCustomer();

        $overweight = UploadedFile::fake()
            ->image('heavy.jpg', 64, 64)
            ->size(ImageUploadRules::maxKilobytes() + 1);

        $this->upload($overweight)->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertSame(0, File::query()->count());
    }

    /**
     * A payload wearing an image name and an image Content-Type. Both are
     * chosen by the client; only the bytes are not.
     */
    public function test_a_non_image_declaring_an_image_content_type_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsCustomer();

        $payload = UploadedFile::fake()->createWithContent(
            'avatar.jpg',
            "<?php echo 'pwned'; ?>"
        );

        $this->upload($payload)->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertSame(0, File::query()->count());
    }

    /**
     * These files are written to the public disk, so the stored name must come
     * from the bytes, never from the name the client picked.
     */
    public function test_the_stored_filename_ignores_the_client_supplied_extension(): void
    {
        Storage::fake('public');
        $user = $this->actingAsCustomer();

        // Genuine JPEG bytes that the client insists on calling avatar.svg.
        // UploadedFile::fake() cannot model this - it derives the MIME type from
        // the name it was given - so the file has to be a real one. (A `.php`
        // name would not reach the model at all: Laravel's own mimes rule
        // refuses those outright, which is a floor, not the whole defence.)
        $this->upload($this->realJpegNamed('avatar.svg'))->assertOk();

        $file = $user->fresh()->profile_image;

        $this->assertSame('jpg', $file->extension);
        $this->assertStringEndsWith('.jpg', $file->path);
        $this->assertStringNotContainsString('.svg', $file->path);
    }

    /**
     * A real JPEG on disk, presented under a name of the caller's choosing.
     */
    protected function realJpegNamed(string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        $image = imagecreatetruecolor(64, 64);
        imagejpeg($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, $clientName, 'image/jpeg', null, true);
    }

    /**
     * Build a genuine WebP, since UploadedFile::fake()->image() writes a JPEG
     * for any extension it does not know and would not exercise the format.
     */
    protected function fakeWebp(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar') . '.webp';
        $image = imagecreatetruecolor(64, 64);
        imagewebp($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'avatar.webp', 'image/webp', null, true);
    }
}
