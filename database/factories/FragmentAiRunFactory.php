<?php

namespace Database\Factories;

use App\Models\FragmentAiRun;
use App\Models\FragmentProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FragmentAiRun>
 */
class FragmentAiRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => FragmentProject::factory(),
            'user_id' => null,
            'kind' => FragmentAiRun::KIND_GENERATE,
            'status' => FragmentAiRun::STATUS_SUCCEEDED,
            'parameters' => ['count' => 1, 'hint' => null],
            'cost_usd' => 0,
        ];
    }

    public function failed(): static
    {
        return $this->state(['status' => FragmentAiRun::STATUS_FAILED, 'error' => 'Erreur Gemini.']);
    }
}
