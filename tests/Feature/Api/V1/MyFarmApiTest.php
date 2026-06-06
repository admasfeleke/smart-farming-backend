<?php

namespace Tests\Feature\Api\V1;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Planting;
use App\Models\Plot;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use App\Services\InferencePipelineService;
use App\Models\DiseaseReport;
use App\Models\Alert;
use App\Models\FailedInference;
use App\Jobs\ProcessDiseaseReportScan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class MyFarmApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_farms_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/farms')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ])
            ->assertJsonStructure(['request_id'])
            ->assertHeader('X-Request-Id');
    }

    public function test_farms_index_is_scoped_to_authenticated_farmer_and_returns_plot_counts(): void
    {
        $farmer = $this->createFarmer('Farmer One', '0911000001');
        $otherFarmer = $this->createFarmer('Farmer Two', '0911000002');

        $farmOne = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Alpha Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Beta Farm',
            'farm_type' => 'mixed',
            'is_active' => 1,
        ]);

        Farm::create([
            'farmer_id' => $otherFarmer->id,
            'region_id' => $otherFarmer->region_id,
            'farm_name' => 'Other Farm',
            'farm_type' => 'livestock',
            'is_active' => 1,
        ]);

        Plot::create([
            'farm_id' => $farmOne->id,
            'plot_name' => 'North',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);

        Plot::create([
            'farm_id' => $farmOne->id,
            'plot_name' => 'Inactive Plot',
            'soil_type' => 'clay',
            'is_active' => 0,
        ]);

        Sanctum::actingAs($farmer);

        $response = $this->getJson('/api/v1/farms')
            ->assertOk()
            ->assertJsonMissing(['farm_name' => 'Other Farm']);

        $farms = collect($response->json('data'));

        $alpha = $farms->firstWhere('farm_name', 'Alpha Farm');
        $this->assertNotNull($alpha);
        $this->assertSame(1, (int) ($alpha['plots_count'] ?? 0));

        $this->assertCount(2, $farms);
    }

    public function test_farmer_cannot_view_other_farm_plots(): void
    {
        $farmer = $this->createFarmer('Farmer One', '0911000011');
        $otherFarmer = $this->createFarmer('Farmer Two', '0911000012');

        $otherFarm = Farm::create([
            'farmer_id' => $otherFarmer->id,
            'region_id' => $otherFarmer->region_id,
            'farm_name' => 'Locked Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($farmer);

        $this->getJson("/api/v1/farms/{$otherFarm->id}/plots")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden.',
            ]);
    }

    public function test_planting_store_returns_field_validation_errors(): void
    {
        $farmer = $this->createFarmer('Farmer', '0911000021');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Planting Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Plot 1',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($farmer);

        $this->postJson("/api/v1/plots/{$plot->id}/plantings", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['crop_id', 'planting_date']);
    }

    public function test_farm_crud_and_validation_contract(): void
    {
        $farmer = $this->createFarmer('Farm CRUD Farmer', '0911000041');
        Sanctum::actingAs($farmer);

        $this->postJson('/api/v1/farms', [
            'region_id' => $farmer->region_id,
            'farm_name' => '',
            'farm_type' => 'invalid_type',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['farm_name', 'farm_type']);

        $create = $this->postJson('/api/v1/farms', [
            'region_id' => $farmer->region_id,
            'farm_name' => 'Gamma Farm',
            'latitude' => 9.0012345,
            'longitude' => 38.0012345,
            'area_hectares' => 4.5,
            'farm_type' => 'crop',
            'is_active' => 1,
        ])->assertStatus(201)
            ->assertJsonPath('data.farm_name', 'Gamma Farm')
            ->assertJsonPath('data.farmer_id', $farmer->id);

        $farmId = (int) $create->json('data.id');

        $this->putJson("/api/v1/farms/{$farmId}", [
            'farm_type' => 'mixed',
            'farm_name' => 'Gamma Farm Updated',
        ])->assertOk()
            ->assertJsonPath('data.farm_name', 'Gamma Farm Updated')
            ->assertJsonPath('data.farm_type', 'mixed');

        $this->deleteJson("/api/v1/farms/{$farmId}")
            ->assertNoContent();

        $this->assertDatabaseHas('farms', [
            'id' => $farmId,
            'is_active' => 0,
        ]);
    }

    public function test_farm_store_rejects_region_outside_farmer_scope(): void
    {
        $farmer = $this->createFarmer('Scoped Region Farmer', '0911000042');
        Sanctum::actingAs($farmer);

        $otherRegion = Region::create([
            'name' => 'Unscoped Region '.uniqid(),
            'level' => 'region',
            'is_active' => 1,
        ]);

        $this->postJson('/api/v1/farms', [
            'region_id' => $otherRegion->id,
            'farm_name' => 'Out of Scope Farm',
            'farm_type' => 'crop',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['region_id']);
    }

    public function test_plot_crud_and_cross_owner_forbidden(): void
    {
        $farmer = $this->createFarmer('Plot CRUD Farmer', '0911000051');
        $otherFarmer = $this->createFarmer('Other Plot Farmer', '0911000052');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Plot Parent Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $otherFarm = Farm::create([
            'farmer_id' => $otherFarmer->id,
            'region_id' => $otherFarmer->region_id,
            'farm_name' => 'Other Parent Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($farmer);

        $this->postJson("/api/v1/farms/{$farm->id}/plots", [
            'plot_name' => '',
            'soil_type' => 'invalid_soil',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['plot_name', 'soil_type']);

        $create = $this->postJson("/api/v1/farms/{$farm->id}/plots", [
            'plot_name' => 'Plot East',
            'area_hectares' => 2.0,
            'soil_type' => 'loam',
            'is_active' => 1,
        ])->assertStatus(201)
            ->assertJsonPath('data.farm_id', $farm->id)
            ->assertJsonPath('data.plot_name', 'Plot East');

        $plotId = (int) $create->json('data.id');

        $this->putJson("/api/v1/plots/{$plotId}", [
            'soil_type' => 'clay',
            'plot_name' => 'Plot East Updated',
        ])->assertOk()
            ->assertJsonPath('data.soil_type', 'clay');

        $this->getJson("/api/v1/farms/{$otherFarm->id}/plots")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden.',
            ]);

        $this->deleteJson("/api/v1/plots/{$plotId}")
            ->assertNoContent();

        $this->assertDatabaseHas('plots', [
            'id' => $plotId,
            'is_active' => 0,
        ]);
    }

    public function test_planting_crud_flow_for_owned_plot(): void
    {
        $farmer = $this->createFarmer('Farmer', '0911000031');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'CRUD Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'CRUD Plot',
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

        Sanctum::actingAs($farmer);

        $store = $this->postJson("/api/v1/plots/{$plot->id}/plantings", [
            'crop_id' => $crop->id,
            'planting_date' => '2026-02-01',
            'expected_harvest_date' => '2026-06-01',
            'status' => 'active',
            'is_active' => 1,
        ])->assertStatus(201)
            ->assertJsonPath('data.plot_id', $plot->id)
            ->assertJsonPath('data.crop_id', $crop->id);

        $plantingId = (int) $store->json('data.id');

        $this->getJson("/api/v1/plots/{$plot->id}/plantings")
            ->assertOk()
            ->assertJsonFragment(['id' => $plantingId, 'crop_id' => $crop->id]);

        $this->putJson("/api/v1/plantings/{$plantingId}", [
            'status' => 'harvested',
        ])->assertOk()
            ->assertJsonPath('data.status', 'harvested');

        $this->deleteJson("/api/v1/plantings/{$plantingId}")
            ->assertNoContent();

        $this->assertDatabaseHas('plantings', [
            'id' => $plantingId,
            'is_active' => 0,
        ]);
    }



    public function test_disease_scan_pipeline_creates_alert_after_supporter_confirmation(): void
    {
        if (! Schema::hasTable('disease_reports') || ! Schema::hasTable('alerts')) {
            $this->markTestSkipped('Disease report/alert tables are not available in this test database.');
        }

        $farmer = $this->createFarmer('Disease Farmer', '0911000061');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Disease Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Disease Plot',
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

        Queue::fake();
        Storage::fake('public');

        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('isHealthy')->andReturn(true);
        });

        Sanctum::actingAs($farmer);

        $scan = $this->postJson('/api/v1/disease-reports/scan', [
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'image' => UploadedFile::fake()->create('leaf.jpg', 300, 'image/jpeg'),
        ])->assertStatus(201)
            ->assertJsonPath('data.plot_id', $plot->id)
            ->assertJsonPath('data.crop_id', $crop->id)
            ->assertJsonPath('data.status', 'reviewing');

        Queue::assertPushed(ProcessDiseaseReportScan::class);

        $reportId = (int) $scan->json('data.id');

        DiseaseReport::query()->whereKey($reportId)->update([
            'disease_name' => 'potato_late_blight',
            'severity' => 'high',
            'confidence_score' => 0.96,
            'description' => 'Model detection completed.',
            'status' => 'reviewing',
        ]);

        $supporter = $this->createUserWithRole('Support Expert', '0911000062', 'supporter');
        $supporter->update(['region_id' => $farmer->region_id]);

        if (Schema::hasTable('user_region_scopes')) {
            \DB::table('user_region_scopes')->insertOrIgnore([
                'user_id' => $supporter->id,
                'region_id' => $farmer->region_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($supporter);

        $verifyResponse = $this->putJson("/api/v1/disease-reports/{$reportId}/verify", [
            'disease_name' => 'potato_late_blight',
            'severity' => 'high',
            'description' => 'Confirmed by supporter.',
            'confidence_score' => 0.96,
            'status' => 'confirmed',
            'decision_reason_code' => 'expert_confirmed',
            'decision_comment' => 'Field symptoms and severity are consistent.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.treatment_guidance.mode', 'treat')
            ->assertJsonPath('data.treatment_guidance.treatment_ready', true)
            ->assertJsonPath('data.treatment_guidance.risk_level', 'high');

        $this->assertDatabaseHas('alerts', [
            'disease_report_id' => $reportId,
            'status' => 'open',
            'severity' => 'high',
        ]);

        $this->assertIsArray($verifyResponse->json('data.treatment_guidance.actions'));
        $this->assertNotEmpty($verifyResponse->json('data.treatment_guidance.actions'));
        $this->assertIsString($verifyResponse->json('data.treatment_guidance.ppe'));
        $this->assertNotEmpty(trim((string) $verifyResponse->json('data.treatment_guidance.ppe')));
        $this->assertIsString($verifyResponse->json('data.treatment_guidance.active_ingredient'));
        $this->assertNotEmpty(trim((string) $verifyResponse->json('data.treatment_guidance.active_ingredient')));
        $this->assertIsString($verifyResponse->json('data.treatment_guidance.dosage'));
        $this->assertNotEmpty(trim((string) $verifyResponse->json('data.treatment_guidance.dosage')));
        $this->assertIsString($verifyResponse->json('data.treatment_guidance.pre_harvest_interval'));
        $this->assertNotEmpty(trim((string) $verifyResponse->json('data.treatment_guidance.pre_harvest_interval')));
        $this->assertIsString($verifyResponse->json('data.treatment_guidance.re_entry_interval'));
        $this->assertNotEmpty(trim((string) $verifyResponse->json('data.treatment_guidance.re_entry_interval')));
    }



    public function test_low_confidence_inference_marks_report_uncertain(): void
    {
        $farmer = $this->createFarmer('Low Confidence Farmer', '0911000071');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Low Confidence Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Low Confidence Plot',
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

        $report = DiseaseReport::create([
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'reported_by' => $farmer->id,
            'disease_name' => 'pending_analysis',
            'description' => null,
            'report_source' => 'ai',
            'confidence_score' => null,
            'severity' => 'low',
            'status' => 'reviewing',
            'reported_at' => now(),
        ]);

        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('analyze')->andReturn([
                'disease_name' => 'tomato_early_blight',
                'severity' => 'high',
                'confidence_score' => 0.52,
                'description' => 'Model detection completed.',
            ]);
        });

        config(['services.inference.min_confidence' => 0.75]);

        $job = new ProcessDiseaseReportScan($report->id, 'disease-reports/test.jpg');
        $job->handle(app(InferencePipelineService::class));

        $report->refresh();

        $this->assertSame('reviewing', $report->status);
        $this->assertSame('tomato_early_blight', $report->disease_name);
        $this->assertSame(0.52, round((float) $report->confidence_score, 2));
        $this->assertStringContainsString('marked uncertain', strtolower((string) $report->description));

        Sanctum::actingAs($farmer);
        $response = $this->getJson("/api/v1/disease-reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('data.treatment_guidance.mode', 'pending_review')
            ->assertJsonPath('data.treatment_guidance.treatment_ready', false)
            ->assertJsonPath('data.treatment_guidance.reliability', 'low');

        $this->assertNull($response->json('data.treatment_guidance.active_ingredient'));
        $this->assertNull($response->json('data.treatment_guidance.dosage'));
        $this->assertNull($response->json('data.treatment_guidance.ppe'));
        $this->assertNull($response->json('data.treatment_guidance.pre_harvest_interval'));
        $this->assertNull($response->json('data.treatment_guidance.re_entry_interval'));
    }

    public function test_scan_job_rechecks_strict_precheck_before_inference_call(): void
    {
        $farmer = $this->createFarmer('Runtime Precheck Farmer', '0911000072');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Runtime Precheck Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Runtime Precheck Plot',
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

        $report = DiseaseReport::create([
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'reported_by' => $farmer->id,
            'disease_name' => 'pending_analysis',
            'description' => null,
            'report_source' => 'ai',
            'confidence_score' => null,
            'severity' => 'low',
            'status' => 'reviewing',
            'reported_at' => now(),
        ]);

        config([
            'services.inference.enabled' => true,
            'services.inference.strict_precheck' => true,
        ]);

        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('isHealthy')->once()->andReturn(false);
            $mock->shouldNotReceive('analyze');
        });

        $job = new ProcessDiseaseReportScan($report->id, 'disease-reports/test.jpg');
        $job->handle(app(InferencePipelineService::class));

        $report->refresh();
        $this->assertSame('reviewing', $report->status);
        $this->assertStringContainsString('inference unavailable at processing time', strtolower((string) $report->description));
    }

    public function test_failed_inference_payload_is_sanitized_before_storage(): void
    {
        if (! Schema::hasTable('failed_inferences')) {
            $this->markTestSkipped('failed_inferences table is not available in this test database.');
        }

        $farmer = $this->createFarmer('Payload Sanitize Farmer', '0911000070');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Payload Sanitize Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Payload Sanitize Plot',
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

        $report = DiseaseReport::create([
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'reported_by' => $farmer->id,
            'disease_name' => 'pending_analysis',
            'description' => null,
            'report_source' => 'ai',
            'confidence_score' => null,
            'severity' => 'low',
            'status' => 'reviewing',
            'reported_at' => now(),
        ]);

        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('analyze')->andReturn([
                'ok' => false,
                'code' => 'PIPELINE_REJECTED',
                'message' => 'Model rejected this capture.',
                'model_version' => 'trained-model-v1',
                'image_base64' => str_repeat('a', 1200),
                'device_id' => 'android-123',
                'location' => ['lat' => 9.0, 'lon' => 38.0],
                'debug_blob' => str_repeat('x', 1300),
                'safe_hint' => 'use multi-angle capture',
            ]);
        });

        $job = new ProcessDiseaseReportScan($report->id, 'disease-reports/test.jpg');
        $job->handle(app(InferencePipelineService::class));

        $failed = FailedInference::query()->latest('id')->first();
        $this->assertNotNull($failed);
        $payload = (array) $failed->payload;
        $this->assertArrayNotHasKey('image_base64', $payload);
        $this->assertArrayNotHasKey('device_id', $payload);
        $this->assertArrayNotHasKey('location', $payload);
        $this->assertSame('use multi-angle capture', $payload['safe_hint'] ?? null);
        $this->assertStringContainsString('[truncated]', (string) ($payload['debug_blob'] ?? ''));
    }

    public function test_inference_health_requires_expected_runtime_contract_when_configured(): void
    {
        config([
            'services.inference.enabled' => true,
            'services.inference.base_url' => 'http://127.0.0.1:9010',
            'services.inference.health_endpoint' => '/health',
            'services.inference.expected_model_version' => 'trained-model-v1',
            'services.inference.expected_pixel_scale' => 'raw255',
            'services.inference.expected_labels_count' => 38,
        ]);

        Http::fake([
            'http://127.0.0.1:9010/health' => Http::response([
                'status' => 'ok',
                'model_version' => 'trained-model-v1',
                'pixel_scale' => 'normalized',
                'labels_count' => 38,
            ], 200),
        ]);

        $this->assertFalse(app(InferencePipelineService::class)->isHealthy());
    }

    public function test_health_endpoint_for_farmer_hides_sensitive_inference_config(): void
    {
        $farmer = $this->createFarmer('Health Farmer', '0911000077');
        Sanctum::actingAs($farmer);

        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('healthReport')->andReturn([
                'healthy' => false,
                'service_status' => 'down',
                'contract_ok' => false,
                'contract_messages' => ['runtime mismatch'],
                'runtime' => ['model_version' => 'x'],
                'errors' => ['network timeout'],
            ]);
        });

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('services.inference.healthy', false)
            ->assertJsonPath('services.inference.service_status', 'down')
            ->assertJsonMissingPath('services.inference.base_url')
            ->assertJsonMissingPath('services.inference.runtime')
            ->assertJsonMissingPath('services.inference.contract_messages');
    }

    public function test_health_endpoint_for_supporter_includes_detailed_inference_config(): void
    {
        $supporter = $this->createUserWithRole('Health Supporter', '0911000078', 'supporter');
        Sanctum::actingAs($supporter);

        config([
            'services.inference.base_url' => 'http://127.0.0.1:9010',
            'services.inference.endpoint' => '/predict',
            'services.inference.strict_precheck' => true,
            'services.inference.min_confidence' => 0.75,
        ]);

        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('healthReport')->andReturn([
                'healthy' => true,
                'service_status' => 'ok',
                'contract_ok' => false,
                'contract_messages' => ['pixel_scale mismatch'],
                'runtime' => ['pixel_scale' => 'normalized'],
                'errors' => [],
            ]);
        });

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('services.inference.base_url', 'http://127.0.0.1:9010')
            ->assertJsonPath('services.inference.endpoint', '/predict')
            ->assertJsonPath('services.inference.strict_precheck', true)
            ->assertJsonPath('services.inference.contract_ok', false)
            ->assertJsonPath('services.inference.runtime.pixel_scale', 'normalized');
    }

    public function test_scan_rejects_crop_not_registered_on_plot_when_active_plantings_exist(): void
    {
        config(['logging.default' => 'null']);
        $farmer = $this->createFarmer('Planting Scoped Farmer', '0911000072');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Scoped Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Scoped Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);

        $potato = Crop::firstOrCreate(
            ['name' => 'Potato'],
            [
                'crop_type' => 'vegetable',
                'is_active' => 1,
            ]
        );
        $tomato = Crop::firstOrCreate(
            ['name' => 'Tomato'],
            [
                'crop_type' => 'vegetable',
                'is_active' => 1,
            ]
        );

        Planting::create([
            'plot_id' => $plot->id,
            'crop_id' => $potato->id,
            'planting_date' => now()->toDateString(),
            'status' => 'active',
            'is_active' => 1,
        ]);

        Storage::fake('public');
        Queue::fake();
        config(['services.inference.enabled' => true]);
        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('isHealthy')->andReturn(true);
        });

        Sanctum::actingAs($farmer);

        $this->postJson('/api/v1/disease-reports/scan', [
            'plot_id' => $plot->id,
            'crop_id' => $tomato->id,
            'image' => UploadedFile::fake()->create('leaf.jpg', 300, 'image/jpeg'),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['crop_id']);

        Queue::assertNotPushed(ProcessDiseaseReportScan::class);
    }

    public function test_scan_idempotency_key_returns_existing_report_without_duplication(): void
    {
        if (! Schema::hasColumn('disease_reports', 'client_submission_id')) {
            $this->markTestSkipped('client_submission_id column is not available in this test database.');
        }

        $farmer = $this->createFarmer('Idempotency Farmer', '0911000076');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Idempotency Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Idempotency Plot',
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

        Queue::fake();
        Storage::fake('public');
        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('isHealthy')->andReturn(true);
        });

        Sanctum::actingAs($farmer);
        $idempotencyKey = 'scan-key-001';

        $first = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/disease-reports/scan', [
                'plot_id' => $plot->id,
                'crop_id' => $crop->id,
                'image' => UploadedFile::fake()->create('leaf-1.jpg', 300, 'image/jpeg'),
            ])->assertStatus(201);

        $second = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/disease-reports/scan', [
                'plot_id' => $plot->id,
                'crop_id' => $crop->id,
                'image' => UploadedFile::fake()->create('leaf-2.jpg', 300, 'image/jpeg'),
            ])->assertStatus(200);

        $firstId = (int) $first->json('data.id');
        $secondId = (int) $second->json('data.id');
        $this->assertSame($firstId, $secondId);
        $storedImages = Storage::disk('public')->files('disease-reports');
        $this->assertCount(1, $storedImages, 'Duplicate idempotency submissions should not leave extra image files.');

        $this->assertSame(
            1,
            DiseaseReport::query()
                ->where('reported_by', $farmer->id)
                ->where('client_submission_id', $idempotencyKey)
                ->count()
        );
    }

    public function test_scan_allows_submission_when_inference_unhealthy_if_strict_precheck_disabled(): void
    {
        $farmer = $this->createFarmer('Inference Relaxed Farmer', '0911000181');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Inference Relaxed Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Inference Relaxed Plot',
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

        Queue::fake();
        Storage::fake('public');

        config([
            'services.inference.enabled' => true,
            'services.inference.strict_precheck' => false,
        ]);

        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('isHealthy')->never();
        });

        Sanctum::actingAs($farmer);

        $this->postJson('/api/v1/disease-reports/scan', [
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'image' => UploadedFile::fake()->create('leaf.jpg', 300, 'image/jpeg'),
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'reviewing');

        Queue::assertPushed(ProcessDiseaseReportScan::class);
    }

    public function test_scan_accepts_optional_metadata_payload(): void
    {
        if (! Schema::hasColumn('disease_reports', 'scan_metadata')) {
            $this->markTestSkipped('scan_metadata column is not available in this test database.');
        }

        $farmer = $this->createFarmer('Metadata Farmer', '0911000075');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Metadata Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Metadata Plot',
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

        Queue::fake();
        Storage::fake('public');
        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('isHealthy')->andReturn(true);
        });

        Sanctum::actingAs($farmer);

        $scan = $this->postJson('/api/v1/disease-reports/scan', [
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'scan_metadata' => [
                'growth_stage' => 'vegetative',
                'symptom_days' => 4,
                'recent_rain' => true,
                'field_notes' => 'Spots started from lower canopy leaves.',
                'capture_shots' => 2,
                'capture_protocol' => 'guided_multi_leaf',
            ],
            'image' => UploadedFile::fake()->create('leaf.jpg', 300, 'image/jpeg'),
        ])->assertStatus(201)
            ->assertJsonPath('data.scan_metadata.growth_stage', 'vegetative')
            ->assertJsonPath('data.scan_metadata.capture_protocol', 'guided_multi_leaf')
            ->assertJsonPath('data.scan_metadata.capture_shots', 2)
            ->assertJsonPath('data.scan_metadata.expected_capture_shots', 3);

        $report = DiseaseReport::findOrFail((int) $scan->json('data.id'));
        $this->assertSame('vegetative', $report->scan_metadata['growth_stage'] ?? null);
        $this->assertSame(4, (int) ($report->scan_metadata['symptom_days'] ?? 0));
        $this->assertTrue((bool) ($report->scan_metadata['recent_rain'] ?? false));
        $this->assertSame(3, (int) ($report->scan_metadata['expected_capture_shots'] ?? 0));
    }

    public function test_scan_metadata_trends_endpoint_is_region_scoped_and_forbidden_for_farmers(): void
    {
        if (! Schema::hasColumn('disease_reports', 'scan_metadata')) {
            $this->markTestSkipped('scan_metadata column is not available in this test database.');
        }

        $inScopeFarmer = $this->createFarmer('Metadata In Scope Farmer', '0911000079');
        $outScopeFarmer = $this->createFarmer('Metadata Out Scope Farmer', '0911000080');

        $inScopeFarm = Farm::create([
            'farmer_id' => $inScopeFarmer->id,
            'region_id' => $inScopeFarmer->region_id,
            'farm_name' => 'Metadata In Scope Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);
        $outScopeFarm = Farm::create([
            'farmer_id' => $outScopeFarmer->id,
            'region_id' => $outScopeFarmer->region_id,
            'farm_name' => 'Metadata Out Scope Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $inScopePlot = Plot::create([
            'farm_id' => $inScopeFarm->id,
            'plot_name' => 'Metadata In Scope Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);
        $outScopePlot = Plot::create([
            'farm_id' => $outScopeFarm->id,
            'plot_name' => 'Metadata Out Scope Plot',
            'soil_type' => 'clay',
            'is_active' => 1,
        ]);

        $crop = Crop::firstOrCreate(
            ['name' => 'Wheat'],
            [
                'crop_type' => 'cereal',
                'is_active' => 1,
            ]
        );

        DiseaseReport::create([
            'plot_id' => $inScopePlot->id,
            'crop_id' => $crop->id,
            'reported_by' => $inScopeFarmer->id,
            'disease_name' => 'leaf_rust',
            'description' => 'In scope metadata report.',
            'report_source' => 'manual',
            'severity' => 'medium',
            'status' => 'new',
            'reported_at' => now(),
            'scan_metadata' => [
                'growth_stage' => 'vegetative',
                'symptom_days' => 3,
                'recent_rain' => true,
            ],
        ]);
        DiseaseReport::create([
            'plot_id' => $outScopePlot->id,
            'crop_id' => $crop->id,
            'reported_by' => $outScopeFarmer->id,
            'disease_name' => 'stem_rust',
            'description' => 'Out scope metadata report.',
            'report_source' => 'manual',
            'severity' => 'high',
            'status' => 'new',
            'reported_at' => now(),
            'scan_metadata' => [
                'growth_stage' => 'flowering',
                'symptom_days' => 7,
                'recent_rain' => false,
            ],
        ]);

        $supporter = $this->createUserWithRole('Metadata Scoped Supporter', '0911000084', 'supporter');
        $supporter->update(['region_id' => $inScopeFarmer->region_id]);

        if (Schema::hasTable('user_region_scopes')) {
            \DB::table('user_region_scopes')->insertOrIgnore([
                'user_id' => $supporter->id,
                'region_id' => $inScopeFarmer->region_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($supporter);
        $this->getJson('/api/v1/scan-metadata/trends?days=30')
            ->assertOk()
            ->assertJsonPath('data.totals.reports_with_metadata', 1)
            ->assertJsonPath('data.by_region.0.reports', 1)
            ->assertJsonPath('data.by_crop.0.reports', 1);

        Sanctum::actingAs($inScopeFarmer);
        $this->getJson('/api/v1/scan-metadata/trends?days=30')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden.',
            ]);
    }

    public function test_enforce_crop_scope_skips_unsupported_crop_family(): void
    {
        config(['logging.default' => 'null']);
        $farmer = $this->createFarmer('Unsupported Crop Farmer', '0911000073');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Unsupported Crop Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Unsupported Crop Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);

        $crop = Crop::firstOrCreate(
            ['name' => 'Teff'],
            [
                'crop_type' => 'cereal',
                'is_active' => 1,
            ]
        );

        $report = DiseaseReport::create([
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'reported_by' => $farmer->id,
            'disease_name' => 'pending_analysis',
            'description' => null,
            'report_source' => 'ai',
            'confidence_score' => null,
            'severity' => 'low',
            'status' => 'reviewing',
            'reported_at' => now(),
        ]);

        config([
            'services.inference.enforce_crop_scope' => true,
            'services.inference.supported_crop_families' => ['tomato', 'potato', 'pepper'],
        ]);

        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldNotReceive('analyze');
        });

        $job = new ProcessDiseaseReportScan($report->id, 'disease-reports/test.jpg');
        $job->handle(app(InferencePipelineService::class));

        $report->refresh();
        $this->assertSame('pending_analysis', $report->disease_name);
        $this->assertSame('reviewing', $report->status);
        $this->assertStringContainsString('not currently supported by ai model', strtolower((string) $report->description));
    }

    public function test_bell_pepper_crop_name_matches_pepper_predictions_without_mismatch_flag(): void
    {
        config(['logging.default' => 'null']);
        $farmer = $this->createFarmer('Pepper Alias Farmer', '0911000074');

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $farmer->region_id,
            'farm_name' => 'Pepper Alias Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $plot = Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Pepper Alias Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);

        $crop = Crop::firstOrCreate(
            ['name' => 'Bell Pepper'],
            [
                'crop_type' => 'vegetable',
                'is_active' => 1,
            ]
        );

        $report = DiseaseReport::create([
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'reported_by' => $farmer->id,
            'disease_name' => 'pending_analysis',
            'description' => null,
            'report_source' => 'ai',
            'confidence_score' => null,
            'severity' => 'low',
            'status' => 'reviewing',
            'reported_at' => now(),
        ]);

        config([
            'services.inference.enforce_crop_scope' => true,
            'services.inference.supported_crop_families' => ['pepper', 'tomato'],
            'services.inference.min_confidence' => 0.75,
            'services.inference.review_only_mode' => false,
        ]);

        $this->mock(InferencePipelineService::class, function ($mock): void {
            $mock->shouldReceive('analyze')->andReturn([
                'disease_name' => 'pepper_bacterial_spot',
                'severity' => 'high',
                'confidence_score' => 0.94,
                'description' => 'Model detection completed.',
                'model_version' => 'trained-model-v1',
            ]);
        });

        $job = new ProcessDiseaseReportScan($report->id, 'disease-reports/test.jpg');
        $job->handle(app(InferencePipelineService::class));

        $report->refresh();

        $this->assertSame('pepper_bacterial_spot', $report->disease_name);
        $this->assertSame('reviewing', $report->status);
        $this->assertStringNotContainsString('does not match selected crop', strtolower((string) $report->description));
    }

    public function test_supporter_disease_report_index_is_region_scoped(): void
    {
        if (! Schema::hasTable('disease_reports')) {
            $this->markTestSkipped('Disease reports table is not available in this test database.');
        }

        $inScopeFarmer = $this->createFarmer('In Scope Farmer', '0911000081');
        $outScopeFarmer = $this->createFarmer('Out Scope Farmer', '0911000082');

        $inScopeFarm = Farm::create([
            'farmer_id' => $inScopeFarmer->id,
            'region_id' => $inScopeFarmer->region_id,
            'farm_name' => 'In Scope Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);
        $outScopeFarm = Farm::create([
            'farmer_id' => $outScopeFarmer->id,
            'region_id' => $outScopeFarmer->region_id,
            'farm_name' => 'Out Scope Farm',
            'farm_type' => 'crop',
            'is_active' => 1,
        ]);

        $inScopePlot = Plot::create([
            'farm_id' => $inScopeFarm->id,
            'plot_name' => 'In Scope Plot',
            'soil_type' => 'loam',
            'is_active' => 1,
        ]);
        $outScopePlot = Plot::create([
            'farm_id' => $outScopeFarm->id,
            'plot_name' => 'Out Scope Plot',
            'soil_type' => 'clay',
            'is_active' => 1,
        ]);

        $crop = Crop::firstOrCreate(
            ['name' => 'Barley'],
            [
                'crop_type' => 'cereal',
                'is_active' => 1,
            ]
        );

        $inScopeReport = DiseaseReport::create([
            'plot_id' => $inScopePlot->id,
            'crop_id' => $crop->id,
            'reported_by' => $inScopeFarmer->id,
            'disease_name' => 'leaf_rust',
            'description' => 'In-scope report.',
            'report_source' => 'manual',
            'severity' => 'medium',
            'status' => 'new',
            'reported_at' => now(),
        ]);
        $outScopeReport = DiseaseReport::create([
            'plot_id' => $outScopePlot->id,
            'crop_id' => $crop->id,
            'reported_by' => $outScopeFarmer->id,
            'disease_name' => 'stem_rust',
            'description' => 'Out-of-scope report.',
            'report_source' => 'manual',
            'severity' => 'high',
            'status' => 'new',
            'reported_at' => now(),
        ]);

        $supporter = $this->createUserWithRole('Scoped Supporter', '0911000083', 'supporter');
        $supporter->update(['region_id' => $inScopeFarmer->region_id]);

        if (Schema::hasTable('user_region_scopes')) {
            \DB::table('user_region_scopes')->insertOrIgnore([
                'user_id' => $supporter->id,
                'region_id' => $inScopeFarmer->region_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($supporter);

        $response = $this->getJson('/api/v1/disease-reports')
            ->assertOk();

        $reportIds = collect($response->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains($inScopeReport->id, $reportIds);
        $this->assertNotContains($outScopeReport->id, $reportIds);
    }

    public function test_auth_login_is_rate_limited(): void
    {
        $farmer = $this->createFarmer('Rate Farmer', '0911999911');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'phone' => $farmer->phone,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'phone' => $farmer->phone,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_auth_login_with_duplicate_phone_uses_account_matching_password(): void
    {
        $sharedPhone = '0911999912';
        $first = $this->createFarmer('Duplicate Phone A', '0911999913');
        $second = $this->createFarmer('Duplicate Phone B', '0911999914');

        $first->update([
            'phone' => $sharedPhone,
            'password' => bcrypt('password-one'),
        ]);
        $second->update([
            'phone' => $sharedPhone,
            'password' => bcrypt('password-two'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'phone' => $sharedPhone,
            'password' => 'password-two',
        ])->assertOk()
            ->assertJsonPath('user.id', $second->id);
    }

    public function test_auth_login_issues_token_with_expiry_and_expired_token_is_rejected(): void
    {
        $farmer = $this->createFarmer('TTL Farmer', '0911999933');
        config(['services.mobile_auth.ttl_minutes' => 5]);

        $login = $this->postJson('/api/v1/auth/login', [
            'phone' => $farmer->phone,
            'password' => 'password123',
        ])->assertOk();

        $plainToken = (string) $login->json('token');
        $token = PersonalAccessToken::findToken($plainToken);
        $this->assertNotNull($token, 'Expected login token to be persisted.');
        $this->assertNotNull($token?->expires_at, 'Expected mobile token to include expires_at.');

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/v1/health')
            ->assertOk();

        $token?->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/v1/health')
            ->assertStatus(401);
    }

    public function test_auth_login_rejects_duplicate_phone_when_password_match_is_ambiguous(): void
    {
        $sharedPhone = '0911999920';
        $first = $this->createFarmer('Ambiguous Phone A', '0911999921');
        $second = $this->createFarmer('Ambiguous Phone B', '0911999922');

        $sharedPasswordHash = bcrypt('shared-password');

        $first->update([
            'phone' => $sharedPhone,
            'password' => $sharedPasswordHash,
        ]);
        $second->update([
            'phone' => $sharedPhone,
            'password' => $sharedPasswordHash,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'phone' => $sharedPhone,
            'password' => 'shared-password',
        ])->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials.',
            ]);
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
            'name' => 'Region '.uniqid(),
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

