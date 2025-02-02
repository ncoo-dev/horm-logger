<?php

use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Spatie\PestPluginTestTime\testTime;

describe('API', function () {

    beforeEach(function () {
        testTime()->freeze('2024-01-01 00:00:00');

        [
            $this->entry00,
            $this->entry0,
            $this->entry1,
            $this->entry2,
            $this->entry3,
            $this->entry4,
        ] =
            \NcooDev\HormLogger\Models\Entry::factory(6)->sequence(
                ['created_at' => now()->subMinutes(10)],
                ['created_at' => now()->subSecond()],
                ['created_at' => now()],
                ['created_at' => now()->addSeconds(1)],
                ['created_at' => now()->addMinutes(3)],
                ['created_at' => now()->addMinutes(4)],
            )->create();

        config()->set('horm.horm_endpoint', [
            'enabled' => true,
            'secret' => 'my-secret',
            'url' => 'horm-my-url',
        ]);

        $this->refreshServiceProvider();

    });

    it('can or not connect with secredt', function ($secret, $expected) {
        $response = $this->get('/horm-my-url', [
            'horm-check-secret' => $secret,
        ]);

        $response->assertStatus($expected);
    })->with([
        'bad-secret' => ['bad-secret', 403],
        'empty secret' => ['', 403],
        'Good secret' => ['my-secret', 302], // argument attendu
    ]);

    it('cannot get entries without start', function () {
        $response = get('/horm-my-url', [
            'horm-check-secret' => 'my-secret',
        ]);

        $response->assertStatus(302);
    });

    it('can get entries', function () {
        $entries = getJson('/horm-my-url?'.http_build_query([
            'start' => now()->toDateTimeString(),
        ]), [
            'horm-check-secret' => 'my-secret',
        ])->assertSuccessful()
            ->assertJson(function (AssertableJson $json) {
                $json->has('data', 4)
                    ->has('data.0', function (AssertableJson $json) {

                        $json->has('id')
                            ->where('id', $this->entry1->id)
                            ->where('type', $this->entry1->type)
                            ->where('direction', $this->entry1->direction)
                            ->where('url', $this->entry1->url)
                            ->where('status_code', $this->entry1->status_code)
                            ->where('method', $this->entry1->method)
                            ->where('request', $this->entry1->request)
                            ->where('response', $this->entry1->response)
                            ->where('content', $this->entry1->content)
                            ->where('created_at', $this->entry1->created_at->format('Y-m-d H:i:s'))
                            ->where('updated_at', $this->entry1->updated_at->format('Y-m-d H:i:s'));
                    });
            });

    });

});
