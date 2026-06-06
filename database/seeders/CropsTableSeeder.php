<?php

namespace Database\Seeders;

use App\Models\Crop;
use Illuminate\Database\Seeder;

class CropsTableSeeder extends Seeder
{
    /**
     * Seed the crops table with common crops.
     */
    public function run(): void
    {
        $crops = [
            ['name' => 'Teff', 'scientific_name' => 'Eragrostis tef', 'crop_type' => 'cereal'],
            ['name' => 'Wheat', 'scientific_name' => 'Triticum aestivum', 'crop_type' => 'cereal'],
            ['name' => 'Maize', 'scientific_name' => 'Zea mays', 'crop_type' => 'cereal'],
            ['name' => 'Barley', 'scientific_name' => 'Hordeum vulgare', 'crop_type' => 'cereal'],
            ['name' => 'Sorghum', 'scientific_name' => 'Sorghum bicolor', 'crop_type' => 'cereal'],
            ['name' => 'Oats', 'scientific_name' => 'Avena sativa', 'crop_type' => 'cereal'],
            ['name' => 'Rice', 'scientific_name' => 'Oryza sativa', 'crop_type' => 'cereal'],

            ['name' => 'Chickpea', 'scientific_name' => 'Cicer arietinum', 'crop_type' => 'legume'],
            ['name' => 'Lentil', 'scientific_name' => 'Lens culinaris', 'crop_type' => 'legume'],
            ['name' => 'Faba Bean', 'scientific_name' => 'Vicia faba', 'crop_type' => 'legume'],
            ['name' => 'Haricot Bean', 'scientific_name' => 'Phaseolus vulgaris', 'crop_type' => 'legume'],
            ['name' => 'Soybean', 'scientific_name' => 'Glycine max', 'crop_type' => 'legume'],
            ['name' => 'Pea', 'scientific_name' => 'Pisum sativum', 'crop_type' => 'legume'],

            ['name' => 'Potato', 'scientific_name' => 'Solanum tuberosum', 'crop_type' => 'vegetable'],
            ['name' => 'Tomato', 'scientific_name' => 'Solanum lycopersicum', 'crop_type' => 'vegetable'],
            ['name' => 'Onion', 'scientific_name' => 'Allium cepa', 'crop_type' => 'vegetable'],
            ['name' => 'Cabbage', 'scientific_name' => 'Brassica oleracea', 'crop_type' => 'vegetable'],
            ['name' => 'Carrot', 'scientific_name' => 'Daucus carota', 'crop_type' => 'vegetable'],
            ['name' => 'Pepper', 'scientific_name' => 'Capsicum annuum', 'crop_type' => 'vegetable'],

            ['name' => 'Banana', 'scientific_name' => 'Musa spp.', 'crop_type' => 'fruit'],
            ['name' => 'Mango', 'scientific_name' => 'Mangifera indica', 'crop_type' => 'fruit'],
            ['name' => 'Avocado', 'scientific_name' => 'Persea americana', 'crop_type' => 'fruit'],
            ['name' => 'Orange', 'scientific_name' => 'Citrus sinensis', 'crop_type' => 'fruit'],
            ['name' => 'Papaya', 'scientific_name' => 'Carica papaya', 'crop_type' => 'fruit'],

            ['name' => 'Coffee', 'scientific_name' => 'Coffea arabica', 'crop_type' => 'cash_crop'],
            ['name' => 'Sesame', 'scientific_name' => 'Sesamum indicum', 'crop_type' => 'cash_crop'],
            ['name' => 'Cotton', 'scientific_name' => 'Gossypium spp.', 'crop_type' => 'cash_crop'],
            ['name' => 'Sugarcane', 'scientific_name' => 'Saccharum officinarum', 'crop_type' => 'cash_crop'],
            ['name' => 'Khat', 'scientific_name' => 'Catha edulis', 'crop_type' => 'cash_crop'],
        ];

        foreach ($crops as $crop) {
            Crop::updateOrCreate(
                ['name' => $crop['name']],
                [
                    'scientific_name' => $crop['scientific_name'],
                    'crop_type' => $crop['crop_type'],
                    'is_active' => 1,
                ]
            );
        }
    }
}
