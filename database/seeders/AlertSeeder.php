<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Farm;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    public function run(): void
    {
        $severities = ['low', 'medium', 'high', 'critical'];
        $farms = Farm::take(5)->get();

        foreach ($farms as $farm) {
            foreach ($severities as $severity) {
                Alert::factory(random_int(2, 5))->create([
                    'farm_id' => $farm->id,
                    'severity' => $severity,
                    'alert_type' => 'disease_alert',
                    'title' => "Test {$severity} severity alert",
                    'status' => 'open',
                ]);
            }
        }
    }
}
