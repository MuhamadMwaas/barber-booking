<?php

namespace App\Services\Landing;

use App\Models\CmsPage;
use Illuminate\Support\Str;

/**
 * Turns a transformed CMS payload into a *document* the legal template can lay
 * out — a title, a table of contents, a meta description and anchored blocks.
 *
 * ── Why this exists instead of doing it in the Blade ─────────────────────────
 *
 * The public site's rule is that templates never touch Eloquent or raw JSON;
 * they ask for finished values (see LandingContent). Building a table of
 * contents means walking every block, tracking ordinals and consuming the title
 * block so it is not printed twice — loop state that has no business living in a
 * view.
 *
 * ── What it fixes ────────────────────────────────────────────────────────────
 *
 * `cms_pages.name` is a single untranslated column, and the seeded values are
 * Arabic. The old template printed it as the `<h1>` and the `<title>`, so a
 * German visitor to /page/privacy-policy got an Arabic RTL heading on an
 * `lang="de" dir="ltr"` page — and then the CORRECT German title immediately
 * below it, because every page's first block is a level-h1 heading that IS
 * fully translated. This class takes the title from that block and drops it from
 * the body, so each page renders exactly one heading, in the visitor's language.
 * `$page->name` survives only as the last-resort fallback and as the admin's own
 * label in the panel.
 */
class LegalDocument
{
    /** Meta descriptions are truncated by search engines around this length. */
    private const DESCRIPTION_LENGTH = 155;

    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        /** @var list<array{id: string, text: string}> */
        public readonly array $sections,
        /** @var list<array<string, mixed>> */
        public readonly array $blocks,
        public readonly ?string $updatedAt,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $blocks  already transformed by CmsPageTransformer
     */
    public static function build(CmsPage $page, array $blocks): self
    {
        $title       = null;
        $description = null;
        $sections    = [];
        $body        = [];
        $ordinal     = 0;

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;

            // Cast, don't trust: a block with no content of its own (a divider)
            // arrives with `content` as an EMPTY stdClass, not an empty array,
            // because that is what json_encode/decode round-trips `{}` into.
            // Indexing it directly is a fatal "Cannot use object of type
            // stdClass as array" — and only on pages that contain a divider,
            // which is every one of these documents.
            $content = (array) ($block['content'] ?? []);
            $props   = (array) ($block['props'] ?? []);

            $text  = trim((string) ($content['text'] ?? ''));
            $level = $props['level'] ?? null;

            // The first level-h1 heading IS the document title. Consume it rather
            // than printing it: the template renders the title in the page header,
            // and a document with two <h1>s is a document with none.
            if ($type === 'heading' && $level === 'h1' && $title === null && $text !== '') {
                $title = $text;

                continue;
            }

            if ($type === 'heading' && $text !== '') {
                $ordinal++;

                // Ordinal anchors, not slugified text. A slug would differ per
                // locale (and Str::slug() empties Arabic headings entirely), so
                // a "§ 7" link shared from the German page would 404 its own
                // fragment on the Arabic one. `section-7` is the same clause in
                // every language, which is the whole point of citing a clause.
                $block['anchor'] = 'section-' . $ordinal;

                $sections[] = [
                    'id'   => $block['anchor'],
                    'text' => $text,
                ];
            }

            // First real paragraph doubles as the meta description. Without this
            // every /page/* shared the landing page's marketing description.
            if ($description === null && $type === 'paragraph' && $text !== '') {
                $description = Str::limit(preg_replace('/\s+/u', ' ', $text), self::DESCRIPTION_LENGTH);
            }

            $body[] = $block;
        }

        return new self(
            title: $title ?? $page->name,
            description: $description,
            sections: $sections,
            blocks: $body,
            updatedAt: $page->updated_at?->translatedFormat('j F Y'),
        );
    }

    /**
     * A contents list is worth showing for a long document and is just noise on
     * a short one. The Impressum has 7 sections, the privacy policy 17.
     */
    public function hasContents(): bool
    {
        return count($this->sections) >= 3;
    }
}
