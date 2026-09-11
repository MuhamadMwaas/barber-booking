<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API token lifetimes and rotation (AUTH-04)
    |--------------------------------------------------------------------------
    |
    | The mobile API hands out a pair: a short-lived ACCESS token that is sent
    | with every request, and a long-lived REFRESH token that is sent to exactly
    | one endpoint (POST /api/auth/refresh) and is used to mint new access
    | tokens.
    |
    | The short access lifetime is the whole point of the split — it bounds how
    | long a leaked access token is useful. But that bound is only real if the
    | refresh token cannot be leaked and replayed indefinitely, which is what
    | rotation and reuse detection below exist to guarantee.
    |
    */

    /*
    | How long a minted access token stays usable. Short on purpose: this is the
    | credential that travels on every request, so it is the one most likely to
    | end up in a proxy log, a crash report or a screenshot.
    */
    'access_ttl_minutes' => (int) env('AUTH_ACCESS_TTL_MINUTES', 15),

    /*
    | How long a refresh token stays usable. Long on purpose: it is what keeps a
    | customer from being asked to log in every fifteen minutes. With rotation
    | on, this is a ceiling on the FAMILY, not on any single token — each
    | individual token dies the first time it is used.
    */
    'refresh_ttl_days' => (int) env('AUTH_REFRESH_TTL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Rotation
    |--------------------------------------------------------------------------
    |
    | With rotation ON, every call to /api/auth/refresh revokes the token it was
    | given and returns a brand new one alongside the access token. A refresh
    | token therefore becomes a ONE-TIME credential.
    |
    | That single property is what makes theft detectable. Once a token can only
    | be spent once, a second appearance of an already-spent token is proof that
    | a copy of it exists somewhere — see reuse_grace_seconds below.
    |
    | !! CLIENT CONTRACT !!
    | A client MUST store the `refresh_token` returned by /api/auth/refresh and
    | send that one next time. A client that keeps replaying its original token
    | will be treated as a leak and logged out of every device. Turn this off
    | ONLY as a temporary measure while old app builds are still in the field.
    */
    'rotate_refresh_tokens' => (bool) env('AUTH_ROTATE_REFRESH_TOKENS', true),

    /*
    |--------------------------------------------------------------------------
    | Reuse detection grace window (seconds)
    |--------------------------------------------------------------------------
    |
    | A rotated token that comes back is normally proof of a leak. There is one
    | innocent way it happens: the client sent a refresh, the server rotated and
    | replied, and the reply was lost on the way back — a dropped connection, a
    | backgrounded app, a flaky mobile handover. The client never saw its new
    | token and retries with the old one, entirely honestly.
    |
    | Inside this window such a retry is answered with a plain 401 and nothing
    | else happens: the client re-authenticates, and no other device is touched.
    | Outside it, an attacker has had time to act, so the alarm fires and every
    | session for the account is destroyed.
    |
    | Keep this small. It is the length of time a genuinely stolen token is
    | allowed to be replayed unnoticed.
    */
    'reuse_grace_seconds' => (int) env('AUTH_REFRESH_REUSE_GRACE_SECONDS', 60),

];
