<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds `public` disk URLs on the host the admin panel was opened from.
 *
 * The disk's URL is `APP_URL/storage` (https://lookupfriseur.com/storage), but
 * the panel is also reachable on the dashboard subdomain
 * (https://dashboard.lookupfriseur.com/admin). Filament's FileUpload does not
 * show an existing image with a plain <img>: FilePond downloads it with
 * `fetch(url)` to learn its size (vendor/filament/forms/resources/js/components/
 * file-upload.js, `server.load`). From the subdomain that fetch is cross-origin,
 * the storage files carry no CORS header, so the browser rejects it ("Failed to
 * fetch") and the field hangs on "Waiting for size" — every edit form with an
 * image, on the server only (locally APP_URL and the browser share one host).
 *
 * Both hosts serve the same app and the same /storage, so pointing the disk at
 * the current host makes the fetch same-origin without opening CORS.
 *
 * Registered as PERSISTENT panel middleware: the file URLs are produced in the
 * Livewire request FilePond makes after the page loads, not in the page request.
 * Scoped to the panel on purpose — the API keeps answering with APP_URL links.
 * Nothing stores these URLs (File keeps only `path`), so no host leaks into data.
 */
class ServePanelStorageFromCurrentHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $url = $request->getSchemeAndHttpHost().'/storage';

        if (config('filesystems.disks.public.url') !== $url) {
            config(['filesystems.disks.public.url' => $url]);

            // The resolved disk caches its config; drop it so the next
            // Storage::disk('public') is built with the new URL.
            Storage::forgetDisk('public');
        }

        return $next($request);
    }
}
