<?php

use Illuminate\Support\Facades\Route;
use NcooDev\HormLogger\Middleware\SaveLog;
use NcooDev\HormLogger\Models\Entry;

beforeEach(function () {
    config([
        'horm.database.connection' => 'testing',
        'horm.database.table_name' => 'horm_entries',
    ]);

    Schema::connection('testing')->create('horm_entries', function ($table) {
        $table->uuid('id');
        $table->string('direction');
        $table->string('url')->nullable();
        $table->string('type');
        $table->string('method');
        $table->text('request')->nullable();
        $table->text('response')->nullable();
        $table->integer('status_code')->nullable();
        $table->text('content')->nullable();
        $table->timestamps();
    });

    Route::middleware(SaveLog::class)->post('/test-endpoint', function () {
        return response()->json(['message' => 'success'], 200);
    });

    Route::middleware(SaveLog::class)->post('/auth/login', function () {
        return response()->json(['token' => 'abc123'], 200);
    });
});

it('saves log when logger is enabled', function () {
    config(['horm.enabled' => true]);

    $this->postJson('/test-endpoint', ['data' => 'test'])
        ->assertStatus(200);

    expect(Entry::count())->toBe(1);

    $entry = Entry::first();
    expect($entry->url)->toContain('/test-endpoint');
    expect($entry->method->value)->toBe('POST');
    expect($entry->status_code)->toBe(200);
    expect($entry->direction->value)->toBe('incoming');
});

it('does not save log when logger is disabled', function () {
    config(['horm.enabled' => false]);

    $this->postJson('/test-endpoint', ['data' => 'test'])
        ->assertStatus(200);

    expect(Entry::count())->toBe(0);
});

it('excludes urls matching patterns', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_incoming_urls' => ['test-*'],
    ]);

    $this->postJson('/test-endpoint', ['data' => 'test'])
        ->assertStatus(200);

    expect(Entry::count())->toBe(0);
});

it('obfuscates sensitive fields in requests', function () {
    config([
        'horm.enabled' => true,
        'horm.obfuscate_fields' => ['password', 'token'],
    ]);

    $this->postJson('/auth/login', [
        'email' => 'user@example.com',
        'password' => 'mysecretpassword',
    ])->assertStatus(200);

    $entry = Entry::first();
    $request = unserialize(base64_decode($entry->request));

    // Request body is stored as an array from the DTO
    $requestBody = $request['body'] ?? [];
    expect($requestBody['email'] ?? null)->toBe('user@example.com');
    expect($requestBody['password'] ?? null)->not->toBe('mysecretpassword');
    expect($requestBody['password'] ?? '')->toContain('*');
});

it('obfuscates sensitive fields in responses', function () {
    config([
        'horm.enabled' => true,
        'horm.obfuscate_fields' => ['token'],
    ]);

    $this->postJson('/auth/login', ['email' => 'user@example.com'])
        ->assertStatus(200);

    $entry = Entry::first();
    $response = unserialize(base64_decode($entry->response));
    $content = json_decode(unserialize(base64_decode($entry->content)), true);

    expect($content['token'])->toBe('abc123');
});
