@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    $availableLocales = (array) config('app.available_locales', []);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $systemTitle = __('نظام إدارة سير العمل');
        $pageTitle = $pageTitle !== ''
            ? str_replace(' | WorkFlow', '', $pageTitle) . ' | ' . $systemTitle . ' | DAYANCO'
            : $systemTitle . ' | DAYANCO';
    @endphp
    <title>{{ $pageTitle }}</title>
    @if ($dir === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <style>
        body { min-height: 100vh; background: linear-gradient(135deg, #4f46e5 0%, #1e293b 100%); text-align: start; }
        html[dir="rtl"] body { text-align: right; }
        html[dir="ltr"] body { text-align: left; }
        .guest-wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18px; padding: 24px; }
        .guest-card { width: 100%; max-width: 480px; border: 0; border-radius: 24px; box-shadow: 0 20px 60px rgba(15, 23, 42, .25); }
        .guest-footer { color: rgba(255,255,255,.86); font-size: .92rem; text-align: center; }
    </style>
    @stack('styles')
</head>
<body>
    <div class="guest-wrap">
        @if (!empty($availableLocales))
            <form method="POST" action="{{ route('locale.switch') }}" class="align-self-end">
                @csrf
                <select name="locale" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Language') }}">
                    @foreach ($availableLocales as $code => $label)
                        <option value="{{ $code }}" @selected($code === $locale)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        @yield('content')
        <div class="guest-footer">{{ __('نظام إدارة سير العمل') }} | DAYANCO</div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
