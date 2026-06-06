<x-filament-panels::page>
    <style>
        /* Bootstrap-like treatment for dashboard stats cards */
        .fi-wi-stats-overview {
            display: grid;
            gap: 1rem;
        }

        .fi-wi-stats-overview-stat {
            border: 1px solid #dee2e6;
            border-top: 4px solid #6c757d;
            border-radius: 0.75rem;
            background: #ffffff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            padding: 1rem;
        }

        .fi-wi-stats-overview-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.14);
        }

        .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-label {
            color: #495057;
            font-weight: 600;
        }

        .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-value {
            color: #212529;
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-description {
            color: #6c757d;
        }

        .fi-wi-stats-overview-stat.kpi-farmers {
            border-color: #ffecb5;
            background: #fff3cd;
            border-top-color: #ffc107;
        }

        .fi-wi-stats-overview-stat.kpi-active-farms,
        .fi-wi-stats-overview-stat.kpi-active-farms-scope {
            border-color: #badbcc;
            background: #d1e7dd;
            border-top-color: #198754;
        }

        .fi-wi-stats-overview-stat.kpi-disease-reports,
        .fi-wi-stats-overview-stat.kpi-reviewing-reports,
        .fi-wi-stats-overview-stat.kpi-review-queue,
        .fi-wi-stats-overview-stat.kpi-pending-verification {
            border-color: #cfe2ff;
            background: #e7f1ff;
            border-top-color: #0d6efd;
        }

        .fi-wi-stats-overview-stat.kpi-high-alerts,
        .fi-wi-stats-overview-stat.kpi-critical-open-alerts,
        .fi-wi-stats-overview-stat.kpi-open-high-alerts,
        .fi-wi-stats-overview-stat.kpi-open-critical-alerts,
        .fi-wi-stats-overview-stat.kpi-overdue-assignments {
            border-color: #f5c2c7;
            background: #f8d7da;
            border-top-color: #dc3545;
        }

        .fi-wi-stats-overview-stat.kpi-admin-level,
        .fi-wi-stats-overview-stat.kpi-my-active-assignments {
            border-color: #d7d9ff;
            background: #ecebff;
            border-top-color: #6366f1;
        }
    </style>

    {{ $this->content }}
</x-filament-panels::page>
