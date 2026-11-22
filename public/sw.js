/*
 * P8 M28 — service worker PWA của EduNest.
 * - Trang (điều hướng): mạng trước, mất mạng thì trả trang ngoại tuyến. Không lưu nội dung trang đã đăng nhập.
 * - Tệp dựng (/build/*): bộ nhớ đệm trước (tên tệp có băm nên an toàn).
 * - Không chạm API, webhook, video/PDF có ký, hay yêu cầu khác GET.
 * - Thông báo đẩy: hiển thị payload JSON {title, body, url}; máy chủ gửi đẩy cần khóa VAPID (chờ cấu hình).
 */
const VERSION = 'edunest-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/icon-192.png', '/favicon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(VERSION)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== VERSION)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches
                                .open(VERSION)
                                .then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }
    event.waitUntil(
        self.registration.showNotification(data.title || 'EduNest', {
            body: data.body || '',
            icon: '/icon-192.png',
            data: { url: data.url || '/dashboard' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target =
        (event.notification.data && event.notification.data.url) ||
        '/dashboard';
    event.waitUntil(self.clients.openWindow(target));
});
