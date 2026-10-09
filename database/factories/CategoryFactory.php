<?php

namespace Database\Factories;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'parent_id' => null,
            'image' => null,
            'order' => fake()->numberBetween(0, 100),
            'status' => CategoryStatus::ACTIVE,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CategoryStatus::ACTIVE,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CategoryStatus::INACTIVE,
        ]);
    }

    public function withParent(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'image' => fake()->imageUrl(),
        ]);
    }
}
