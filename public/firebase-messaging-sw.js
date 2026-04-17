importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: 'AIzaSyAXvcw2kyDn83T1_pI6x7ECJR0RY3ISgy0',
    authDomain: 'dayancosys.firebaseapp.com',
    projectId: 'dayancosys',
    storageBucket: 'dayancosys.firebasestorage.app',
    messagingSenderId: '291737712580',
    appId: '1:291737712580:web:fe48dcdf63780b44de1691',
    measurementId: 'G-KVCQCNMRV8',
});

const messaging = firebase.messaging();

const normalizePayload = function (payload) {
    const data = payload?.data || {};
    const notification = payload?.notification || {};

    return {
        title: notification.title || data.title || 'تنبيه جديد',
        message: notification.body || data.message || data.body || '',
        url: data.url || data.action_url || notification.click_action || '/dashboard',
        tag: data.tag || ('workflow-' + (data.order_id || 'notification')),
        type: data.type || '',
        sound_event: data.sound_event || '',
        order_id: data.order_id || '',
    };
};

const shouldPlayBell = function (payload) {
    const searchableText = (payload.title || '') + ' ' + (payload.message || '');

    return ['adjustment_request', 'new_order'].includes(payload.sound_event)
        || ['order_change_requested', 'adjustment_requested', 'new_order'].includes(payload.type)
        || /طلب تعديل|طلب جديد/.test(searchableText);
};

const notifyClients = function (payload) {
    return clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
        windowClients.forEach(function (client) {
            client.postMessage({
                type: 'workflow-notification',
                payload: payload,
            });
        });
    });
};

messaging.onBackgroundMessage(function (payload) {
    const normalized = normalizePayload(payload);

    notifyClients({
        ...normalized,
        should_play_sound: shouldPlayBell(normalized),
        source: 'service-worker-background',
    });

    self.registration.showNotification(normalized.title, {
        body: normalized.message,
        tag: normalized.tag,
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data: {
            url: normalized.url,
            type: normalized.type,
            sound_event: normalized.sound_event,
            order_id: normalized.order_id,
            title: normalized.title,
            message: normalized.message,
        },
    });
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const targetUrl = event.notification?.data?.url || '/dashboard';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (const client of windowClients) {
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
