<?php

namespace App\Filament\Resources\Farms;

use App\Filament\Resources\Farms\Pages\ListFarms;
use App\Filament\Resources\Farms\Schemas\FarmForm;
use App\Filament\Resources\Farms\Tables\FarmsTable;
use App\Models\Farm;
use App\Models\User;
use App\Support\RegionScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use Filament\Tables;

class FarmResource extends Resource
{
    protected static ?string $model = Farm::class;

   // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
   protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string | UnitEnum | null $navigationGroup = 'Farm Management';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'farm_name';

    public static function form(Schema $schema): Schema
    {
        return FarmForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FarmsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFarms::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        // Farm structure CRUD is farmer-owned via mobile/API flow.
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['farmer', 'region']);
        $user = auth()->user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if (RegionScope::isSuperAdmin($user) || RegionScope::roleName($user) === 'admin') {
            return $query;
        }

        $regions = RegionScope::accessibleRegionIds($user);
        if ($regions === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('region_id', $regions);
    }
}
