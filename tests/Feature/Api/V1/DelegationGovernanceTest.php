<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Pages\AlertsOverview;
use App\Filament\Pages\AuthorizationMatrix;
use App\Filament\Pages\CaseAuditLogsOverview;
use App\Filament\Pages\DelegationOverview;
use App\Filament\Pages\DiseaseReportsOverview;
use App\Filament\Pages\FarmersOverview;
use App\Filament\Pages\FarmsOverview;
use App\Filament\Pages\MyAssignedCases;
use App\Filament\Pages\SlaBreachesOverview;
use App\Filament\Resources\Regions\RegionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DelegationGovernanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_role_based_navigation_matrix_is_enforced(): void
    {
        // Set Filament panel for testing
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $region = $this->createRegionTreeRoot('Central');

        $superAdmin = $this->createUserWithRole('super_admin', $region->id, 'national');
        $admin = $this->createUserWithRole('admin', $region->id, 'region');
        $supporter = $this->createUserWithRole('supporter', $region->id, 'region');
        $expert = $this->createUserWithRole('expert', $region->id, 'region');
        $farmer = $this->createUserWithRole('farmer', $region->id, null);

        $this->be($superAdmin);
        $this->assertTrue(AuthorizationMatrix::shouldRegisterNavigation());
        $this->assertTrue(RoleResource::shouldRegisterNavigation());
        $this->assertTrue(RegionResource::shouldRegisterNavigation());

        $this->be($admin);
        $this->assertFalse(DelegationOverview::shouldRegisterNavigation());
        $this->assertFalse(UserResource::shouldRegisterNavigation());
        $this->assertFalse(UserResource::canCreate());
        $this->assertFalse(RoleResource::shouldRegisterNavigation());
        $this->assertFalse(RegionResource::shouldRegisterNavigation());

        $this->be($supporter);
        $this->assertTrue(DiseaseReportsOverview::shouldRegisterNavigation());
        $this->assertTrue(AlertsOverview::shouldRegisterNavigation());
        $this->assertTrue(MyAssignedCases::shouldRegisterNavigation());
        $this->assertFalse(AuthorizationMatrix::shouldRegisterNavigation());
        $this->assertFalse(SlaBreachesOverview::shouldRegisterNavigation());
        $this->assertFalse(CaseAuditLogsOverview::shouldRegisterNavigation());

        $this->be($expert);
        $this->assertTrue(FarmersOverview::shouldRegisterNavigation());
        $this->assertTrue(FarmsOverview::shouldRegisterNavigation());

        $this->be($farmer);
        $panel = Filament::getCurrentPanel() ?? Filament::getDefaultPanel();
        $this->assertFalse($farmer->canAccessPanel($panel));
        $this->assertFalse(DiseaseReportsOverview::shouldRegisterNavigation());
        $this->assertFalse(UserResource::shouldRegisterNavigation());
    }

    public function test_last_super_admin_cannot_be_demoted_or_deactivated(): void
    {
        $region = $this->createRegionTreeRoot('South');
        $superAdmin = $this->createUserWithRole('super_admin', $region->id, 'national');
        $this->be($superAdmin);

        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');

        $this->expectException(ValidationException::class);
        UserResource::guardDelegation([
            'role_id' => $adminRoleId,
            'is_active' => true,
            'region_id' => $region->id,
            'admin_level' => 'region',
        ], $superAdmin);
    }

    public function test_scoped_regions_must_belong_to_primary_region_subtree(): void
    {
        $regionA = $this->createRegionTreeRoot('Region A');
        Region::create([
            'name' => 'Zone A1 '.uniqid(),
            'level' => 'zone',
            'parent_id' => $regionA->id,
            'is_active' => 1,
        ]);

        $regionB = $this->createRegionTreeRoot('Region B');
        $zoneB = Region::create([
            'name' => 'Zone B1 '.uniqid(),
            'level' => 'zone',
            'parent_id' => $regionB->id,
            'is_active' => 1,
        ]);

        $superAdmin = $this->createUserWithRole('super_admin', $regionA->id, 'national');
        $this->be($superAdmin);

        $supporterRoleId = (int) Role::query()->where('name', 'supporter')->value('id');

        $this->expectException(ValidationException::class);
        UserResource::guardDelegation([
            'role_id' => $supporterRoleId,
            'region_id' => $regionA->id,
            'admin_level' => 'region',
            'scopedRegions' => [$zoneB->id],
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(string $roleName, int $regionId, ?string $adminLevel): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => ucfirst($roleName).' role']
        );

        $payload = [
            'role_id' => $role->id,
            'region_id' => $regionId,
            'name' => strtoupper($roleName).' '.uniqid(),
            'phone' => '09'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'email' => uniqid($roleName).'@test.local',
            'password' => bcrypt('password123'),
            'is_active' => 1,
        ];

        if (Schema::hasColumn('users', 'admin_level')) {
            $payload['admin_level'] = $adminLevel;
        }

        return User::query()->create($payload);
    }

    public function test_primary_region_level_must_match_selected_admin_level(): void
    {
        $region = $this->createRegionTreeRoot('Central');
        $zone = Region::create([
            'name' => 'Zone C1 '.uniqid(),
            'level' => 'zone',
            'parent_id' => $region->id,
            'is_active' => 1,
        ]);

        $superAdmin = $this->createUserWithRole('super_admin', $region->id, 'national');
        $this->be($superAdmin);

        $supporterRoleId = (int) Role::query()->where('name', 'supporter')->value('id');

        $this->expectException(ValidationException::class);
        UserResource::guardDelegation([
            'role_id' => $supporterRoleId,
            'region_id' => $zone->id,
            'admin_level' => 'region',
            'is_active' => true,
        ]);
    }

    private function createRegionTreeRoot(string $name): Region
    {
        return Region::query()->create([
            'name' => $name.' '.uniqid(),
            'level' => 'region',
            'is_active' => 1,
        ]);
    }
}
