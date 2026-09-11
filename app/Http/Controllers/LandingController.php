<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Models\Language;
use App\Models\LandingSection;
use App\Services\Cms\CmsPageTransformer;
use App\Services\Landing\LandingContent;
use App\Services\Landing\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public marketing site: the landing page and the app-download page.
 *
 * Both views read every string through LandingContent, which resolves the
 * per-locale maps stored by the admin panel. The active locale is decided
 * upstream by App\Http\Middleware\SetLocaleFromSession.
 */
class LandingController extends Controller
{
    /*
     * LandingContent is deliberately NOT injected into the constructor.
     *
     * Illuminate\Routing\Route::getController() memoises the controller on the
     * Route object, and routes live for the lifetime of the application — so a
     * constructor-injected service is shared by every request the process
     * serves. LandingContent memoises the whole section payload, which under a
     * persistent runtime (Octane) would serve stale content until a restart.
     * Resolving it per action, from a container binding registered as `scoped`,
     * keeps it to one instance per request.
     */

    private function landing(): LandingContent
    {
        return app(LandingContent::class);
    }

    public function index(): View
    {
        return view('landing.index', $this->viewData());
    }

    public function app(): View
    {
        // The download page is a section row like any other, so hiding it in
        // the admin panel must also close the route — otherwise the navbar CTA
        // would point at a live page the admin believes is switched off.
        abort_unless($this->landing()->enabled(LandingSection::APP_PAGE), 404);

        return view('landing.app', $this->viewData());
    }

    /**
     * Render a CMS page (Impressum, Datenschutz, AGB …) inside the public site.
     *
     * The blocks already exist in `cms_pages` and were previously reachable only
     * through the mobile API and an admin-only preview. The footer of the design
     * links to Impressum and Datenschutz, and a German commercial site is
     * legally required to carry an Impressum, so the same content is served here
     * in the landing's own skin rather than duplicated.
     */
    public function page(string $slug, CmsPageTransformer $transformer): View
    {
        $page = CmsPage::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        // The CMS falls back per-block to its own default language, which is
        // independent of the site locale — pass the site locale so a visitor
        // reading the German site gets the German Impressum.
        $payload = $transformer->transform($page, $this->landing()->locale());

        $doc = LegalDocument::build($page, $payload['blocks']);

        return view('landing.legal', [
            ...$this->viewData(),

            // Overrides viewData()'s value, which is the landing page's marketing
            // description. Every /page/* used to share it, so three legal
            // documents advertised haircuts to search engines and link previews.
            'seoDescription' => $doc->description ?: $this->landing()->text('seo.description'),

            'page' => $page,
            'doc'  => $doc,

            // The sibling documents, minus the one being read — so the AGB can
            // hand the reader the Impressum they were probably looking for.
            'siblingLegalLinks' => array_values(array_filter(
                $this->landing()->items('footer.legal_links'),
                fn (array $link): bool => ($link['url'] ?? null) !== "/page/{$page->slug}",
            )),
        ]);
    }

    /**
     * Switch the public site's language.
     *
     * Writes the choice to the session, which SetLocaleFromSession reads on
     * every later request; the same session key is what the staff dashboard and
     * the Filament panel use, so one switch is honoured everywhere.
     */
    public function switchLanguage(Request $request, string $code): RedirectResponse
    {
        $exists = Language::query()
            ->where('is_active', true)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            $request->session()->put('locale', $code);
        }

        // `back()` keeps the visitor on the section they were reading, including
        // the #anchor, and falls back to the landing page for direct hits.
        return back(fallback: route('landing'));
    }

    /**
     * Shared payload for every public view.
     *
     * The brand and SEO values are resolved here rather than in a @php block in
     * the layout: Blade renders a child view's sections before the layout body,
     * and variables declared in the layout do not reach partials pulled in from
     * those sections. Passing them as view data makes them available uniformly
     * to the layout, the sections and every partial.
     */
    private function viewData(): array
    {
        $landing = $this->landing();

        $brandName   = $landing->text('brand.name', 'LOOK UP');
        $brandSuffix = $landing->text('brand.suffix', 'FRISEUR');

        return [
            'landing'   => $landing,
            'languages' => Language::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->get(),

            'brandName'   => $brandName,
            'brandSuffix' => $brandSuffix,
            'brandLabel'  => trim($brandName . ' ' . $brandSuffix),
            'logo'        => $landing->image('brand.logo') ?? asset('image/landing/lookup-logo.png'),
            'favicon'     => $landing->image('brand.favicon') ?? asset('image/landing/lookup-logo.png'),

            'seoTitle'       => $landing->text('seo.title') ?: trim($brandName . ' ' . $brandSuffix),
            'seoDescription' => $landing->text('seo.description'),
            'seoKeywords'    => $landing->text('seo.keywords'),
            'seoImage'       => $landing->image('seo.og_image')
                ?? $landing->image('hero.image')
                ?? asset('image/landing/salon.png'),
        ];
    }
}
