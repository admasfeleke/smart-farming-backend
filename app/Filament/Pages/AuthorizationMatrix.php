<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\RegionScope;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class AuthorizationMatrix extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'System Settings';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.authorization-matrix';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && RegionScope::isSuperAdmin($user);
    }

    protected function getViewData(): array
    {
        $matrix = (array) config('authority_matrix.actions', []);
        $roles = ['super_admin', 'admin', 'supporter', 'expert', 'farmer'];
        $levels = ['national', 'region', 'zone', 'woreda', 'kebele', '*'];

        $rows = [];
        foreach ($matrix as $action => $ruleSet) {
            $ruleSet = (array) $ruleSet;
            $row = [
                'action' => (string) $action,
                'roles' => [],
            ];

            foreach ($roles as $role) {
                $allowed = array_values(array_filter(array_map(
                    static fn (string $value): string => strtolower(trim($value)),
                    (array) ($ruleSet[$role] ?? [])
                )));

                $row['roles'][$role] = [
                    'levels' => $allowed,
                    'labels' => $allowed === []
                        ? 'No'
                        : (in_array('*', $allowed, true) ? 'Any' : implode(', ', array_map('ucfirst', $allowed))),
                    'allowed' => $allowed !== [],
                ];
            }

            $rows[] = $row;
        }

        return [
            'roles' => $roles,
            'levels' => $levels,
            'rows' => $rows,
        ];
    }
}

