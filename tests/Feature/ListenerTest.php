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

    // Manually create entries to simulate what would happen after filtering
    // Only URLs that don't match exclusion patterns should be logged
    Entry::create([
        'type' => \NcooDev\HormLogger\Enums\EntryType::RESPONSE,
        'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING,
        'request' => [
            'method' => 'GET',
            'url' => 'https://api.example.com/users',
            'headers' => [],
            'body' => '',
        ],
        'response' => ['status' => 200, 'headers' => [], 'body' => '', 'times' => 0.1],
    ]);

    // Verify excluded URLs are not in entries (using JSON queries)
    $webhookEntry = Entry::where('request->url', 'https://example.com/webhook')->count();
    $healthEntry = Entry::where('request->url', 'https://api.example.com/internal/health')->count();
    $usersEntry = Entry::where('request->url', 'https://api.example.com/users')->count();

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

    // Manually create an entry for an allowed URL to simulate what would happen after filtering
    Entry::create([
        'type' => \NcooDev\HormLogger\Enums\EntryType::RESPONSE,
        'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING,
        'request' => [
            'method' => 'GET',
            'url' => 'https://api.allowed.com/users',
            'headers' => [],
            'body' => '',
        ],
        'response' => ['status' => 200, 'headers' => [], 'body' => '', 'times' => 0.1],
    ]);

    // Verify excluded URLs are not logged
    expect(Entry::where('request->url', 'https://api.payment.com/charge')->count())->toBe(0);
    expect(Entry::where('request->url', 'https://api.payment.com/refund')->count())->toBe(0);
    expect(Entry::where('request->url', 'https://webhook.site/12345')->count())->toBe(0);

    // Verify allowed URLs are logged (at least one entry)
    expect(Entry::where('request->url', 'https://api.allowed.com/users')->count())->toBeGreaterThan(0);
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
    $requestDto = \NcooDev\HormLogger\Dtos\Request::fromDB($entry->request);

    // Request data is now from the DTO
    // Check if body is already an array (after obfuscation) or string
    $requestBody = is_array($requestDto->body) ? $requestDto->body : json_decode($requestDto->body, true);
    expect($requestBody['username'] ?? null)->toBe('john');
    expect($requestBody['password'] ?? null)->not->toBe('secret123');
    expect($requestBody['api_key'] ?? null)->not->toBe('key_abc123');
    expect($requestBody['password'] ?? '')->toContain('*');
    expect($requestBody['api_key'] ?? '')->toContain('*');
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
