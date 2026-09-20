<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $level = $this->faker->numberBetween(7, 9);

        return [
            'name' => static::nameForLevel($level, $this->faker->randomElement(['A', 'B', 'C'])),
            'level' => $level,
        ];
    }

    /**
     * Override newModel to guarantee name/level consistency.
     *
     * When a test provides ['level' => 7], the factory definition may
     * have generated a name for a different level. This method always
     * re-derives name from the final level value.
     */
    public function newModel(array $attributes = []): SchoolClass
    {
        $level = $attributes['level'] ?? $this->faker->numberBetween(7, 9);

        if (isset($attributes['level'])) {
            $section = $this->faker->randomElement(['A', 'B', 'C', 'D', 'E']);
            $attributes['name'] = static::nameForLevel($level, $section);
        }

        return parent::newModel($attributes);
    }

    /**
     * Deterministic name derived from numeric level + section letter.
     */
    public static function nameForLevel(int $level, string $section): string
    {
        return match ($level) {
            -3 => 'KB',
            -2 => "A-$section",
            -1 => "B-$section",
            1 => "I $section",
            2 => "II $section",
            3 => "III $section",
            4 => "IV $section",
            5 => "V $section",
            6 => "VI $section",
            7 => "VII $section",
            8 => "VIII $section",
            9 => "IX $section",
            10 => "X-$section",
            11 => "XI-$section",
            12 => "XII IPA-$section",
            default => "Level $level $section",
        };
    }
}
