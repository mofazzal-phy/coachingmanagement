<?php

// Health check (public) - only here, removed from Modules/Core/routes/api.php
Route::get('health', fn() => response()->json([
    'status' => 'success',
    'app' => config('app.name'),
    'version' => 'v1',
    'timestamp' => now()->toIso8601String(),
]));
