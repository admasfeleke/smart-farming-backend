<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Role Name')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->helperText('Example: farmer, supporter, expert, admin.'),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->maxLength(255),
            ]);
    }
}
