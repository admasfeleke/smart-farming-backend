<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'description' => 'Platform super administrator'],
            ['name' => 'admin', 'description' => 'System administrator'],
            ['name' => 'supporter', 'description' => 'Regional supporter / verifier'],
            ['name' => 'expert', 'description' => 'Agronomy expert'],
            ['name' => 'farmer', 'description' => 'Farmer mobile user'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}
