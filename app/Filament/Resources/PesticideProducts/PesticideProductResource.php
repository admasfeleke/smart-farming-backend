<?php

namespace App\Filament\Resources\PesticideProducts;

use App\Filament\Resources\PesticideProducts\Pages\CreatePesticideProduct;
use App\Filament\Resources\PesticideProducts\Pages\EditPesticideProduct;
use App\Filament\Resources\PesticideProducts\Pages\ListPesticideProducts;
use App\Models\PesticideProduct;
use App\Models\User;
use App\Support\RegionScope;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PesticideProductResource extends Resource
{
    protected static ?string $model = PesticideProduct::class;

    protected static string | UnitEnum | null $navigationGroup = 'Disease Management';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-beaker';
    protected static ?int $navigationSort = 31;
    protected static ?string $recordTitleAttribute = 'product_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('product_name')
                ->required()
                ->maxLength(150),

            TextInput::make('active_ingredient')
                ->required()
                ->maxLength(200),

            TextInput::make('formulation')
                ->maxLength(80),

            TextInput::make('product_type')
                ->required()
                ->default('fungicide')
                ->maxLength(80),

            Select::make('registration_status')
                ->required()
                ->options([
                    'locally_verified_required' => 'Local verification required',
                    'locally_registered' => 'Locally registered',
                    'restricted' => 'Restricted',
                    'retired' => 'Retired',
                ])
                ->default('locally_verified_required'),

            Textarea::make('label_warning')
                ->rows(3)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('active_ingredient')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('product_type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('registration_status')
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPesticideProducts::route('/'),
            'create' => CreatePesticideProduct::route('/create'),
            'edit' => EditPesticideProduct::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User && RegionScope::isBackoffice($user);
    }

    public static function canViewAny(): bool
    {
        return self::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && in_array(RegionScope::roleName($user), ['super_admin', 'admin', 'expert'], true);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && in_array(RegionScope::roleName($user), ['super_admin', 'admin', 'expert'], true);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && in_array(RegionScope::roleName($user), ['super_admin', 'admin'], true);
    }
}
