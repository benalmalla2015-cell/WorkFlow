<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LocaleController extends Controller
{
    public function switch(Request $request): Response|RedirectResponse
    {
        $availableLocales = array_keys((array) config('app.available_locales', []));
        $locale = (string) $request->input('locale', '');

        if (!in_array($locale, $availableLocales, true)) {
            return $this->respond($request, 422, ['message' => __('Invalid locale selection.')]);
        }

        $request->session()->put('locale', $locale);

        if ($request->user()) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }

        return $this->respond($request, 200, ['message' => __('Locale updated successfully.'), 'locale' => $locale]);
    }

    private function respond(Request $request, int $status, array $payload): Response|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload, $status);
        }

        if ($status >= 400) {
            return back()->withErrors(['locale' => $payload['message'] ?? __('Invalid locale selection.')]);
        }

        return back()->with('success', $payload['message'] ?? __('Locale updated successfully.'));
    }
}
