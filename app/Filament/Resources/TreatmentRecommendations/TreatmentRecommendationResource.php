<?php

namespace App\Filament\Resources\TreatmentRecommendations;

use App\Filament\Resources\TreatmentRecommendations\Pages\CreateTreatmentRecommendation;
use App\Filament\Resources\TreatmentRecommendations\Pages\EditTreatmentRecommendation;
use App\Filament\Resources\TreatmentRecommendations\Pages\ListTreatmentRecommendations;
use App\Filament\Resources\TreatmentRecommendations\Schemas\TreatmentRecommendationForm;
use App\Filament\Resources\TreatmentRecommendations\Tables\TreatmentRecommendationsTable;
use App\Models\TreatmentRecommendation;
use App\Models\User;
use App\Support\RegionScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TreatmentRecommendationResource extends Resource
{
    protected static ?string $model = TreatmentRecommendation::class;

    protected static string | UnitEnum | null $navigationGroup = 'Disease Management';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 30;
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return TreatmentRecommendationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TreatmentRecommendationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTreatmentRecommendations::route('/'),
            'create' => CreateTreatmentRecommendation::route('/create'),
            'edit' => EditTreatmentRecommendation::route('/{record}/edit'),
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

        return $user instanceof User && in_array(RegionScope::roleName($user), ['super_admin', 'admin'], true);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['crop', 'pesticideProduct']);
    }
}
