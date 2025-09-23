<?php

use Illuminate\Support\Facades\Http;
use NcooDev\HormLogger\Models\Entry;

beforeEach(function () {
    config([
        'horm.database.connection' => 'testing',
        'horm.database.table_name' => 'horm_entries',
    ]);

    Schema::connection('testing')->dropIfExists('horm_entries');
    Schema::connection('testing')->create('horm_entries', function ($table) {
        $table->uuid('id');
        $table->string('direction');
        $table->string('type');
        $table->longText('request')->nullable();
        $table->longText('response')->nullable();
        $table->timestamps();
    });

    // The listener is already registered by HormLoggerServiceProvider
});

it('logs outgoing http requests when enabled', function () {
    config(['horm.enabled' => true]);

    Http::fake([
        'api.example.com/users' => Http::response(['name' => 'John'], 200),
    ]);

    Http::get('https://api.example.com/users');

    $entry = Entry::first();
    expect($entry)->not->toBeNull();
    expect($entry->request['url'])->toBe('https://api.example.com/users');
    expect($entry->request['method'])->toBe('GET');
    expect($entry->response['status'])->toBe(200);
    expect($entry->direction->value)->toBe('outgoing');
});

it('does not log when logger is disabled', function () {
    config(['horm.enabled' => false]);

    Http::fake([
        'api.example.com/users' => Http::response(['name' => 'John'], 200),
    ]);

    Http::get('https://api.example.com/users');

    expect(Entry::count())->toBe(0);
});

it('excludes outgoing urls matching patterns', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_outgoing_urls' => ['*/webhook', 'internal/*'],
    ]);

    Http::fake([
        'example.com/webhook' => Http::response([], 200),
        'api.example.com/internal/health' => Http::response([], 200),
        'api.example.com/users' => Http::response([], 200),
    ]);

    Http::get('https://example.com/webhook');
    Http::get('https://api.example.com/internal/health');
    Http::get('https://api.example.com/users');

    $webhookEntry = Entry::where('request', 'like', '%https://example.com/webhook%')->count();
    $healthEntry = Entry::where('request', 'like', '%https://api.example.com/internal/health%')->count();
    $usersEntry = Entry::where('request', 'like', '%https://api.example.com/users%')->count();

    expect($webhookEntry)->toBe(0);
    expect($healthEntry)->toBe(0);
    expect($usersEntry)->toBeGreaterThan(0);
});

it('excludes full URLs for outgoing requests', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_outgoing_urls' => [
            'https://api.payment.com/*',
            'https://webhook.site/12345',
        ],
    ]);

    Http::fake([
        'api.payment.com/*' => Http::response(['status' => 'success'], 200),
        'webhook.site/*' => Http::response(['received' => true], 200),
        'api.allowed.com/*' => Http::response(['data' => 'test'], 200),
    ]);

    Http::post('https://api.payment.com/charge', ['amount' => 100]);
    Http::post('https://api.payment.com/refund', ['id' => 123]);
    Http::post('https://webhook.site/12345', ['event' => 'test']);
    Http::get('https://api.allowed.com/users');

    // Verify excluded URLs are not logged
    expect(Entry::where('request', 'like', '%https://api.payment.com/charge%')->count())->toBe(0);
    expect(Entry::where('request', 'like', '%https://api.payment.com/refund%')->count())->toBe(0);
    expect(Entry::where('request', 'like', '%https://webhook.site/12345%')->count())->toBe(0);

    // Verify allowed URLs are logged (at least one entry)
    expect(Entry::where('request', 'like', '%https://api.allowed.com/users%')->count())->toBeGreaterThan(0);
});

it('obfuscates sensitive data in requests', function () {
    config([
        'horm.enabled' => true,
        'horm.obfuscate_fields' => ['api_key', 'password'],
    ]);

    Http::fake([
        'api.example.com/auth' => Http::response(['token' => 'jwt123'], 200),
    ]);

    Http::post('https://api.example.com/auth', [
        'username' => 'john',
        'password' => 'secret123',
        'api_key' => 'key_abc123',
    ]);

    $entry = Entry::first();
    $request = unserialize(base64_decode($entry->request));

    // Request data is now an array from the DTO
    expect($request['body']['username'] ?? null)->toBe('john');
    expect($request['body']['password'] ?? null)->not->toBe('secret123');
    expect($request['body']['api_key'] ?? null)->not->toBe('key_abc123');
    expect($request['body']['password'] ?? '')->toContain('*');
    expect($request['body']['api_key'] ?? '')->toContain('*');
});

it('obfuscates sensitive data in responses', function () {
    config([
        'horm.enabled' => true,
        'horm.obfuscate_fields' => ['token', 'secret'],
    ]);

    Http::fake([
        'api.example.com/auth' => Http::response([
            'user' => 'john',
            'token' => 'bearer_abc123xyz',
            'secret' => 'topsecret',
        ], 200),
    ]);

    Http::post('https://api.example.com/auth');

    $entry = Entry::first();
    $responseData = $entry->response;

    // Response body is now stored as array directly when it's JSON
    $responseBody = $responseData['body'];

    expect($responseBody['user'])->toBe('john');
    expect($responseBody['token'])->not->toBe('bearer_abc123xyz');
    expect($responseBody['secret'])->not->toBe('topsecret');
    expect($responseBody['token'])->toContain('*');
    expect($responseBody['secret'])->toContain('*');
});
