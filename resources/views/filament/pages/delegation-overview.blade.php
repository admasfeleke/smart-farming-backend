<x-filament-panels::page>
    <style>
        .delegation-kpi-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .delegation-kpi-card {
            --card-border: #d1d5db;
            --card-bg: #ffffff;
            --card-title: #6b7280;
            --card-value: #111827;
            --card-icon: #6b7280;
            --card-accent: #9ca3af;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.08);
            padding: 1rem;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            border-top: 4px solid var(--card-accent);
        }
        .delegation-kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.14);
        }
        .delegation-kpi-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .delegation-kpi-title {
            font-size: 0.875rem;
            color: var(--card-title, #475569);
        }
        .delegation-kpi-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--card-value, #0f172a);
            line-height: 1.1;
        }
        .delegation-kpi-icon {
            width: 1.25rem;
            height: 1.25rem;
            color: var(--card-icon);
            flex-shrink: 0;
        }
        .kpi-primary {
            --card-border: #cfe2ff;
            --card-bg: #e7f1ff;
            --card-title: #084298;
            --card-value: #052c65;
            --card-icon: #0d6efd;
            --card-accent: #0d6efd;
        }
        .kpi-success {
            --card-border: #badbcc;
            --card-bg: #d1e7dd;
            --card-title: #0f5132;
            --card-value: #0a3622;
            --card-icon: #198754;
            --card-accent: #198754;
        }
        .kpi-info {
            --card-border: #b6effb;
            --card-bg: #cff4fc;
            --card-title: #055160;
            --card-value: #03363d;
            --card-icon: #0dcaf0;
            --card-accent: #0dcaf0;
        }
        .kpi-warning {
            --card-border: #ffecb5;
            --card-bg: #fff3cd;
            --card-title: #664d03;
            --card-value: #4a3702;
            --card-icon: #ffc107;
            --card-accent: #ffc107;
        }
        .kpi-danger {
            --card-border: #f5c2c7;
            --card-bg: #f8d7da;
            --card-title: #842029;
            --card-value: #58151c;
            --card-icon: #dc3545;
            --card-accent: #dc3545;
        }
        .kpi-indigo {
            --card-border: #d7d9ff;
            --card-bg: #ecebff;
            --card-title: #312e81;
            --card-value: #1e1b4b;
            --card-icon: #6366f1;
            --card-accent: #6366f1;
        }
    </style>

    <div class="delegation-kpi-grid">
        <div class="delegation-kpi-card kpi-primary">
            <div class="delegation-kpi-head">
                <p class="delegation-kpi-title">Total Delegated Users</p>
                <x-filament::icon icon="heroicon-o-users" class="delegation-kpi-icon" />
            </div>
            <p class="delegation-kpi-value">{{ $summary['total_users'] ?? 0 }}</p>
        </div>

        <div class="delegation-kpi-card kpi-success">
            <div class="delegation-kpi-head">
                <p class="delegation-kpi-title">Active Users</p>
                <x-filament::icon icon="heroicon-o-check-badge" class="delegation-kpi-icon" />
            </div>
            <p class="delegation-kpi-value">{{ $summary['active_users'] ?? 0 }}</p>
        </div>

        <div class="delegation-kpi-card kpi-info">
            <div class="delegation-kpi-head">
                <p class="delegation-kpi-title">Backoffice Users</p>
                <x-filament::icon icon="heroicon-o-shield-check" class="delegation-kpi-icon" />
            </div>
            <p class="delegation-kpi-value">{{ $summary['backoffice_users'] ?? 0 }}</p>
        </div>

        <div class="delegation-kpi-card kpi-warning">
            <div class="delegation-kpi-head">
                <p class="delegation-kpi-title">Farmers</p>
                <x-filament::icon icon="heroicon-o-user-group" class="delegation-kpi-icon" />
            </div>
            <p class="delegation-kpi-value">{{ $summary['farmers'] ?? 0 }}</p>
        </div>

        <div class="delegation-kpi-card kpi-danger">
            <div class="delegation-kpi-head">
                <p class="delegation-kpi-title">Supporters</p>
                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="delegation-kpi-icon" />
            </div>
            <p class="delegation-kpi-value">{{ $summary['supporters'] ?? 0 }}</p>
        </div>

        <div class="delegation-kpi-card kpi-indigo">
            <div class="delegation-kpi-head">
                <p class="delegation-kpi-title">Experts</p>
                <x-filament::icon icon="heroicon-o-academic-cap" class="delegation-kpi-icon" />
            </div>
            <p class="delegation-kpi-value">{{ $summary['experts'] ?? 0 }}</p>
        </div>
    </div>

    <x-filament::section class="mt-4">
        <div class="text-sm text-gray-600">
            Ethiopia governance scope: Federal (National) to Regional, Zone, Woreda, and Kebele.
            This panel is for backoffice delegation only (super admin, admin, supporter, expert).
            Farmer accounts remain operational/mobile users and are created by admins via standard user management.
        </div>
    </x-filament::section>

    <x-filament::section class="mt-4">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
