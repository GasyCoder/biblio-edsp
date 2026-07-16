<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    config()->set('services.cloudflare.account_id', 'account-test');
    config()->set('services.cloudflare.api_token', 'secret-cloudflare-token');
    config()->set('services.cloudflare.ai_image_model', '@cf/black-forest-labs/flux-1-schnell');
    $this->actingAs(User::factory()->create()->assignRole('secretaire'));
});

it('rejects an empty prompt', function () {
    $this->postJson(route('ai.images.generate'), ['prompt' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('prompt');
});

it('rejects a prompt longer than 2048 characters', function () {
    $this->postJson(route('ai.images.generate'), ['prompt' => str_repeat('a', 2049)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('prompt');
});

it('generates an image without exposing Cloudflare credentials', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response([
        'success' => true,
        'result' => ['image' => base64_encode('fake-jpeg')],
    ])]);

    $response = $this->postJson(route('ai.images.generate'), [
        'prompt' => 'Une couverture universitaire bleue',
        'steps' => 4,
        'seed' => 123456,
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.seed', 123456)
        ->assertJsonPath('data.image', 'data:image/jpeg;base64,'.base64_encode('fake-jpeg'));

    expect($response->getContent())
        ->not->toContain('secret-cloudflare-token')
        ->not->toContain('account-test');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-cloudflare-token')
        && $request['prompt'] === 'Une couverture universitaire bleue'
        && $request['steps'] === 4
        && $request['seed'] === 123456);
});

it('returns a safe message when Cloudflare rejects the request', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'sensitive provider detail']]], 401)]);

    $response = $this->postJson(route('ai.images.generate'), ['prompt' => 'Couverture'])
        ->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'authentication_failed');

    expect($response->getContent())
        ->not->toContain('sensitive provider detail')
        ->not->toContain('secret-cloudflare-token');
});

it('handles a Cloudflare response without an image', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'result' => []])]);

    $this->postJson(route('ai.images.generate'), ['prompt' => 'Couverture'])
        ->assertStatus(502)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'image_missing');
});

it('requires authentication and an authorized role', function () {
    auth()->logout();
    $this->postJson(route('ai.images.generate'), ['prompt' => 'Couverture'])->assertUnauthorized();

    $this->actingAs(User::factory()->create()->assignRole('etudiant'))
        ->postJson(route('ai.images.generate'), ['prompt' => 'Couverture'])
        ->assertForbidden();
});
