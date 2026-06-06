<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['id' => 1001, 'name' => 'Addis Ababa City Administration', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1002, 'name' => 'Afar', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1003, 'name' => 'Amhara', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1004, 'name' => 'Benishangul-Gumuz', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1005, 'name' => 'Central Ethiopia', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1006, 'name' => 'Dire Dawa City Administration', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1007, 'name' => 'Gambella', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1008, 'name' => 'Harari', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1009, 'name' => 'Oromia', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1010, 'name' => 'Sidama', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1011, 'name' => 'Somali', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1012, 'name' => 'South Ethiopia', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1013, 'name' => 'South West Ethiopia Peoples', 'parent_id' => null, 'level' => 'region'],
            ['id' => 1014, 'name' => 'Tigray', 'parent_id' => null, 'level' => 'region'],
            ['id' => 2001, 'name' => 'Gurage Zone', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 2002, 'name' => 'East Gurage Zone', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 2003, 'name' => 'Silte Zone', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 2004, 'name' => 'Hadiya Zone', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 2005, 'name' => 'Halaba Zone', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 2006, 'name' => 'Kembata Zone', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 2007, 'name' => 'Yem Zone', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 2008, 'name' => 'Kebena Special Woreda', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 2009, 'name' => 'Mareko Special Woreda', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 2010, 'name' => 'Tembaro Special Woreda', 'parent_id' => 1005, 'level' => 'zone'],
            ['id' => 3001, 'name' => 'Dalocha Woreda', 'parent_id' => 2003, 'level' => 'woreda'],
            ['id' => 3002, 'name' => 'Silti Woreda', 'parent_id' => 2003, 'level' => 'woreda'],
            ['id' => 3003, 'name' => 'Lanfuro Woreda', 'parent_id' => 2003, 'level' => 'woreda'],
            ['id' => 3004, 'name' => 'Sankurra Woreda', 'parent_id' => 2003, 'level' => 'woreda'],
            ['id' => 3005, 'name' => 'Wulbareg Woreda', 'parent_id' => 2003, 'level' => 'woreda'],
            ['id' => 3006, 'name' => 'Alicho Werero Woreda', 'parent_id' => 2003, 'level' => 'woreda'],
            ['id' => 3007, 'name' => 'Misrak Azernet Berbere Woreda', 'parent_id' => 2003, 'level' => 'woreda'],
            ['id' => 4001, 'name' => 'Dalocha Town', 'parent_id' => 3001, 'level' => 'kebele'],
            ['id' => 4002, 'name' => 'Kebele not listed', 'parent_id' => 3001, 'level' => 'kebele'],
        ];

        foreach ($regions as $region) {
            $model = Region::query()->find($region['id']) ?? new Region();
            $model->forceFill([
                'id' => $region['id'],
                'name' => $region['name'],
                'parent_id' => $region['parent_id'],
                'level' => $region['level'],
                'is_active' => 1,
            ])->save();
        }
    }
}
