<?php

namespace Keasy9\HitStatistics\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Keasy9\HitStatistics\Models\Hit;

class HitFactory extends Factory
{
    protected $model = Hit::class;

    public function definition()
    {
        return [
            'visited_from' => $this->faker->url(),
            'visited_to'   => config('app.url'),
            'country'      => $this->faker->countryCode(),
            'city'         => $this->faker->city(),
            'useragent'    => $this->faker->userAgent(),
        ];
    }

    public function fromUrlWithQuery(string $url): static
    {
        return $this->state(fn (array $attributes) => [
            'visited_from' => sprintf($url, Str::replace(' ', '+', $this->faker->sentence(3))),
        ]);
    }

    public function toUrl(string $url): static
    {
        return $this->state([
            'visited_to' => $url,
        ]);
    }

    public function withoutCity(): static
    {
        return $this->state([
            'city' => null,
        ]);
    }

    public function forDate(Carbon $date): static
    {
        return $this->state([
            'visited_at' => $date,
        ]);
    }
}
