// Laravel Echo + Reverb client bootstrap.
//
// Reverb speaks the Pusher WebSocket protocol, so Echo is configured with the
// `reverb` broadcaster backed by pusher-js. Setting `window.Echo` lets Livewire
// consume it for `#[On('echo-private:...')]` / `echo:` listeners (e.g. the
// opportunity Show + line-item editor availability listeners) without any
// per-component JavaScript. Private-channel subscriptions authorize against the
// app's session guard via the auto-registered `/broadcasting/auth` route.
//
// Connection values come from the build-time VITE_REVERB_* env vars (see
// .env.example); when they are absent Echo simply fails to connect, which keeps
// non-broadcasting environments (and the test build) functional.
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
