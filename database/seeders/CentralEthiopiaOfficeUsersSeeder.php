<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CentralEthiopiaOfficeUsersSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()
            ->whereIn('name', ['admin', 'supporter', 'expert'])
            ->pluck('id', 'name');

        $users = [
            [
                'name' => 'Central Ethiopia Regional Agriculture Admin',
                'email' => 'central.region.admin@test.local',
                'phone' => '0912001001',
                'role' => 'admin',
                'region_id' => 1005,
                'admin_level' => 'region',
            ],
            [
                'name' => 'Silte Zone Agriculture Coordinator',
                'email' => 'silte.zone.admin@test.local',
                'phone' => '0912002001',
                'role' => 'admin',
                'region_id' => 2003,
                'admin_level' => 'zone',
            ],
            [
                'name' => 'Dalocha Woreda Agriculture Coordinator',
                'email' => 'dalocha.woreda.admin@test.local',
                'phone' => '0912003001',
                'role' => 'admin',
                'region_id' => 3001,
                'admin_level' => 'woreda',
            ],
            [
                'name' => 'Dalocha Development Agent',
                'email' => 'dalocha.da@test.local',
                'phone' => '0912004001',
                'role' => 'supporter',
                'region_id' => 4003,
                'admin_level' => 'kebele',
            ],
            [
                'name' => 'Dalocha Crop Protection Specialist',
                'email' => 'dalocha.crop.expert@test.local',
                'phone' => '0912005001',
                'role' => 'expert',
                'region_id' => 3001,
                'admin_level' => 'woreda',
            ],
            [
                'name' => 'Silte Zone Soil Health Specialist',
                'email' => 'silte.soil.expert@test.local',
                'phone' => '0912006001',
                'role' => 'expert',
                'region_id' => 2003,
                'admin_level' => 'zone',
            ],
        ];

        foreach ($users as $data) {
            $roleId = $roles[$data['role']] ?? null;
            if (! $roleId) {
                continue;
            }

            $user = User::query()->firstOrNew(['email' => $data['email']]);
            $isNew = ! $user->exists;

            $user->forceFill([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'role_id' => $roleId,
                'region_id' => $data['region_id'],
                'admin_level' => $data['admin_level'],
                'is_active' => true,
            ]);

            if ($isNew) {
                $user->password = Hash::make('Password123!');
            }

            $user->save();
        }
    }
}
