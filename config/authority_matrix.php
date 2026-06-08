<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ethiopia-Oriented Role + Level Action Matrix
    |--------------------------------------------------------------------------
    |
    | Levels:
    | - national, region, zone, woreda, kebele
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
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'farm.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
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
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'plot.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
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
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'planting.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
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
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'disease_report.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'disease_report.create' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'disease_report.verify' => [
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
        ],

        'alert.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'alert.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'alert.create' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
        ],
        'alert.update' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'alert.delete' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
        ],

        'soil_health.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'soil_health.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'soil_health.create' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'soil_health.update' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'soil_health.delete' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
            'farmer' => ['*'],
        ],
        'soil_health.verify' => [
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
        ],

        'case_assignment.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
        ],
        'case_assignment.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
        ],
        'case_assignment.update' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
            'supporter' => ['region', 'zone', 'woreda', 'kebele'],
            'expert' => ['region', 'zone', 'woreda', 'kebele'],
        ],

        'case_audit_log.view_any' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
        ],
        'case_audit_log.view' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
        ],

        'delegation.manage' => [
            'super_admin' => ['national'],
            'admin' => ['region', 'zone', 'woreda', 'kebele'],
        ],
    ],
];
