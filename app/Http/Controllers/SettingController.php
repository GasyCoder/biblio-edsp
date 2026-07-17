<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Settings/Edit', ['settings' => $this->values()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'library_name' => ['required', 'string', 'max:120'],
            'institution_name' => ['required', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'default_loan_days' => ['required', 'integer', 'min:1', 'max:90'],
            'max_books_per_loan' => ['required', 'integer', 'min:1', 'max:20'],
            'scanner_inactivity_seconds' => ['required', 'integer', 'min:10', 'max:180'],
            'card_validity_months' => ['required', 'integer', 'min:1', 'max:60'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:4096'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            foreach (collect($data)->except(['logo', 'favicon', 'remove_logo', 'remove_favicon']) as $key => $value) {
                Setting::put($key, $value, str_contains($key, 'loan') || str_contains($key, 'scanner') || str_contains($key, 'card_') ? 'operations' : 'general');
            }

            $this->storeBrandAsset($request, 'logo', 'branding/logo');
            $this->storeBrandAsset($request, 'favicon', 'branding/favicon');
        });

        return back()->with('success', 'Paramètres enregistrés et identité visuelle appliquée.');
    }

    private function storeBrandAsset(Request $request, string $key, string $directory): void
    {
        $currentPath = Setting::getValue("{$key}_path");

        if ($request->boolean("remove_{$key}")) {
            if ($currentPath) {
                Storage::disk('public')->delete($currentPath);
            }

            Setting::put("{$key}_path", '', 'branding');
        }

        if (! $request->hasFile($key)) {
            return;
        }

        if ($currentPath) {
            Storage::disk('public')->delete($currentPath);
        }

        Setting::put("{$key}_path", $request->file($key)->store($directory, 'public'), 'branding');
    }

    private function values(): array
    {
        $logoPath = Setting::getValue('logo_path');
        $faviconPath = Setting::getValue('favicon_path');

        return [
            'library_name' => Setting::getValue('library_name', 'Bibliothèque EDSP'),
            'institution_name' => Setting::getValue('institution_name', 'Université de Mahajanga'),
            'contact_email' => Setting::getValue('contact_email', ''),
            'contact_phone' => Setting::getValue('contact_phone', ''),
            'default_loan_days' => (int) Setting::getValue('default_loan_days', 14),
            'max_books_per_loan' => (int) Setting::getValue('max_books_per_loan', 5),
            'scanner_inactivity_seconds' => (int) Setting::getValue('scanner_inactivity_seconds', 40),
            'card_validity_months' => (int) Setting::getValue('card_validity_months', 12),
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
            'favicon_url' => $faviconPath ? Storage::disk('public')->url($faviconPath) : asset('favicon.ico'),
        ];
    }
}
