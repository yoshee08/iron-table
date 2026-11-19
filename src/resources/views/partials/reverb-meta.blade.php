{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{--
    Live-update settings for the browser, given at run time (not at build time).
    The Reverb app key is public by design (Pusher protocol). The secret is never sent.
    The browser connects to the same host and port as the page; docker/Caddyfile
    forwards /app/* to the reverb service. Read by resources/js/echo.js.
--}}
<meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
