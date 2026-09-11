<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\Language;
use App\Services\Landing\LegalDocument;
use Database\Seeders\LandingPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public legal documents at /page/{slug} — Impressum, Datenschutz, AGB.
 *
 * These render from `cms_pages`, the same rows the mobile API and the admin
 * panel use, through App\Services\Landing\LegalDocument and the
 * `landing.legal` template.
 *
 * The regressions worth pinning are the ones that were live in production:
 * an untranslated `$page->name` printed as the heading (Arabic text on the
 * German site, and a second one below it in the right language), and every
 * legal page inheriting the landing page's marketing meta description.
 */
class LegalPageTest extends TestCase
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

    /**
     * A three-section document with an untranslated `name`, a divider (whose
     * `content` is an empty object, not an empty array) and a first paragraph
     * that should become the meta description.
     */
    private function makePage(string $slug = 'privacy-policy'): CmsPage
    {
        $heading = fn (string $level, string $de, string $en): array => [
            'id' => uniqid('b', true),
            'type' => 'heading',
            'is_active' => true,
            'props' => ['level' => $level, 'alignment' => 'left'],
            'translations' => ['de' => ['text' => $de], 'en' => ['text' => $en]],
        ];

        return CmsPage::create([
            'name' => 'اسم عربي غير مترجم',
            'slug' => $slug,
            'is_active' => true,
            'blocks' => [
                $heading('h1', 'Datenschutzrichtlinie', 'Privacy Policy'),
                [
                    'id' => uniqid('b', true),
                    'type' => 'paragraph',
                    'is_active' => true,
                    'props' => ['alignment' => 'left'],
                    'translations' => [
                        'de' => ['text' => 'Wir nehmen den Schutz Ihrer Daten ernst.'],
                        'en' => ['text' => 'We take the protection of your data seriously.'],
                    ],
                ],
                // Empty content — this is the shape that crashed array access.
                ['id' => uniqid('b', true), 'type' => 'divider', 'is_active' => true, 'props' => [], 'translations' => []],
                $heading('h2', 'Verantwortlicher', 'Controller'),
                $heading('h2', 'Ihre Rechte', 'Your rights'),
                $heading('h2', 'Kontakt', 'Contact'),
            ],
        ]);
    }

    // ── Rendering ────────────────────────────────────────────────────────────

    public function test_a_legal_page_renders(): void
    {
        $this->makePage();

        $this->get('/page/privacy-policy')
            ->assertOk()
            ->assertSee('Privacy Policy');
    }

    public function test_an_inactive_page_is_not_public(): void
    {
        $this->makePage()->update(['is_active' => false]);

        $this->get('/page/privacy-policy')->assertNotFound();
    }

    /**
     * A divider carries `content` as an empty stdClass once it has been through
     * json_decode, so indexing it is a fatal error rather than a null.
     */
    public function test_a_block_with_empty_content_does_not_break_the_page(): void
    {
        $this->makePage();

        $this->get('/page/privacy-policy')->assertOk();
    }

    // ── The heading regression ───────────────────────────────────────────────

    /**
     * `cms_pages.name` is one untranslated column. It used to be the <h1> and
     * the <title>, so a German visitor got an Arabic RTL heading — with the
     * correct German one printed directly underneath it.
     */
    public function test_the_heading_is_in_the_visitor_language_not_the_untranslated_name(): void
    {
        $page = $this->makePage();

        $this->get('/page/privacy-policy', ['Accept-Language' => 'de-DE,de;q=0.9'])
            ->assertOk()
            ->assertSee('Datenschutzrichtlinie')
            ->assertDontSee($page->name);
    }

    public function test_the_document_carries_exactly_one_heading_level_one(): void
    {
        $this->makePage();

        $html = $this->get('/page/privacy-policy')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'The title block must not be printed twice.');
    }

    // ── Contents and anchors ─────────────────────────────────────────────────

    public function test_every_section_gets_an_anchor_and_a_contents_entry(): void
    {
        $this->makePage();

        $html = $this->get('/page/privacy-policy')->assertOk()->getContent();

        // Three h2 sections in the fixture; the h1 is the title, not a section.
        $this->assertSame(3, substr_count($html, 'id="section-'));
        $this->assertSame(3, substr_count($html, 'href="#section-'));
    }

    /**
     * Anchors are ordinals, not slugified headings, so that a link to a clause
     * shared from the German page resolves on the Arabic one too — and because
     * Str::slug() reduces an Arabic heading to an empty string.
     */
    public function test_anchors_are_identical_across_locales(): void
    {
        $this->makePage();

        foreach (['de', 'en'] as $locale) {
            // The detected locale is cached in the session; without this the
            // second pass would silently re-test the first language.
            $this->flushSession();

            $html = $this->get('/page/privacy-policy', ['Accept-Language' => $locale])
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('id="section-1"', $html);
            $this->assertStringContainsString('id="section-3"', $html);
        }
    }

    // ── Direction ────────────────────────────────────────────────────────────

    /**
     * CmsPageTransformer resolves `alignment: auto` into a PHYSICAL side per
     * language — "right" for Arabic, "left" for German. The template must map
     * that to a physical class, because a logical one (text-start/text-end)
     * flips it a SECOND time: RTL's text-end is the left edge, which pinned
     * every Arabic paragraph to the wrong margin inside a correct dir="rtl"
     * document.
     */
    public function test_arabic_text_is_aligned_to_the_right_not_flipped_twice(): void
    {
        CmsPage::create([
            'name' => 'Alignment',
            'slug' => 'privacy-policy',
            'is_active' => true,
            'blocks' => [[
                'id' => uniqid('b', true),
                'type' => 'paragraph',
                'is_active' => true,
                'props' => ['alignment' => 'auto'],
                'translations' => [
                    'ar' => ['text' => 'نص عربي'],
                    'de' => ['text' => 'Deutscher Text'],
                ],
            ]],
        ]);

        $arabic = $this->get('/page/privacy-policy', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->getContent();

        $this->assertStringContainsString('text-right', $arabic);
        $this->assertStringNotContainsString('text-end', $arabic);
        $this->assertStringNotContainsString('text-start', $arabic);

        // SetLocaleFromSession caches the detected locale in the session, so the
        // browser header on a second request is ignored until it is cleared.
        $this->flushSession();

        $german = $this->get('/page/privacy-policy', ['Accept-Language' => 'de'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('text-left', $german);
    }

    /**
     * The documents number their own clauses, in their own numeral system
     * ("1." in German, "١." in Arabic). A generated counter on top of that
     * printed every entry twice, in two scripts.
     */
    public function test_the_contents_list_does_not_add_its_own_numbering(): void
    {
        $this->makePage();

        $html = $this->get('/page/privacy-policy')->assertOk()->getContent();

        $this->assertSame(0, preg_match('/>\s*0\d\s*</', $html));
        $this->assertSame(3, substr_count($html, 'href="#section-'));
    }

    // ── SEO ──────────────────────────────────────────────────────────────────

    /** Every /page/* used to advertise haircuts, inheriting the landing meta. */
    public function test_the_meta_description_comes_from_the_page_not_the_landing_copy(): void
    {
        $this->makePage();

        $this->get('/page/privacy-policy', ['Accept-Language' => 'de'])
            ->assertOk()
            ->assertSee('Wir nehmen den Schutz Ihrer Daten ernst.', false);
    }

    // ── Cross-links ──────────────────────────────────────────────────────────

    public function test_the_page_links_to_its_siblings_but_not_to_itself(): void
    {
        $this->makePage('impressum');

        $html = $this->get('/page/impressum')->assertOk()->getContent();

        // The seeded footer links all three; the in-article nav drops the
        // current one, so the page must not offer a link back to itself twice.
        $this->assertSame(1, substr_count($html, 'href="/page/impressum"'));
        $this->assertGreaterThan(1, substr_count($html, 'href="/page/terms-conditions"'));
    }

    public function test_the_footer_legal_links_are_not_printed_twice(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['impressum', 'privacy-policy', 'terms-conditions'] as $slug) {
            $this->assertSame(
                1,
                substr_count($html, 'href="/page/' . $slug . '"'),
                "The {$slug} link is rendered more than once in the footer.",
            );
        }
    }

    // ── The document builder ─────────────────────────────────────────────────

    public function test_the_builder_falls_back_to_the_page_name_without_a_title_block(): void
    {
        $page = CmsPage::create([
            'name' => 'Untitled document',
            'slug' => 'no-title-block',
            'is_active' => true,
            'blocks' => [[
                'id' => uniqid('b', true),
                'type' => 'paragraph',
                'is_active' => true,
                'props' => [],
                'translations' => ['en' => ['text' => 'Body only.']],
            ]],
        ]);

        $doc = LegalDocument::build($page, [
            ['type' => 'paragraph', 'props' => [], 'content' => ['text' => 'Body only.']],
        ]);

        $this->assertSame('Untitled document', $doc->title);
        $this->assertSame($page->name, $doc->title);
    }

    /** A contents list on a two-heading document is noise, not navigation. */
    public function test_a_short_document_gets_no_contents_list(): void
    {
        $doc = LegalDocument::build(
            new CmsPage(['name' => 'Short']),
            [
                ['type' => 'heading', 'props' => ['level' => 'h1'], 'content' => ['text' => 'Short']],
                ['type' => 'heading', 'props' => ['level' => 'h2'], 'content' => ['text' => 'Only section']],
            ],
        );

        $this->assertFalse($doc->hasContents());
    }
}
