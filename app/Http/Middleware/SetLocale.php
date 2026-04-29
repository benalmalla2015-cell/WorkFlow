<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $availableLocales = array_keys((array) config('app.available_locales', []));
        $fallbackLocale = (string) config('app.fallback_locale', 'en');
        $defaultLocale = (string) config('app.locale', $fallbackLocale);

        $locale = $this->resolveLocale($request, $availableLocales, $defaultLocale, $fallbackLocale);

        app()->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request, array $availableLocales, string $defaultLocale, string $fallbackLocale): string
    {
        $candidate = $request->get('locale')
            ?: $request->header('X-Locale')
            ?: $request->header('Accept-Language');

        if (is_string($candidate) && $candidate !== '') {
            $locale = $this->parseLocaleCandidate($candidate, $availableLocales);
            if ($locale) {
                return $locale;
            }
        }

        $userLocale = $request->user()?->locale;
        if (is_string($userLocale) && in_array($userLocale, $availableLocales, true)) {
            return $userLocale;
        }

        $sessionLocale = $request->session()?->get('locale');
        if (is_string($sessionLocale) && in_array($sessionLocale, $availableLocales, true)) {
            return $sessionLocale;
        }

        if (in_array($defaultLocale, $availableLocales, true)) {
            return $defaultLocale;
        }

        return in_array($fallbackLocale, $availableLocales, true) ? $fallbackLocale : ($availableLocales[0] ?? 'en');
    }

    private function parseLocaleCandidate(string $candidate, array $availableLocales): ?string
    {
        $candidate = Str::of($candidate)->lower()->trim()->value();

        $direct = $this->normalizeLocale($candidate);
        if ($direct && in_array($direct, $availableLocales, true)) {
            return $direct;
        }

        foreach (explode(',', $candidate) as $segment) {
            $segment = trim(explode(';', $segment)[0] ?? '');
            $normalized = $this->normalizeLocale($segment);
            if ($normalized && in_array($normalized, $availableLocales, true)) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeLocale(string $value): ?string
    {
        $value = str_replace('_', '-', strtolower(trim($value)));
        if ($value === '') {
            return null;
        }

        return explode('-', $value)[0] ?? null;
    }
}
