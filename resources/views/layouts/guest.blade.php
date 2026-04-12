<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WorkFlow')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: linear-gradient(135deg, #4f46e5 0%, #1e293b 100%); }
        .guest-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .guest-card { width: 100%; max-width: 480px; border: 0; border-radius: 24px; box-shadow: 0 20px 60px rgba(15, 23, 42, .25); }
    </style>
    @stack('styles')
</head>
<body>
    <div class="guest-wrap">
        @yield('content')
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
