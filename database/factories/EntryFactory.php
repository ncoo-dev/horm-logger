<?php

namespace NcooDev\HormLogger\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\Enums\Method;
use NcooDev\HormLogger\Models\Entry;

class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        $method = fake()->randomElement(['GET', 'POST', 'PUT', 'DELETE', 'PATCH']);
        $url = fake()->url;
        $statusCode = fake()->randomElement([200, 201, 204, 400, 401, 403, 404, 500]);

        return [
            'direction' => fake()->randomElement(Direction::cases()),
            'type' => fake()->randomElement(EntryType::cases()),
            'request' => json_encode([
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'method' => $method,
                'url' => $url,
                'body' => fake()->sentence,
            ]),
            'response' => json_encode([
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'status' => $statusCode,
                'body' => fake()->sentence,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
