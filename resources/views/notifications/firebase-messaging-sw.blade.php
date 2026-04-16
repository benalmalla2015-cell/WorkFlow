importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

firebase.initializeApp(@json($firebaseConfig));

const messaging = firebase.messaging();

messaging.onBackgroundMessage(function (payload) {
    const data = payload?.data || {};
    const notification = payload?.notification || {};

    self.registration.showNotification(notification.title || data.title || 'تنبيه جديد', {
        body: notification.body || data.message || '',
        tag: data.tag || ('workflow-' + (data.order_id || 'notification')),
        data: {
            url: data.url || '/dashboard',
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
