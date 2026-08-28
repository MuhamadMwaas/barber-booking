<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageLandingPage;
use App\Models\LandingSection;
use App\Models\Language;
use App\Models\User;
use Database\Seeders\LandingPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the public marketing site and the admin screen that feeds it.
 *
 * The behaviours worth locking down are the ones a future change could break
 * silently: locale resolution, the section on/off switch reaching both the page
 * and its navigation, and the admin save round-trip.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::insert([
            ['name' => 'English', 'native_name' => 'English', 'code' => 'en', 'order' => 1, 'is_active' => true, 'is_default' => true],
            ['name' => 'German',  'native_name' => 'Deutsch', 'code' => 'de', 'order' => 2, 'is_active' => true, 'is_default' => false],
            ['name' => 'Arabic',  'native_name' => 'العربية', 'code' => 'ar', 'order' => 3, 'is_active' => true, 'is_default' => false],
        ]);

        $this->seed(LandingPageSeeder::class);
    }

    /* ==========================================================
     | Rendering
     |========================================================== */

    public function test_the_landing_page_renders_every_enabled_section(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="hero"', false)
            ->assertSee('id="services"', false)
            ->assertSee('id="features"', false)
            ->assertSee('id="gallery"', false)
            ->assertSee('id="cta"', false)
            ->assertSee('id="info"', false);
    }

    public function test_a_disabled_section_disappears_from_the_page_and_the_navigation(): void
    {
        LandingSection::query()
            ->where('key', LandingSection::GALLERY)
            ->update(['is_active' => false]);

        LandingSection::flushCache();

        $response = $this->get('/')->assertOk();

        $response->assertDontSee('id="gallery"', false);

        // The navbar anchor pointing at it must go too, otherwise the link
        // scrolls nowhere.
        $response->assertDontSee('href="#gallery"', false);
    }

    /* ==========================================================
     | Language
     |========================================================== */

    public function test_a_german_browser_gets_the_german_page(): void
    {
        $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8'])
            ->get('/')
            ->assertOk()
            ->assertSee('lang="de"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Für Ihren perfekten Look', false);
    }

    public function test_an_arabic_browser_gets_a_right_to_left_page(): void
    {
        $this->withHeaders(['Accept-Language' => 'ar-SY,ar;q=0.9'])
            ->get('/')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('من أجل إطلالتك المثالية', false);
    }

    public function test_an_unsupported_browser_language_falls_back_to_the_default(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->get('/')
            ->assertOk()
            ->assertSee('lang="en"', false);
    }

    public function test_the_language_switcher_persists_the_choice(): void
    {
        $this->get('/language/de')
            ->assertRedirect();

        $this->assertSame('de', session('locale'));

        // An explicit choice must outrank the browser header on later requests.
        $this->withHeaders(['Accept-Language' => 'ar-SY,ar;q=0.9'])
            ->get('/')
            ->assertSee('lang="de"', false);
    }

    public function test_an_unknown_language_code_is_ignored(): void
    {
        $this->get('/language/de');
        $this->get('/language/zz')->assertRedirect();

        $this->assertSame('de', session('locale'));
    }

    /* ==========================================================
     | App page
     |========================================================== */

    public function test_the_app_page_renders(): void
    {
        $this->withHeaders(['Accept-Language' => 'de'])
            ->get('/app')
            ->assertOk()
            ->assertSee('Termin buchen in der App', false);
    }

    public function test_the_app_page_is_not_reachable_while_it_is_switched_off(): void
    {
        LandingSection::query()
            ->where('key', LandingSection::APP_PAGE)
            ->update(['is_active' => false]);

        LandingSection::flushCache();

        $this->get('/app')->assertNotFound();
    }

    public function test_navigation_anchors_point_back_at_the_landing_page_from_other_pages(): void
    {
        // On "/" an in-page anchor is enough...
        $this->get('/')
            ->assertOk()
            ->assertSee('href="#services"', false);

        // ...but the same navbar on /app must send the visitor back to the
        // landing page, otherwise the link scrolls nowhere.
        $this->get('/app')
            ->assertOk()
            ->assertDontSee('href="#services"', false)
            ->assertSee('href="' . route('landing') . '#services"', false);
    }

    /* ==========================================================
     | Translation fallback
     |========================================================== */

    public function test_a_missing_translation_falls_back_instead_of_rendering_empty(): void
    {
        $section = LandingSection::query()->where('key', LandingSection::CTA)->firstOrFail();

        $content = $section->content;
        $content['title']['ar'] = '';
        $section->update(['content' => $content]);

        LandingSection::flushCache();

        // Arabic is blank, so the visitor sees the fallback rather than a gap.
        $this->withHeaders(['Accept-Language' => 'ar'])
            ->get('/')
            ->assertOk()
            ->assertSee('Ready for your new look?', false);
    }

    /* ==========================================================
     | Admin screen
     |========================================================== */

    public function test_an_admin_can_save_the_landing_page(): void
    {
        Livewire::actingAs($this->adminUser())
            ->test(ManageLandingPage::class)
            ->set('data.hero.content.title_gold.de', 'GEÄNDERT')
            ->call('save')
            ->assertHasNoErrors();

        LandingSection::flushCache();

        $hero = LandingSection::query()->where('key', LandingSection::HERO)->firstOrFail();

        $this->assertSame('GEÄNDERT', $hero->content['title_gold']['de']);

        $this->germanVisitor()
            ->get('/')
            ->assertSee('GEÄNDERT', false);
    }

    public function test_saving_from_the_admin_screen_busts_the_public_cache(): void
    {
        // Warm the cache through a real page view first.
        $this->germanVisitor()->get('/')->assertOk();

        Livewire::actingAs($this->adminUser())
            ->test(ManageLandingPage::class)
            ->set('data.cta.content.title.de', 'Neuer Titel')
            ->call('save');

        // No manual flush here: the page must already show the new copy.
        $this->germanVisitor()
            ->get('/')
            ->assertSee('Neuer Titel', false);
    }

    /**
     * Regression: FileUpload keeps its state as an array while the payload
     * stores a plain path string. Before mount() switched to form()->fill(),
     * saving the page wiped every image field it did not touch.
     */
    public function test_saving_does_not_wipe_images_it_did_not_touch(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('landing/hero/photo.jpg', 'x');

        $hero = LandingSection::query()->where('key', LandingSection::HERO)->firstOrFail();
        $hero->update(['content' => [...$hero->content, 'image' => 'landing/hero/photo.jpg']]);
        LandingSection::flushCache();

        Livewire::actingAs($this->adminUser())
            ->test(ManageLandingPage::class)
            ->set('data.hero.content.eyebrow.de', 'Etwas anderes')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            'landing/hero/photo.jpg',
            LandingSection::query()->where('key', LandingSection::HERO)->firstOrFail()->content['image'],
        );
    }

    public function test_the_editor_creates_any_section_row_that_is_missing(): void
    {
        LandingSection::query()->delete();
        LandingSection::flushCache();

        Livewire::actingAs($this->adminUser())->test(ManageLandingPage::class);

        $this->assertSame(
            count(LandingSection::KEYS),
            LandingSection::query()->count(),
        );
    }

    /**
     * A page request that is guaranteed to render in German.
     *
     * The admin stays authenticated after a Livewire test, and
     * SetLocaleFromSession gives a signed-in user's own `locale` precedence over
     * Accept-Language — which is the intended behaviour, so these assertions
     * pick the language the way the switcher does instead.
     */
    private function germanVisitor(): static
    {
        $this->get('/language/de');

        return $this;
    }

    private function adminUser(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
