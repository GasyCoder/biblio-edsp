<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));
it('allows the superadmin to configure operational settings', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $this->actingAs($admin)->patch(route('settings.update'), ['library_name' => 'Bibliothèque Test', 'institution_name' => 'EDSP', 'contact_email' => 'contact@edsp.mg', 'contact_phone' => '123', 'default_loan_days' => 21, 'max_books_per_loan' => 4, 'scanner_inactivity_seconds' => 60, 'card_validity_months' => 24])->assertRedirect();
    expect(Setting::getValue('default_loan_days'))->toBe('21')->and(Setting::getValue('scanner_inactivity_seconds'))->toBe('60');
    $this->get(route('settings.edit'))->assertInertia(fn (Assert $page) => $page->component('Settings/Edit')->where('settings.default_loan_days', 21));
});
it('denies settings to the secretary', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $this->actingAs($user)->get(route('settings.edit'))->assertForbidden();
});

it('uploads and shares the library logo and favicon', function () {
    Storage::fake('public');
    $admin = User::factory()->create()->assignRole('superadmin');

    $this->actingAs($admin)->post(route('settings.update'), [
        '_method' => 'patch',
        'library_name' => 'Bibliothèque Test',
        'institution_name' => 'EDSP',
        'contact_email' => 'contact@edsp.mg',
        'contact_phone' => '123',
        'default_loan_days' => 21,
        'max_books_per_loan' => 4,
        'scanner_inactivity_seconds' => 60,
        'card_validity_months' => 24,
        'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
        'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
    ])->assertRedirect();

    Storage::disk('public')->assertExists(Setting::getValue('logo_path'));
    Storage::disk('public')->assertExists(Setting::getValue('favicon_path'));

    $this->get(route('settings.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('application.version', fn ($version) => preg_match('/^\d+\.\d+\.\d+$/', $version) === 1)
        ->where('branding.library_name', 'Bibliothèque Test')
        ->where('branding.institution_name', 'EDSP')
        ->where('branding.logo_url', fn ($url) => str_contains($url, '/storage/branding/logo/'))
        ->where('branding.favicon_url', fn ($url) => str_contains($url, '/storage/branding/favicon/')));
});
