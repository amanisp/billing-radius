import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Initialize Echo for broadcasting
const broadcastDriver = import.meta.env.VITE_BROADCAST_DRIVER || 'log';

if (broadcastDriver === 'pusher') {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'ap1',
        wsHost: import.meta.env.VITE_PUSHER_HOST || `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
        wsPort: Number(import.meta.env.VITE_PUSHER_PORT) || 80,
        wssPort: Number(import.meta.env.VITE_PUSHER_PORT) || 443,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else if (broadcastDriver === 'log') {
    window.Echo = new Echo({
        broadcaster: 'log',
        name: import.meta.env.VITE_APP_NAME
    });
    console.log('Broadcasting set to log driver');
}
