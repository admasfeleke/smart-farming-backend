<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Central Ethiopia Administrative + Agriculture Bureaucracy Model
    |--------------------------------------------------------------------------
    |
    | Internal roles remain intentionally small: super_admin, admin, supporter,
    | expert, farmer. Real public-sector titles are derived from role + level.
    | This keeps authorization stable while allowing the displayed office terms
    | to match Ethiopia's agricultural extension structure.
    |
    */

    'region_id' => 1005,

    'levels' => [
        'region' => [
            'label' => 'Central Ethiopia Regional State',
            'agriculture_office' => 'Regional Agriculture Bureau',
            'default_admin_title' => 'Regional Agriculture Bureau Administrator',
            'recommended_users' => [
                'admins' => 1,
                'experts' => '2-5',
                'supporters' => 0,
            ],
        ],
        'zone' => [
            'label' => 'Zone',
            'agriculture_office' => 'Zonal Agriculture Office',
            'default_admin_title' => 'Zonal Agriculture Office Coordinator',
            'recommended_users' => [
                'admins' => 1,
                'experts' => '2-4',
                'supporters' => '0-2',
            ],
        ],
        'special_woreda' => [
            'label' => 'Special Woreda',
            'agriculture_office' => 'Special Woreda Agriculture Office',
            'default_admin_title' => 'Special Woreda Agriculture Office Coordinator',
            'authority_level' => 'woreda',
            'recommended_users' => [
                'admins' => 1,
                'experts' => '1-3',
                'supporters' => '1-3',
            ],
        ],
        'woreda' => [
            'label' => 'Woreda',
            'agriculture_office' => 'Woreda Agriculture Office',
            'default_admin_title' => 'Woreda Agriculture Office Coordinator',
            'recommended_users' => [
                'admins' => 1,
                'experts' => '2-5',
                'supporters' => '2-5',
            ],
        ],
        'kebele' => [
            'label' => 'Kebele',
            'agriculture_office' => 'Kebele Extension Service',
            'default_supporter_title' => 'Development Agent',
            'recommended_users' => [
                'admins' => 0,
                'experts' => 0,
                'supporters' => '1-3',
            ],
        ],
        'ftc' => [
            'label' => 'Farmer Training Center',
            'agriculture_office' => 'Farmer Training Center',
            'default_supporter_title' => 'Development Agent',
            'authority_level' => 'kebele',
            'recommended_users' => [
                'admins' => 0,
                'experts' => 0,
                'supporters' => '1-3',
            ],
        ],
    ],

    'role_titles' => [
        'super_admin' => [
            'national' => 'System Super Administrator',
        ],
        'admin' => [
            'region' => 'Regional Agriculture Bureau Administrator',
            'zone' => 'Zonal Agriculture Office Coordinator',
            'special_woreda' => 'Special Woreda Agriculture Office Coordinator',
            'woreda' => 'Woreda Agriculture Office Coordinator',
            'kebele' => 'Kebele Extension Coordinator',
            'ftc' => 'FTC Extension Coordinator',
        ],
        'supporter' => [
            'region' => 'Regional Extension Support Officer',
            'zone' => 'Zonal Extension Support Officer',
            'special_woreda' => 'Special Woreda Development Agent Supervisor',
            'woreda' => 'Woreda Extension Supervisor',
            'kebele' => 'Development Agent',
            'ftc' => 'FTC Development Agent',
        ],
        'expert' => [
            'region' => 'Regional Subject Matter Specialist',
            'zone' => 'Zonal Subject Matter Specialist',
            'special_woreda' => 'Special Woreda Subject Matter Specialist',
            'woreda' => 'Woreda Subject Matter Specialist',
            'kebele' => 'Field Technical Expert',
            'ftc' => 'FTC Technical Expert',
        ],
        'farmer' => [
            'own_farm' => 'Farmer',
        ],
    ],

    'technical_domains' => [
        'crop_protection' => 'Crop Protection Specialist',
        'soil_health' => 'Soil Health Specialist',
        'agronomy' => 'Agronomy Specialist',
        'weather_risk' => 'Climate and Weather Risk Officer',
        'extension' => 'Extension Service Officer',
    ],
];
