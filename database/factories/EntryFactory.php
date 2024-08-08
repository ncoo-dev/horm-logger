<?php

namespace NcooDev\HormLogger\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\Models\Entry;

class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        return [
            'direction' => fake()->randomElement(Direction::cases()),
            'url' => fake()->url,
            'type' => fake()->randomElement(EntryType::cases()),
            'method' => fake()->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'request' => base64_encode(serialize([
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'method' => 'GET',
                'url' => fake()->url,
                'body' => fake()->sentence,
            ])),
            'response' => base64_encode(serialize([
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'status' => 200,
                'body' => fake()->sentence,
            ])),
            'content' => base64_encode(serialize([
                'message' => fake()->sentence,
                'context' => fake()->sentence,
            ])),
            'status_code' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
