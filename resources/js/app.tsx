import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLayout from '@/layouts/public-layout';
import { xsrf } from '@/lib/http';

// M15: chuông nhận thông báo tức thời qua Reverb khi có khóa ứng dụng; không có thì chỉ thăm dò định kỳ.
if (import.meta.env.VITE_REVERB_APP_KEY) {
    configureEcho({
        broadcaster: 'reverb',
        authorizer: (channel: { name: string }) => ({
            authorize: (socketId, callback) => {
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrf(),
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then((res) =>
                        res.ok
                            ? res.json()
                            : Promise.reject(new Error(`HTTP ${res.status}`)),
                    )
                    .then((data: { auth: string }) => callback(null, data))
                    .catch((error: Error) => callback(error, null));
            },
        }),
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'EduNest';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'error':
                return null;
            case name.startsWith('public/'):
                return PublicLayout;
            case name.startsWith('auth/'):
            case name === 'account/password-required':
                return AuthLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#F59E0B',
    },
});

initializeTheme();

// P8 M28: PWA — đăng ký service worker (chỉ bản dựng, không chạy khi dev có HMR).
if (
    import.meta.env.PROD &&
    typeof navigator !== 'undefined' &&
    'serviceWorker' in navigator
) {
    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js').catch(() => undefined);
    });
}
