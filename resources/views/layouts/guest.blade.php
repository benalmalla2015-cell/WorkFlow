<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $pageTitle = $pageTitle !== ''
            ? str_replace(' | WorkFlow', '', $pageTitle) . ' | نظام إدارة سير العمل | مؤسسة مدحت رشاد للحلول التقنية'
            : 'نظام إدارة سير العمل | مؤسسة مدحت رشاد للحلول التقنية';
    @endphp
    <title>{{ $pageTitle }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: linear-gradient(135deg, #4f46e5 0%, #1e293b 100%); text-align: right; }
        .guest-wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18px; padding: 24px; }
        .guest-card { width: 100%; max-width: 480px; border: 0; border-radius: 24px; box-shadow: 0 20px 60px rgba(15, 23, 42, .25); }
        .guest-footer { color: rgba(255,255,255,.86); font-size: .92rem; text-align: center; }
    </style>
    @stack('styles')
</head>
<body>
    <div class="guest-wrap">
        @yield('content')
        <div class="guest-footer">نظام إدارة سير العمل | مؤسسة مدحت رشاد للحلول التقنية</div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
