<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use UnitEnum;
use BackedEnum;

class FarmersOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | UnitEnum | null $navigationGroup = 'Farm Management';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.farmers-overview';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isBackoffice($user)
            && AuthorityMatrix::can($user, 'farm.view_any');
    }

    public function table(Table $table): Table
    {
        $query = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'farmer'))
            ->with(['region'])
            ->withCount('farms');

        $authUser = auth()->user();
        if ($authUser instanceof User && ! RegionScope::isSuperAdmin($authUser)) {
            $regionIds = RegionScope::accessibleRegionIds($authUser);
            if ($regionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('region_id', $regionIds);
            }
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Farmer Name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(36),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('region.name')
                    ->label('Administrative Unit')
                    ->placeholder('-')
                    ->sortable()
                    ->wrap()
                    ->limit(36),

                Tables\Columns\TextColumn::make('farms_count')
                    ->label('Farms')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered On')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('region')
                    ->relationship('region', 'name')
                    ->label('Administrative Unit'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->paginated([10, 25, 50]);
    }
}
