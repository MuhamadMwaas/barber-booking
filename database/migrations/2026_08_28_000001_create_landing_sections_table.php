<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landing page content store.
 *
 * ONE row per section of the public landing page (hero, services, gallery …),
 * addressed by a stable `key`. The whole editable payload of a section lives in
 * the `content` JSON column, and every user-facing string inside it is stored as
 * a per-locale map — e.g. ["de" => "Haarschnitt", "en" => "Haircut", …] — so a
 * new language only needs a row in `languages`, never a migration here.
 *
 * Why a key/JSON table rather than a column per field: the landing design has
 * ~120 editable strings across 11 sections plus three repeatable collections
 * (service cards, gallery images, feature boxes). Modelling that relationally
 * would mean a dozen tables and joins for a page that is read as one whole unit
 * and cached; the JSON payload is written by exactly one Filament screen and
 * read by exactly one controller, so there is no query surface to lose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_sections', function (Blueprint $table) {
            $table->id();

            // Stable identifier used by both the Filament editor and the Blade
            // views (see App\Models\LandingSection::KEYS). Never renamed.
            $table->string('key')->unique();

            // Section-level visibility switch — an admin can hide the gallery
            // or the newsletter without deleting its content.
            $table->boolean('is_active')->default(true);

            // Display order of the *body* sections on the page. Structural rows
            // (brand, seo, navbar, footer, app_page) ignore it.
            $table->unsignedInteger('sort_order')->default(0);

            $table->json('content')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_sections');
    }
};
