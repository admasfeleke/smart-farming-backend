<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DiseaseReport;
use App\Models\Plot;
use App\Models\Crop;
use App\Models\User;
use Illuminate\Support\Carbon;

class DiseaseReportSeeder extends Seeder
{
    public function run(): void
    {
        $plots = Plot::with('farm')->get();
        $crops = Crop::all();
        $reporters = User::whereHas('role', fn ($q) => $q->where('name', 'farmer'))->get();

        $severities = ['low', 'medium', 'high', 'critical'];

        foreach (range(1, 30) as $i) {
            DiseaseReport::create([
                'plot_id' => $plots->random()->id,
                'crop_id' => $crops->random()->id,
                'reported_by' => $reporters->random()->id,
                'ai_prediction' => fake()->randomElement([
                    'Leaf Blight',
                    'Rust Disease',
                    'Bacterial Wilt',
                    'Powdery Mildew',
                ]),
                'confidence_score' => fake()->randomFloat(2, 0.6, 0.95),
                'severity' => fake()->randomElement($severities),
                'status' => fake()->randomElement(['new', 'verified', 'in_progress']),
                'reported_at' => Carbon::now()->subDays(rand(0, 20)),
            ]);
        }
    }
}

