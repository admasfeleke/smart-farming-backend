<?php

namespace Tests\Feature\Api\V1;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Planting;
use App\Models\Plot;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\WeatherData;
use App\Models\SoilHealth;
use App\Models\Alert;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnhancedFeaturesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_weather_data_crud_and_scoping(): void
    {
        if (! Schema::hasTable('weather_data')) {
            $this->markTestSkipped('Weather data table is not available in this test database.');
        }

        $supporter = $this->createUserWithRole('Weather Supporter', '0911000101', 'supporter');
        $otherSupporter = $this->createUserWithRole('Other Weather Supporter', '0911000102', 'supporter');

        $farm = Farm::create([
            'farmer_id' => $supporter->id,
            'region_id' => $supporter->region_id,
            'farm_name' => 'Weather Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Weather Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($supporter);

        // Create weather data
        $create = $this->postJson('/api/v1/weather-data', [
            'region_id' => $supporter->region_id,
            'farm_id' => $farm->id,
            'plot_id' => $plot->id,
            'temperature' => 25.5,
            'humidity' => 75.0,
            'precipitation' => 10.2,
            'wind_speed' => 15.0,
            'soil_moisture' => 45.0,
            'data_source' => 'manual',
            'recorded_at' => now()->toIso8601String(),
        ]);

        // Debug the response
        if ($create->status() !== 201) {
            $create->dump();
        }

        $create->assertStatus(201);

        $weatherId = (int) $create->json('id');

        // Get weather data
        $this->getJson("/api/v1/weather-data/{$weatherId}")
            ->assertOk()
            ->assertJsonPath('id', $weatherId);

        // Update weather data
        $this->putJson("/api/v1/weather-data/{$weatherId}", [
            'temperature' => 26.0,
            'humidity' => 78.0,
        ])->assertOk()
            ->assertJsonPath('temperature', '26.00');

        // Test scoping - other farmer should not see this data
        Sanctum::actingAs($otherSupporter);
        $this->getJson("/api/v1/weather-data/{$weatherId}")
            ->assertStatus(403);

        // Test summary endpoint
        Sanctum::actingAs($supporter);
        $this->getJson('/api/v1/weather-data/summary')
            ->assertOk()
            ->assertJsonStructure(['summary' => [
                'avg_temperature',
                'min_temperature',
                'max_temperature',
                'avg_humidity',
                'total_precipitation',
                'avg_wind_speed',
                'avg_soil_moisture',
                'total_records',
            ]]);
    }

    public function test_soil_health_crud_and_recommendations(): void
    {
        if (! Schema::hasTable('soil_health')) {
            $this->markTestSkipped('Soil health table is not available in this test database.');
        }

        $supporter = $this->createUserWithRole('Soil Supporter', '0911000103', 'supporter');

        $farm = Farm::create([
            'farmer_id' => $supporter->id, // Note: supporter creating for their region
            'region_id' => $supporter->region_id,
            'farm_name' => 'Soil Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Soil Plot',
            'soil_type' => 'clay',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($supporter);

        // Create soil health data
        $create = $this->postJson('/api/v1/soil-health', [
            'plot_id' => $plot->id,
            'ph_level' => 5.2,
            'nitrogen' => 30.0,
            'phosphorus' => 15.0,
            'potassium' => 80.0,
            'organic_matter' => 1.5,
            'soil_type' => 'clay',
            'moisture_level' => 25.0,
            'test_date' => now()->toDateString(),
            'recommendations' => 'Initial recommendations',
            'test_method' => 'manual',
        ])->assertStatus(201)
            ->assertJsonPath('ph_level', '5.20')
            ->assertJsonPath('soil_type', 'clay');

        $soilId = (int) $create->json('id');

        // Get recommendations
        $response = $this->getJson("/api/v1/soil-health/{$soilId}/recommendations")
            ->assertOk()
            ->assertJsonStructure([
                'soil_health',
                'recommendations',
            ]);

        $recommendations = $response->json('recommendations');
        $this->assertIsArray($recommendations);
        $this->assertEqualsCanonicalizing([
            'Add organic matter (compost, manure, cover crops) to improve soil structure',
            'Add organic matter to improve drainage and aeration',
            'Apply lime to raise soil pH to optimal range (5.5-7.0)',
            'Apply nitrogen-rich fertilizer (e.g., urea, compost)',
            'Apply phosphorus fertilizer (e.g., rock phosphate, bone meal)',
            'Apply potassium fertilizer (e.g., potash, wood ash)',
            'Increase irrigation and consider mulching to retain moisture',
        ], $recommendations);
    }

    public function test_yield_prediction_service(): void
    {
        $farmer = $this->createFarmer('Yield Farmer', '0911000104');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Yield Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Yield Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);

        $crop = Crop::firstOrCreate(
            ['name' => 'Wheat'],
            [
                'crop_type' => 'cereal',
                'is_active' => 1,
            ]
        );

        $planting = Planting::create([
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'planting_date' => now()->subDays(60),
            'expected_harvest_date' => now()->addDays(30),
            'status' => 'active',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($farmer);

        // Test yield prediction
        $this->postJson('/api/v1/yield-prediction', [
            'planting_id' => $planting->id,
            'temperature' => 22.5,
            'humidity' => 65.0,
            'precipitation' => 25.0,
            'soil_ph' => 6.5,
            'soil_nitrogen' => 80.0,
            'soil_phosphorus' => 40.0,
            'soil_potassium' => 200.0,
            'soil_moisture' => 50.0,
        ])->assertStatus(200)
            ->assertJsonStructure([
                'planting',
                'prediction' => [
                    'predicted_yield',
                    'confidence_interval' => ['lower', 'upper'],
                    'confidence_level',
                    'factors',
                    'recommendations',
                    'prediction_date',
                ],
            ])
            ->assertJsonPath('prediction.confidence_level', 63.3);

        // Test prediction without current conditions
        $this->getJson("/api/v1/yield-prediction/{$planting->id}")
            ->assertOk()
            ->assertJsonStructure([
                'planting',
                'prediction' => [
                    'predicted_yield',
                    'confidence_interval' => ['lower', 'upper'],
                    'confidence_level',
                    'factors',
                    'recommendations',
                    'prediction_date',
                ],
            ]);
    }

    public function test_disease_prevention_service(): void
    {
        $farmer = $this->createFarmer('Prevention Farmer', '0911000105');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Prevention Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Prevention Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);

        $crop = Crop::firstOrCreate(
            ['name' => 'Tomato'],
            [
                'crop_type' => 'vegetable',
                'is_active' => 1,
            ]
        );

        Planting::create([
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'planting_date' => now()->subDays(30),
            'expected_harvest_date' => now()->addDays(60),
            'status' => 'active',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($farmer);

        // Test preventive recommendations
        $this->getJson('/api/v1/disease-prevention/recommendations?crop_id=' . $crop->id . '&temperature=28.0&humidity=85.0&precipitation=60.0&soil_moisture=85.0')
            ->assertStatus(200)
            ->assertJsonStructure([
                'crop',
                'conditions',
                'recommendations',
            ])
            ->assertJsonFragment(['recommendations' => [
                'Monitor crop health daily',
                'Practice good sanitation in the field',
                'Use certified disease-free seeds',
                'Implement proper crop rotation',
                'Increase spacing between plants for better air circulation',
                'Avoid watering during evening hours',
                'Check drainage systems',
                'Consider raised beds for better water management',
            ]]);
    }

    public function test_preventive_alert_generation(): void
    {
        if (! Schema::hasTable('alerts')) {
            $this->markTestSkipped('Alerts table is not available in this test database.');
        }

        $supporter = $this->createUserWithRole('Alert Supporter', '0911000106', 'supporter');

        $farm = Farm::create([
            'farmer_id' => $supporter->id,
            'region_id' => $supporter->region_id,
            'farm_name' => 'Alert Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Alert Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);

        $crop = Crop::firstOrCreate(
            ['name' => 'Potato'],
            [
                'crop_type' => 'vegetable',
                'is_active' => 1,
            ]
        );

        Planting::create([
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'planting_date' => now()->subDays(45),
            'expected_harvest_date' => now()->addDays(45),
            'status' => 'active',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($supporter);

        // Create weather conditions that should trigger high risk
        WeatherData::create([
            'plot_id' => $plot->id,
            'temperature' => 25.0,
            'humidity' => 85.0,
            'precipitation' => 60.0,
            'wind_speed' => 5.0,
            'soil_moisture' => 85.0,
            'data_source' => 'manual',
            'recorded_at' => now(),
        ]);

        // Run disease prevention analysis
        $this->postJson('/api/v1/disease-prevention/analyze', [
            'plot_id' => $plot->id,
            'temperature' => 25.0,
            'humidity' => 85.0,
            'precipitation' => 60.0,
            'soil_moisture' => 85.0,
        ])->assertStatus(200);

        // Check that preventive alerts were created
        $alerts = Alert::where('farm_id', $farm->id)
            ->where('is_preventive', true)
            ->where('status', 'open')
            ->get();

        $this->assertNotEmpty($alerts, 'Preventive alerts should be generated for high-risk conditions');
        
        $highRiskAlert = $alerts->first();
        $this->assertNotNull($highRiskAlert);
        $this->assertGreaterThan(0.6, (float) $highRiskAlert->risk_level);
        $this->assertContains($highRiskAlert->alert_type, ['fungal_prevention', 'root_rot_prevention']);
    }

    private function createFarmer(string $name, string $phone): User
    {
        return $this->createUserWithRole($name, $phone, 'farmer');
    }

    private function createUserWithRole(string $name, string $phone, string $roleName): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['description' => ucfirst($roleName).' role']
        );

        $region = Region::create([
            'name' => 'Test Region '.uniqid(),
            'level' => 'region',
            'is_active' => 1,
        ]);

        $adminLevel = match ($roleName) {
            'super_admin' => 'national',
            'admin', 'supporter', 'expert' => 'region',
            default => null,
        };

        $payload = [
            'role_id' => $role->id,
            'region_id' => $region->id,
            'name' => $name,
            'phone' => $phone,
            'email' => $phone.'.'.uniqid().'@test.local',
            'password' => bcrypt('password123'),
            'is_active' => 1,
        ];

        if (Schema::hasColumn('users', 'admin_level')) {
            $payload['admin_level'] = $adminLevel;
        }

        return User::create($payload);
    }
}