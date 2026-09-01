<?php

namespace Database\Factories;

use App\Enums\PromptSource;
use App\Enums\PromptStatus;
use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Prompt> */
class PromptFactory extends Factory
{
    protected $model = Prompt::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'owner_id' => User::factory(), 'category_id' => PromptCategory::factory(),
            'source' => PromptSource::Library, 'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(), 'content' => fake()->paragraphs(2, true),
            'content_locale' => 'en', 'visibility' => PromptVisibility::Private,
            'status' => PromptStatus::Draft, 'published_at' => null, 'published_by' => null,
            'revision_number' => 1,
        ];
    }

    public function published(PromptVisibility $visibility = PromptVisibility::AllUsers): static
    {
        return $this->state(fn () => ['status' => PromptStatus::Published, 'visibility' => $visibility, 'published_at' => now()]);
    }
}
