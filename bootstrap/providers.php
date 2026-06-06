<?php

return array_values(array_filter([
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    filter_var(env('FILAMENT_ENABLED', true), FILTER_VALIDATE_BOOL)
        ? App\Providers\Filament\AdminPanelProvider::class
        : null,
]));
