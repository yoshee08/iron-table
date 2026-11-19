// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Live updates (Laravel Echo + Reverb).
// The settings come from the page at run time, not from the build, so one image
// works for any host name:
//   - key:  <meta name="reverb-key"> (resources/views/partials/reverb-meta.blade.php)
//   - host, port, TLS: the same as the page. docker/Caddyfile forwards /app/* to Reverb.

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const key = document.querySelector('meta[name="reverb-key"]')?.content;

if (key) {
    const secure = window.location.protocol === 'https:';
    const port = Number(window.location.port) || (secure ? 443 : 80);

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
        enabledTransports: ['ws', 'wss'],
    });
}
