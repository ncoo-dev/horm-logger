<?php

use NcooDev\HormLogger\Support\DataObfuscator;

it('obfuscates sensitive fields in data arrays', function () {
    config([
        'horm.obfuscate_fields' => ['password', 'token', 'api_key'],
    ]);

    $data = [
        'email' => 'user@example.com',
        'password' => 'secret123',
        'token' => 'abcdef123456',
        'api_key' => 'xyz789',
        'username' => 'johndoe',
    ];

    $obfuscated = DataObfuscator::obfuscate($data);

    expect($obfuscated['email'])->toBe('user@example.com');
    expect($obfuscated['username'])->toBe('johndoe');
    expect($obfuscated['password'])->not->toBe('secret123');
    expect($obfuscated['token'])->not->toBe('abcdef123456');
    expect($obfuscated['api_key'])->not->toBe('xyz789');
});

it('obfuscates nested sensitive fields', function () {
    config([
        'horm.obfuscate_fields' => ['password', 'secret'],
    ]);

    $data = [
        'user' => [
            'name' => 'John Doe',
            'password' => 'mypassword',
            'profile' => [
                'bio' => 'Developer',
                'secret' => 'topsecret',
            ],
        ],
    ];

    $obfuscated = DataObfuscator::obfuscate($data);

    expect($obfuscated['user']['name'])->toBe('John Doe');
    expect($obfuscated['user']['profile']['bio'])->toBe('Developer');
    expect($obfuscated['user']['password'])->not->toBe('mypassword');
    expect($obfuscated['user']['profile']['secret'])->not->toBe('topsecret');
});

it('masks values based on their length', function () {
    config([
        'horm.obfuscate_fields' => ['field'],
    ]);

    $shortValue = ['field' => '123'];
    $mediumValue = ['field' => '123456'];
    $longValue = ['field' => '1234567890abcdef'];

    expect(DataObfuscator::obfuscate($shortValue)['field'])->toBe('***');
    expect(DataObfuscator::obfuscate($mediumValue)['field'])->toMatch('/^12\*+$/');
    expect(DataObfuscator::obfuscate($longValue)['field'])->toMatch('/^123\*+def$/');
});

it('returns data unchanged when no fields are configured for obfuscation', function () {
    config([
        'horm.obfuscate_fields' => [],
    ]);

    $data = [
        'password' => 'secret',
        'token' => 'abc123',
    ];

    $obfuscated = DataObfuscator::obfuscate($data);

    expect($obfuscated)->toBe($data);
});

it('checks if incoming url should be excluded based on patterns', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_incoming_urls' => [
            'telescope/*',
            'horizon/*',
            'api/health',
        ],
    ]);

    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/telescope/requests'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/horizon/dashboard'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/api/health'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/api/users'))->toBeFalse();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/dashboard'))->toBeFalse();
});

it('checks if outgoing url should be excluded based on patterns', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_outgoing_urls' => [
            'https://api.stripe.com/*',
            'https://webhook.site/12345',
        ],
    ]);

    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.stripe.com/charges'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://webhook.site/12345'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.allowed.com/users'))->toBeFalse();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://webhook.site/67890'))->toBeFalse();
});

it('excludes all urls when logger is disabled', function () {
    config([
        'horm.enabled' => false,
        'horm.excluded_incoming_urls' => [],
        'horm.excluded_outgoing_urls' => [],
    ]);

    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/api/users'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.example.com/users'))->toBeTrue();
});

it('returns false when no excluded urls are configured', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_incoming_urls' => [],
        'horm.excluded_outgoing_urls' => [],
    ]);

    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/telescope'))->toBeFalse();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.example.com/users'))->toBeFalse();
});

it('handles incoming wildcard patterns correctly', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_incoming_urls' => [
            'admin/*',
            '*/webhooks',
            'api/*/logs',
        ],
    ]);

    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/admin/users'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/admin/settings/general'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/github/webhooks'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/api/v1/logs'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://example.com/api/users'))->toBeFalse();
});

it('excludes outgoing full URLs with wildcards', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_outgoing_urls' => [
            'https://api.example.com/*',
            'https://webhook.site/*',
            'https://*.github.com/api/*',
        ],
    ]);

    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.example.com/users'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.example.com/v1/posts'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://webhook.site/unique-id'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.github.com/api/repos'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://github.com/api/user'))->toBeTrue();

    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.other.com/users'))->toBeFalse();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://github.com/users'))->toBeFalse();
});

it('excludes exact outgoing full URLs', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_outgoing_urls' => [
            'https://api.payment.com/charge',
            'https://webhook.example.com/notify',
        ],
    ]);

    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.payment.com/charge'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://webhook.example.com/notify'))->toBeTrue();

    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.payment.com/refund'))->toBeFalse();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://webhook.example.com/other'))->toBeFalse();
});

it('handles mixed incoming and outgoing exclusions', function () {
    config([
        'horm.enabled' => true,
        'horm.excluded_incoming_urls' => [
            'admin/*',                          // Path pattern for incoming
            'telescope',                        // Exact path for incoming
        ],
        'horm.excluded_outgoing_urls' => [
            'https://api.external.com/*',       // Full URL pattern for outgoing
            'https://webhook.site/12345',       // Exact URL for outgoing
        ],
    ]);

    // Incoming patterns
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://myapp.com/admin/users'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://myapp.com/telescope'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeIncomingUrl('https://myapp.com/users'))->toBeFalse();

    // Outgoing patterns
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.external.com/users'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://webhook.site/12345'))->toBeTrue();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://api.other.com/users'))->toBeFalse();
    expect(DataObfuscator::shouldExcludeOutgoingUrl('https://webhook.site/67890'))->toBeFalse();
});
