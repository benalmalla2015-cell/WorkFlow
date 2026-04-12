<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WorkFlow')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; color: #1f2937; }
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
        .status-draft { background: #e2e8f0; color: #334155; }
        .status-factory_pricing { background: #dbeafe; color: #1d4ed8; }
        .status-manager_review { background: #fef3c7; color: #b45309; }
        .status-approved { background: #dcfce7; color: #15803d; }
        .status-customer_approved { background: #cffafe; color: #0f766e; }
        .status-payment_confirmed { background: #d1fae5; color: #047857; }
        .status-completed { background: #ede9fe; color: #6d28d9; }
        .form-card { border: 0; border-radius: 18px; box-shadow: 0 12px 34px rgba(15, 23, 42, .06); }
        .attachment-list a { text-decoration: none; }
        .chart-card canvas { max-height: 320px; }
        @media (max-width: 991.98px) {
            .portal-shell { display: block; }
            .portal-sidebar { width: 100%; height: auto; position: relative; }
            .portal-header, .portal-content { padding: 18px; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @php
        $user = auth()->user();
        $links = [];

        if ($user?->isAdmin()) {
            $links = [
                ['label' => 'لوحة الإدارة', 'route' => 'admin.dashboard'],
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
            <div class="portal-brand">DAYANCO WorkFlow</div>
            <div class="portal-tagline">Laravel + MySQL Operational Portal</div>

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
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
