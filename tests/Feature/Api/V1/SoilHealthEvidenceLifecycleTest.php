<?php

namespace Tests\Feature\Api\V1;

use App\Models\Farm;
use App\Models\Plot;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SoilHealthEvidenceLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_soil_health_evidence_is_replaced_on_update_and_removed_on_delete(): void
    {
        if (! Schema::hasTable('soil_health')) {
            $this->markTestSkipped('Soil health table is not available in this test database.');
        }

        Storage::fake('public');

        $supporter = $this->createUserWithRole('Soil Lifecycle Supporter', '0911000201', 'supporter');
        $plot = $this->createPlotForUser($supporter);

        Sanctum::actingAs($supporter);

        $create = $this->withHeader('Accept', 'application/json')->post('/api/v1/soil-health', [
            'plot_id' => $plot->id,
            'ph_level' => 6.1,
            'nitrogen' => 22.0,
            'phosphorus' => 11.0,
            'potassium' => 75.0,
            'organic_matter' => 2.3,
            'soil_type' => 'loam',
            'moisture_level' => 31.0,
            'test_date' => now()->toDateString(),
            'test_method' => 'manual',
            'evidence' => UploadedFile::fake()->image('before.jpg'),
        ]);

        $create->assertStatus(201);
        $soilId = (int) $create->json('id');

        $originalFiles = Storage::disk('public')->allFiles("soil_evidence/{$soilId}");
        $this->assertCount(1, $originalFiles);
        $originalPath = $originalFiles[0];

        $update = $this->withHeader('Accept', 'application/json')->post("/api/v1/soil-health/{$soilId}", [
            '_method' => 'PUT',
            'test_method' => 'manual',
            'review_status' => 'validated',
            'evidence' => UploadedFile::fake()->image('after.jpg'),
        ]);

        $update->assertOk();

        $updatedFiles = Storage::disk('public')->allFiles("soil_evidence/{$soilId}");
        $this->assertCount(1, $updatedFiles);
        $this->assertNotSame($originalPath, $updatedFiles[0]);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($updatedFiles[0]);

        $this->deleteJson("/api/v1/soil-health/{$soilId}")
            ->assertOk()
            ->assertJsonPath('message', 'Soil health data deleted successfully');

        Storage::disk('public')->assertMissing($updatedFiles[0]);
        $this->assertSame([], Storage::disk('public')->allFiles("soil_evidence/{$soilId}"));
    }

    private function createPlotForUser(User $user): Plot
    {
        $farm = Farm::create([
            'farmer_id' => $user->id,
            'region_id' => $user->region_id,
            'farm_name' => 'Lifecycle Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        return Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Lifecycle Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);
    }

    private function createUserWithRole(string $name, string $phone, string $roleName): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['description' => ucfirst($roleName) . ' role']
        );

        $region = Region::create([
            'name' => 'Lifecycle Region ' . uniqid(),
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
            'email' => $phone . '.' . uniqid() . '@test.local',
            'password' => bcrypt('password123'),
            'is_active' => 1,
        ];

        if (Schema::hasColumn('users', 'admin_level')) {
            $payload['admin_level'] = $adminLevel;
        }

        return User::create($payload);
    }
}
