<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\Request;

class CmsPagePreviewController extends Controller
{
    public function show(CmsPage $page, Request $request)
    {
        $supportedLangs = config('cms.supported_languages', []);
        $defaultLang    = config('cms.default_language', 'ar');

        $lang = $request->query('lang', $defaultLang);

        if (! array_key_exists($lang, $supportedLangs)) {
            $lang = $defaultLang;
        }

        $direction = $supportedLangs[$lang]['direction'] ?? 'ltr';
        $blocks    = $page->blocks ?? [];

        return view('admin.cms-preview.show', [
            'page'           => $page,
            'blocks'         => $blocks,
            'lang'           => $lang,
            'defaultLang'    => $defaultLang,
            'direction'      => $direction,
            'supportedLangs' => $supportedLangs,
        ]);
    }
}
