<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Filament panel is mounted at /admin.
// Provide a friendly alias for users who expect the default /filament path.
Route::redirect('/filament', '/admin');
Route::redirect('/filament/login', '/admin/login');

// Lightweight unauthenticated health probe for mobile connectivity checks.
// The Flutter app probes `$base/up` to distinguish API reachability from network issues.
Route::get('/up', function () {
    return response()->json(['status' => 'ok']);
});
