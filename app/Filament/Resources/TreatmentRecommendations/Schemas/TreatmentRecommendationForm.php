<?php

namespace App\Filament\Resources\TreatmentRecommendations\Schemas;

use App\Models\Crop;
use App\Models\PesticideProduct;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TreatmentRecommendationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('crop_id')
                ->label('Crop')
                ->options(fn (): array => Crop::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->helperText('Leave empty only for general fallback guidance.'),

            TextInput::make('disease_key')
                ->label('Disease key')
                ->maxLength(120)
                ->helperText('Use normalized key such as tomato_late_blight or potato_early_blight.'),

            TextInput::make('disease_keyword')
                ->label('Disease keyword')
                ->maxLength(80)
                ->helperText('Optional broad match: blight, rust, mold, bacterial, virus.'),

            Select::make('recommendation_type')
                ->required()
                ->options([
                    'chemical' => 'Chemical',
                    'natural' => 'Natural',
                    'integrated' => 'Integrated',
                ])
                ->default('chemical'),

            Select::make('pesticide_product_id')
                ->label('Pesticide product')
                ->options(fn (): array => PesticideProduct::query()->where('is_active', 1)->orderBy('product_name')->pluck('product_name', 'id')->all())
                ->searchable(),

            TextInput::make('title')
                ->required()
                ->maxLength(150),

            Textarea::make('summary')
                ->rows(2)
                ->columnSpanFull(),

            Textarea::make('natural_treatment')
                ->rows(3)
                ->columnSpanFull(),

            Textarea::make('modern_treatment')
                ->rows(3)
                ->columnSpanFull(),

            Textarea::make('dosage_text')
                ->rows(2)
                ->columnSpanFull(),

            Textarea::make('application_timing')
                ->rows(2)
                ->columnSpanFull(),

            TextInput::make('pre_harvest_interval_days')
                ->label('PHI days')
                ->numeric()
                ->minValue(0),

            TextInput::make('re_entry_interval_hours')
                ->label('REI hours')
                ->numeric()
                ->minValue(0),

            TextInput::make('max_applications')
                ->numeric()
                ->minValue(0),

            Textarea::make('ppe')
                ->label('PPE')
                ->rows(2)
                ->columnSpanFull(),

            Textarea::make('restrictions')
                ->rows(2)
                ->columnSpanFull(),

            Select::make('approval_status')
                ->required()
                ->options([
                    'approved' => 'Approved',
                    'draft' => 'Draft',
                    'retired' => 'Retired',
                ])
                ->default('approved'),

            DateTimePicker::make('approved_at'),

            Toggle::make('is_active')
                ->default(true),
        ]);
    }
}
