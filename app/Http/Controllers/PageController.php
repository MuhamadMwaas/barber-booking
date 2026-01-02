<?php

namespace App\Http\Controllers;

use App\Exceptions\PageNotFoundException;
use App\Exceptions\PageTranslationMissingException;
use App\Services\PageRenderService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function show(Request $request, string $pageKey, PageRenderService $service)
    {
        $lang = $request->query('lang');
        try {

            return $service->render($pageKey, $lang);


        } catch (\Throwable $e) {
            report($e);
            abort(500);
        }
    }

    public function privacy(Request $request, PageRenderService $service)
    {
        $lang = $request->query('lang');

        try {

            return $service->render('terms', $lang);
        } catch (\Throwable $e) {
            logger()->error('Error rendering privacy page: ' . $e->getMessage());
            throw $e;
        }

    }

    public function terms(Request $request, PageRenderService $service)
    {
        $lang = $request->query('lang');

        try {

            return $service->render('privacy', $lang);
        } catch (\Throwable $e) {
            report($e);
            abort(500);
        }

    }
}
