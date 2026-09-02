<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+2 months');

        return ['slug' => Str::slug(fake()->unique()->words(3, true)).'-'.Str::lower(Str::random(6)), 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'starts_at' => $start, 'ends_at' => (clone $start)->modify('+2 hours'), 'timezone' => 'UTC', 'location' => fake()->city(), 'organizer_id' => User::factory(), 'status' => 'confirmed', 'visibility' => 'all_users'];
    }
}
