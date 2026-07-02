<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'description' => 'Platform governance and configuration administrator'],
            ['name' => 'admin', 'description' => 'Agriculture office coordinator within assigned administrative scope'],
            ['name' => 'supporter', 'description' => 'Development agent or extension worker for first-line farmer support'],
            ['name' => 'expert', 'description' => 'Subject matter specialist for crop, soil, weather, and treatment decisions'],
            ['name' => 'farmer', 'description' => 'Farmer mobile user operating own farm records'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}
