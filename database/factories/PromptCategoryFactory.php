<?php

namespace Database\Factories;

use App\Models\PromptCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PromptCategory> */
class PromptCategoryFactory extends Factory
{
    protected $model = PromptCategory::class;

    public function configure(): static
    {
        return $this->afterCreating(function (PromptCategory $category) {
            $category->translations()->createMany([
                ['locale' => 'en', 'name' => fake()->unique()->words(2, true), 'description' => fake()->sentence()],
                ['locale' => 'ar', 'name' => 'فئة '.fake()->unique()->numberBetween(1000, 9999), 'description' => 'وصف عربي للفئة.'],
            ]);
        });
    }

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'icon' => 'folder',
            'display_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
