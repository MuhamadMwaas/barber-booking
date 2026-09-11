<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Profile image uploads (AUTH-06)
    |--------------------------------------------------------------------------
    |
    | Every avatar upload path in the application — the customer API endpoint
    | (POST /api/profile) and the two Filament forms — validates against THESE
    | numbers, so a limit can never be tightened in one place and left loose in
    | another. The three constraints each close a different door:
    |
    |   mime_types  — narrows what Laravel's `image` rule accepts. On Laravel 12
    |                 `image` already refuses SVG, but it still lets through GIF
    |                 and BMP. GIF matters: an animated GIF is an arbitrary
    |                 number of frames inside one small file, which is the same
    |                 amplification problem as an oversized still image.
    |
    |   max_kilobytes — bounds what a single request can push onto the public
    |                 disk. It does NOT bound decoding cost, which is the trap:
    |                 compressed bytes on the wire say nothing about pixels in
    |                 memory.
    |
    |   max_width / max_height — the constraint that actually bounds decoding
    |                 cost. A 2 MB PNG can legally declare 20000x20000 pixels;
    |                 decoding it costs ~1.2 GB of RAM and takes the PHP worker
    |                 down with it (a "decompression bomb"). The `dimensions`
    |                 rule reads only the image header via getimagesize(), so
    |                 the check itself never decodes the bomb it is rejecting.
    |
    | 4000x4000 is 16 MP — comfortably above any phone camera avatar, and about
    | 64 MB decoded, which a default 128 MB memory_limit survives.
    |
    */

    'profile_image' => [

        // Extensions, matched against the MIME type Laravel guesses from the
        // file's actual bytes — not against the name the client sent.
        'mimes' => ['jpeg', 'jpg', 'png', 'webp'],

        // The same whitelist expressed as MIME types, for the browser-side
        // `accept` attribute on the Filament upload fields.
        'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],

        'max_kilobytes' => (int) env('UPLOAD_PROFILE_IMAGE_MAX_KB', 2048),

        'max_width' => (int) env('UPLOAD_PROFILE_IMAGE_MAX_WIDTH', 4000),
        'max_height' => (int) env('UPLOAD_PROFILE_IMAGE_MAX_HEIGHT', 4000),
    ],

];
