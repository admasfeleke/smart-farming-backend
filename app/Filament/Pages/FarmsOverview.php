<?php

namespace App\Filament\Pages;

use App\Models\Farm;
use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use UnitEnum;
use BackedEnum;

class FarmsOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | UnitEnum | null $navigationGroup = 'Farm Management';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.farms-overview';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isBackoffice($user)
            && AuthorityMatrix::can($user, 'farm.view_any');
    }

    public function table(Table $table): Table
    {
        $query = Farm::query()
            ->with(['farmer', 'region'])
            ->withCount('plots')
            ->withSum('plots', 'area_hectares');

        $user = auth()->user();
        if ($user instanceof User && ! RegionScope::isSuperAdmin($user)) {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('region_id', $regionIds);
            }
        }

        return $table
           ->query($query)

            ->columns([
                Tables\Columns\TextColumn::make('farm_name')
                    ->label('Farm')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('farmer.name')
                    ->label('Farmer')
                    ->searchable()
                    ->placeholder('-')
                    ->wrap()
                    ->limit(32),

                Tables\Columns\TextColumn::make('region.name')
                    ->label('Administrative Unit')
                    ->placeholder('-')
                    ->wrap()
                    ->limit(36),

                Tables\Columns\TextColumn::make('plots_count')
                    ->label('Plots')
                    ->sortable(),
                Tables\Columns\TextColumn::make('plots_sum_area_hectares')
                    ->label('Total Area (ha)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),


                Tables\Columns\TextColumn::make('latitude')
                    ->label('Lat')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('longitude')
                    ->label('Lng')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('region')
                    ->relationship('region', 'name')
                    ->label('Administrative Unit'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])

            ->paginated([10, 25, 50]);
    }
}
