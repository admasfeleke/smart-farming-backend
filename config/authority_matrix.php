<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ethiopia-Oriented Role + Level Action Matrix
    |--------------------------------------------------------------------------
    |
    | Levels:
    | - national, region, zone, special_woreda, woreda, kebele, ftc
    |
    | Rules:
    | - super_admin is national governance.
    | - admin operates within delegated subtree at region/zone/woreda/kebele.
    | - supporter/expert operate operational queues, typically at regional and below.
    | - farmer is operational mobile user (farm-owned records only).
    |
    */
    'actions' => [
        'farm.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'farm.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'farm.create' => [
            'farmer' => ['*'],
        ],
        'farm.update' => [
            'farmer' => ['*'],
        ],
        'farm.delete' => [
            'farmer' => ['*'],
        ],

        'plot.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'plot.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'plot.create' => [
            'farmer' => ['*'],
        ],
        'plot.update' => [
            'farmer' => ['*'],
        ],
        'plot.delete' => [
            'farmer' => ['*'],
        ],

        'planting.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'planting.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'planting.create' => [
            'farmer' => ['*'],
        ],
        'planting.update' => [
            'farmer' => ['*'],
        ],
        'planting.delete' => [
            'farmer' => ['*'],
        ],

        'disease_report.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'disease_report.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'disease_report.create' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'disease_report.verify' => [
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],

        'alert.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'alert.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'alert.create' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],
        'alert.update' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'alert.delete' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],

        'soil_health.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'soil_health.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'soil_health.create' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'soil_health.update' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'soil_health.delete' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'farmer' => ['*'],
        ],
        'soil_health.verify' => [
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],

        'case_assignment.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],
        'case_assignment.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],
        'case_assignment.update' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'supporter' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
            'expert' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],

        'case_audit_log.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],
        'case_audit_log.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],

        'delegation.manage' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc'],
        ],
    ],
];

