<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames()->values() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->values() ?? [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'info' => fn () => $request->session()->get('info'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'branding' => fn () => $this->branding(),
        ];
    }

    private function branding(): array
    {
        $logoPath = Setting::getValue('logo_path');
        $faviconPath = Setting::getValue('favicon_path');

        return [
            'library_name' => Setting::getValue('library_name', 'Bibliothèque EDSP'),
            'institution_name' => Setting::getValue('institution_name', 'Université de Mahajanga'),
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
            'favicon_url' => $faviconPath ? Storage::disk('public')->url($faviconPath) : asset('favicon.ico'),
        ];
    }
}
