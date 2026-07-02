<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'alert_type' => 'disease_alert',
            'title' => $this->faker->sentence(),
            'message' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['open', 'acknowledged', 'resolved']),
            'triggered_at' => now(),
            'is_preventive' => false,
        ];
    }
}
