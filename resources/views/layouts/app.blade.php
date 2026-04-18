<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $pageTitle = $pageTitle !== ''
            ? str_replace(' | WorkFlow', '', $pageTitle) . ' | نظام إدارة سير العمل | DAYANCO TRADING CO. LIMITED'
            : 'نظام إدارة سير العمل | DAYANCO TRADING CO. LIMITED';
    @endphp
    <title>{{ $pageTitle }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; color: #1f2937; text-align: right; }
        .portal-shell { min-height: 100vh; display: flex; }
        .portal-sidebar { width: 280px; background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 24px 18px; position: sticky; top: 0; height: 100vh; }
        .portal-sidebar a { color: rgba(255,255,255,.82); text-decoration: none; display: block; padding: 12px 14px; border-radius: 12px; margin-bottom: 8px; font-weight: 500; }
        .portal-sidebar a.active, .portal-sidebar a:hover { background: rgba(255,255,255,.1); color: #fff; }
        .portal-brand { font-size: 1.4rem; font-weight: 700; margin-bottom: 4px; }
        .portal-tagline { color: rgba(255,255,255,.62); font-size: .92rem; margin-bottom: 28px; }
        .portal-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .portal-header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 18px 28px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .portal-content { padding: 28px; }
        .page-card { border: 0; border-radius: 18px; box-shadow: 0 14px 42px rgba(15, 23, 42, .08); }
        .stat-card { border: 0; border-radius: 18px; box-shadow: 0 14px 38px rgba(15, 23, 42, .06); }
        .section-title { font-weight: 700; margin-bottom: 1rem; }
        .table thead th { background: #f8fafc; color: #334155; font-size: .88rem; white-space: nowrap; }
        .badge-status { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 6px 12px; font-size: .78rem; font-weight: 700; }
        .status-draft { background: #eef3fb; color: #113f87; }
        .status-sent_to_factory { background: #f8ecd0; color: #7a5600; }
        .status-factory_pricing { background: #f5ecd2; color: #8a5b00; }
        .status-manager_review { background: #e7eef9; color: #113f87; }
        .status-pending_approval { background: #f8ecd0; color: #7a5600; }
        .status-approved { background: #dbe8fb; color: #113f87; }
        .status-customer_approved { background: #e7eef9; color: #0f2f6f; }
        .status-payment_confirmed { background: #eef3fb; color: #113f87; }
        .status-completed { background: #f5ecd2; color: #7a5600; }
        .form-card { border: 0; border-radius: 18px; box-shadow: 0 12px 34px rgba(15, 23, 42, .06); }
        .attachment-list a { text-decoration: none; }
        .chart-card canvas { max-height: 320px; }
        .portal-footer { padding: 0 28px 24px; color: #64748b; font-size: .9rem; text-align: center; }
        .notification-bell { position: relative; width: 42px; height: 42px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; }
        .notification-dot { position: absolute; top: 8px; left: 9px; width: 10px; height: 10px; border-radius: 999px; background: #dc2626; border: 2px solid #fff; }
        .notification-menu { width: min(420px, 92vw); padding: 0; border: 0; border-radius: 18px; box-shadow: 0 24px 60px rgba(15, 23, 42, .18); overflow: hidden; }
        .notification-menu .dropdown-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 18px; background: #f8fafc; }
        .notification-item { padding: 14px 18px; border-bottom: 1px solid #eef2f7; }
        .notification-item.unread { background: #eff6ff; }
        .notification-item:last-child { border-bottom: 0; }
        .toast-stack { position: fixed; top: 18px; left: 18px; z-index: 1085; display: flex; flex-direction: column; gap: 10px; }
        @media (max-width: 991.98px) {
            .portal-shell { display: block; }
            .portal-sidebar { width: 100%; height: auto; position: relative; }
            .portal-header, .portal-content { padding: 18px; }
            .portal-footer { padding: 0 18px 18px; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @php
        $user = auth()->user();
        $links = [];
        $recentNotifications = $user?->notifications()->latest()->limit(5)->get() ?? collect();
        $unreadNotificationsCount = $user?->unreadNotifications()->count() ?? 0;
        $firebaseConfig = config('firebase.web');
        $firebaseVapidKey = config('firebase.vapid_public_key');
        $firebaseEnabled = filled($firebaseVapidKey)
            && filled($firebaseConfig['apiKey'] ?? null)
            && filled($firebaseConfig['projectId'] ?? null)
            && filled($firebaseConfig['messagingSenderId'] ?? null)
            && filled($firebaseConfig['appId'] ?? null);

        if ($user?->isAdmin()) {
            $links = [
                ['label' => 'لوحة الاعتماد', 'route' => 'admin.dashboard'],
                ['label' => 'إدارة المستخدمين', 'route' => 'admin.users.index'],
                ['label' => 'الإعدادات', 'route' => 'admin.settings.index'],
                ['label' => 'السجلات', 'route' => 'admin.audit-logs.index'],
            ];
        } elseif ($user?->isFactory()) {
            $links = [
                ['label' => 'طلبات المصنع', 'route' => 'factory.orders.index'],
            ];
        } elseif ($user) {
            $links = [
                ['label' => 'طلبات المبيعات', 'route' => 'sales.orders.index'],
                ['label' => 'طلب جديد', 'route' => 'sales.orders.create'],
            ];
        }
    @endphp

    <div class="portal-shell">
        <aside class="portal-sidebar">
            <div class="portal-brand">DAYANCO TRADING CO. LIMITED</div>
            <div class="portal-tagline">نظام إدارة سير العمل المؤسسي</div>

            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}" class="{{ request()->routeIs(str_replace('.index', '.*', $link['route'])) || request()->routeIs($link['route']) ? 'active' : '' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </aside>

        <main class="portal-main">
            <header class="portal-header">
                <div>
                    <div class="fw-bold">{{ auth()->user()->name }}</div>
                    <div class="text-muted small text-uppercase">{{ auth()->user()->role }}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary notification-bell" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2Zm.104-14.804A1 1 0 0 0 7 2v.278a4.998 4.998 0 0 0-3 4.584V9.5c0 .628-.134 1.197-.352 1.692-.214.486-.527.897-.916 1.216A.5.5 0 0 0 3 13h10a.5.5 0 0 0 .268-.592 3.64 3.64 0 0 1-.916-1.216A4.49 4.49 0 0 1 12 9.5V6.862a4.998 4.998 0 0 0-3-4.584V2a1 1 0 0 0-.896-.804Z"/>
                            </svg>
                            @if ($unreadNotificationsCount > 0)
                                <span class="notification-dot" id="notification-dot"></span>
                            @else
                                <span class="notification-dot d-none" id="notification-dot"></span>
                            @endif
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-menu">
                            <div class="dropdown-header">
                                <div>
                                    <div class="fw-semibold">الإشعارات</div>
                                    <div class="small text-muted"><span id="notification-unread-count">{{ $unreadNotificationsCount }}</span> غير مقروء</div>
                                </div>
                                @if ($unreadNotificationsCount > 0)
                                    <form method="POST" action="{{ route('notifications.read-all') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-link text-decoration-none">تعليم الكل كمقروء</button>
                                    </form>
                                @endif
                            </div>
                            <div id="notification-feed">
                                @forelse ($recentNotifications as $notification)
                                    @php
                                        $data = $notification->data;
                                    @endphp
                                    <div class="notification-item {{ $notification->read_at ? '' : 'unread' }}">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="fw-semibold">{{ $data['title'] ?? 'تنبيه جديد' }}</div>
                                                <div class="small text-muted">{{ $data['message'] ?? '' }}</div>
                                                @if (!empty($data['reason']))
                                                    <div class="small text-danger mt-1">سبب القرار: {{ $data['reason'] }}</div>
                                                @endif
                                                <div class="small text-muted mt-2">{{ optional($notification->created_at)->diffForHumans() }}</div>
                                            </div>
                                            <div class="d-flex flex-column gap-2">
                                                <a href="{{ route('notifications.open', $notification->id) }}" class="btn btn-sm btn-outline-primary">فتح</a>
                                                @if (!$notification->read_at)
                                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">مقروء</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="notification-item">
                                        <div class="text-muted small">لا توجد إشعارات حالياً.</div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">الرئيسية</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-dark btn-sm">تسجيل الخروج</button>
                    </form>
                </div>
            </header>

            <section class="portal-content">
                @if (session('success'))
                    <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger border-0 shadow-sm">
                        <div class="fw-semibold mb-2">تعذر إتمام العملية:</div>
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </section>

            <footer class="portal-footer">نظام إدارة سير العمل | DAYANCO TRADING CO. LIMITED</footer>
        </main>
    </div>

    <div class="toast-stack" id="notification-toast-stack"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @if ($user && $firebaseEnabled)
        <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js"></script>
        <script>
            (() => {
                const firebaseConfig = @json($firebaseConfig);
                const vapidKey = @json($firebaseVapidKey);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const notificationFeed = document.getElementById('notification-feed');
                const notificationDot = document.getElementById('notification-dot');
                const unreadCounter = document.getElementById('notification-unread-count');
                const toastStack = document.getElementById('notification-toast-stack');
                const notificationBellUrl = '/bell.mp3';
                let latestNotificationId = null;
                let lastNotificationFingerprint = null;
                let lastNotificationFingerprintAt = 0;

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                const normalizeIncomingNotification = (payload = {}) => {
                    const data = payload.data || payload;
                    const notification = payload.notification || {};
                    const title = notification.title || data.title || 'تنبيه جديد';
                    const message = notification.body || data.message || data.body || '';
                    const type = data.type || payload.type || '';
                    const soundEvent = data.sound_event || payload.sound_event || '';
                    const searchableText = `${title} ${message}`;

                    return {
                        title,
                        message,
                        url: data.url || data.action_url || payload.url || @json(route('dashboard')),
                        type,
                        soundEvent,
                        tag: data.tag || payload.tag || '',
                        orderId: data.order_id || payload.order_id || '',
                        shouldPlaySound: Boolean(payload.should_play_sound)
                            || ['adjustment_request', 'new_order', 'sales_update'].includes(soundEvent)
                            || ['order_change_requested', 'adjustment_requested', 'new_order', 'sales_update'].includes(type)
                            || /طلب تعديل|طلب جديد|تحديث من المبيعات|طلب تعديل من المبيعات/.test(searchableText),
                    };
                };

                const isDuplicateNotification = (notification) => {
                    const fingerprint = [
                        notification.title,
                        notification.message,
                        notification.tag,
                        notification.orderId,
                        notification.type,
                        notification.soundEvent,
                    ].join('|');

                    const now = Date.now();
                    if (fingerprint && fingerprint === lastNotificationFingerprint && (now - lastNotificationFingerprintAt) < 4000) {
                        return true;
                    }

                    lastNotificationFingerprint = fingerprint;
                    lastNotificationFingerprintAt = now;
                    return false;
                };

                const playFallbackNotificationSound = () => {
                    try {
                        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                        if (!AudioContextClass) {
                            return;
                        }

                        const context = new AudioContextClass();
                        const now = context.currentTime;
                        [0, 0.18].forEach((offset, index) => {
                            const oscillator = context.createOscillator();
                            const gain = context.createGain();
                            oscillator.type = 'sine';
                            oscillator.frequency.value = index === 0 ? 880 : 1046;
                            gain.gain.setValueAtTime(0.0001, now + offset);
                            gain.gain.exponentialRampToValueAtTime(0.12, now + offset + 0.02);
                            gain.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.24);
                            oscillator.connect(gain);
                            gain.connect(context.destination);
                            oscillator.start(now + offset);
                            oscillator.stop(now + offset + 0.26);
                        });

                        setTimeout(() => context.close().catch(() => {}), 800);
                    } catch (error) {
                    }
                };

                const playNotificationSound = async () => {
                    try {
                        const audio = new Audio(notificationBellUrl);
                        audio.preload = 'auto';
                        await audio.play();
                        return;
                    } catch (error) {
                    }

                    playFallbackNotificationSound();
                };

                const showToast = (title, message) => {
                    if (!toastStack) {
                        return;
                    }

                    const wrapper = document.createElement('div');
                    wrapper.className = 'toast show border-0 shadow';
                    wrapper.setAttribute('role', 'alert');
                    wrapper.innerHTML = `
                        <div class="toast-header">
                            <strong class="me-auto">${escapeHtml(title || 'تنبيه جديد')}</strong>
                            <button type="button" class="btn-close ms-2 mb-1" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body">${escapeHtml(message || '')}</div>
                    `;
                    toastStack.prepend(wrapper);
                    setTimeout(() => wrapper.remove(), 7000);
                };

                const updateUnreadState = (count) => {
                    if (unreadCounter) {
                        unreadCounter.textContent = count;
                    }
                    if (notificationDot) {
                        notificationDot.classList.toggle('d-none', !(count > 0));
                    }
                };

                const processIncomingNotification = (payload, options = {}) => {
                    const { refresh = true } = options;
                    const notification = normalizeIncomingNotification(payload);

                    if (isDuplicateNotification(notification)) {
                        return;
                    }

                    if (notification.shouldPlaySound) {
                        playNotificationSound();
                    }

                    if (notification.title || notification.message) {
                        showToast(notification.title, notification.message);
                    }

                    if (refresh) {
                        refreshNotificationFeed();
                    }
                };

                const renderNotifications = (notifications) => {
                    if (!notificationFeed) {
                        return;
                    }

                    if (!notifications.length) {
                        notificationFeed.innerHTML = '<div class="notification-item"><div class="text-muted small">لا توجد إشعارات حالياً.</div></div>';
                        return;
                    }

                    notificationFeed.innerHTML = notifications.map((notification) => {
                        const reasonHtml = notification.reason
                            ? `<div class="small text-danger mt-1">سبب القرار: ${escapeHtml(notification.reason)}</div>`
                            : '';
                        const markReadHtml = notification.is_read
                            ? ''
                            : `<form method="POST" action="/notifications/${notification.id}/read">
                                    <input type="hidden" name="_token" value="${escapeHtml(csrfToken || '')}">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">مقروء</button>
                               </form>`;

                        return `
                            <div class="notification-item ${notification.is_read ? '' : 'unread'}">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold">${escapeHtml(notification.title)}</div>
                                        <div class="small text-muted">${escapeHtml(notification.message)}</div>
                                        ${reasonHtml}
                                        <div class="small text-muted mt-2">${escapeHtml(notification.created_at_human || '')}</div>
                                    </div>
                                    <div class="d-flex flex-column gap-2">
                                        <a href="${escapeHtml(notification.url)}" class="btn btn-sm btn-outline-primary">فتح</a>
                                        ${markReadHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                };

                const refreshNotificationFeed = async (options = {}) => {
                    try {
                        const response = await fetch(@json(route('notifications.feed')), {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        const notifications = payload.notifications || [];
                        const newLatestId = notifications[0]?.id || null;
                        if (options.playSound && latestNotificationId && newLatestId && newLatestId !== latestNotificationId) {
                            processIncomingNotification(notifications[0], { refresh: false });
                        }

                        latestNotificationId = newLatestId;
                        updateUnreadState(payload.unread_count || 0);
                        renderNotifications(notifications);
                    } catch (error) {
                    }
                };

                const syncFirebaseToken = async (token) => {
                    if (!token || !csrfToken) {
                        return;
                    }

                    await fetch(@json(route('notifications.firebase-token')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            token,
                            device_name: [navigator.platform, navigator.userAgent].filter(Boolean).join(' | ').slice(0, 255),
                        }),
                    });
                };

                const initializeFirebaseMessaging = async () => {
                    try {
                        firebase.initializeApp(firebaseConfig);
                        const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
                        navigator.serviceWorker.addEventListener('message', (event) => {
                            if (event?.data?.type !== 'workflow-notification' || !event.data.payload) {
                                return;
                            }

                            processIncomingNotification(event.data.payload);
                        });

                        const permission = Notification.permission === 'granted'
                            ? 'granted'
                            : (Notification.permission === 'default'
                                ? await Notification.requestPermission()
                                : Notification.permission);

                        if (permission !== 'granted') {
                            return;
                        }

                        const messaging = firebase.messaging();
                        const token = await messaging.getToken({
                            vapidKey,
                            serviceWorkerRegistration: registration,
                        });

                        await syncFirebaseToken(token);

                        messaging.onMessage((payload) => {
                            processIncomingNotification(payload);
                        });
                    } catch (error) {
                    }
                };

                refreshNotificationFeed();
                setInterval(() => refreshNotificationFeed({ playSound: true }), 30000);

                if ('serviceWorker' in navigator && 'Notification' in window) {
                    initializeFirebaseMessaging();
                }
            })();
        </script>
    @endif
    @stack('scripts')
</body>
</html>
